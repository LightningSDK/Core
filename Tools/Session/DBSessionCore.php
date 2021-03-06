<?php

namespace lightningsdk\core\Tools\Session;

use lightningsdk\core\Tools\Configuration;
use lightningsdk\core\Tools\Database;
use lightningsdk\core\Tools\Logger;
use lightningsdk\core\Tools\Messenger;
use lightningsdk\core\Tools\Output;
use lightningsdk\core\Tools\Request;
use lightningsdk\core\Tools\Security\Random;
use lightningsdk\core\Tools\SingletonObject;

/**
 * Class Session
 *   An object to reference a user's session on the site.
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $last_ping
 * @property integer $state
 * @property string $session_key
 * @property string $form_token
 */
class DBSessionCore extends SingletonObject {

    const STATE_ANONYMOUS = 0;
    const STATE_REMEMBER = 1;
    const STATE_PASSWORD = 2;
    const STATE_APP = 4;

    const TABLE = 'session';
    const PRIMARY_KEY = 'session_id';

    protected $__json_encoded_fields = ['content'];
    protected static $initialized = false;

    /**
     * Get the current session.
     *
     * @param boolean $create_object
     *   Whether to create a new object if it doesn't exist.
     * @param boolean $create_session
     *   Whether to create a new session for the client if one doesn't exit.
     *
     * @return Session
     *   The current session.
     */
    public static function getInstance($create_object = true, $create_session = true) {
        $session = parent::getInstance($create_object, $create_session);

        // If the session has been initialized, make sure to mark it.
        if ($session) {
            static::$initialized = true;
        }

        return $session;
    }

    protected static function loadRequestSessionKey() {
        return Request::cookie(Configuration::get('session.cookie'), 'base64');
    }

    /**
     * Create the session object.
     *
     * @param boolean $create_session
     *   Whether to create the session for the user.
     *
     * @return Session
     *   The current session.
     */
    public static function createInstance($create_session = true) {
        if ($session_key = static::loadRequestSessionKey()) {
            $session_criteria = [
                'session_key' => ['LIKE', $session_key]
            ];
            // If the session is only allowed on one IP.
            if (Configuration::get('session.single_ip')) {
                $session_criteria['session_ip'] = Request::getIP();
            }

            // See if the session exists.
            if ($session_details = Database::getInstance()->selectRow('session', $session_criteria)) {
                // Load the session.
                $session = new static($session_details);

                if ($session->validateState()) {
                    $session->ping();
                    return $session;
                } else {
                    $session->destroy();
                    return static::create();
                }
            } else {
                // Possible security issue.
                Logger::security('Bad session', Logger::SEVERITY_MED);
                // There is an old cookie that we should delete.
                // Send a cookie to erase the users cookie, in case this is really a minor error.
                static::clearCookie();
                return static::create();
            }
        }
        elseif ($create_session) {
            // No session exists, create a new one.
            return static::create();
        }
        else {
            return null;
        }
    }

    public function validateState() {
        // If the session has expired
        if ($remember_ttl = Configuration::get('session.remember_ttl')) {
            if ($this->last_ping < time() - $remember_ttl) {
                return false;
            }
        }

        // If the password time has lapsed, remove password status.
        if ($this->last_ping < time() - Configuration::get('session.password_ttl')) {
            if ($this->getState(static::STATE_REMEMBER)) {
                $this->unsetState(static::STATE_PASSWORD);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Create a new session.
     *
     * @param int $user_id
     *   Optional user ID if the user is already known.
     * @param bool $remember
     *   Optional remember flag to remember the user after they have logged out.
     *
     * @return session
     */
    public static function create($user_id = 0, $remember = false) {
        $session_details = [];
        $new_sess_key = static::getNewSessionId();
        $new_token = Random::getInstance()->get(64, Random::BASE64);
        if (empty($new_sess_key) || empty($new_token)) {
            Messenger::error('Session error.');
        }
        $session_details['session_key'] = $new_sess_key;
        $session_details['last_ping'] = time();
        $session_details['session_ip'] = Request::getIP();
        $session_details['user_id'] = $user_id;
        $session_details['state'] = 0 | ($remember ? static::STATE_REMEMBER : 0);
        $session_details['form_token'] = $new_token;
        $session_details['session_id'] = Database::getInstance()->insert('session', $session_details);
        $session = new static($session_details);
        $session->setCookie();
        return $session;
    }

    public static function reset($user_id = 0, $remember = false) {
        static::setInstance(static::create($user_id, $remember));
    }

    /**
     * Set the user to the session.
     *
     * @param integer $user_id
     *   The new user id.
     */
    public function setUser($user_id) {
        Database::getInstance()->update('session', ['user_id' => $user_id], ['session_id' => $this->id]);
    }

    /**
     * Checks for password access.
     *
     * @param integer $state
     *
     * @return boolean
     */
    public function getState($state) {
        return (($state & $this->state) == $state);
    }

    /**
     * This is called when the user enters their password and password access is now allowed.
     */
    public function setState($state) {
        $this->state |= $state;
        Database::getInstance()->update('session', ['state' => ['expression' => 'state | ' . $state]], ['session_id' => $this->id]);
    }

    /**
     * Remove a state.
     *
     * @param integer $state
     *   The state value to set. Should be a value of 2^(n-1) so the first bit is 1,
     *   2nd bit is 2, 3rd bit is 4, etc.
     */
    public function unsetState($state) {
        $this->state ^= $state;
        Database::getInstance()->update('session', ['state' => ['expression' => 'state & ~ ' . $state]], ['session_id' => $this->id]);
    }

    /**
     * Remove all states.
     */
    public function resetState() {
        $this->state = 0;
        $this->save();
    }

    /**
     * When the instance is removed, make sure to destroy the session also.
     */
    public static function destroyInstance() {
        if ($session = self::getInstance(false)) {
            $session->destroy();
        }
        parent::destroyInstance();
    }

    /**
     * Destroy the current session and remove it from the database.
     */
    public function destroy() {
        if (!empty($this->id)) {
            $this->delete();
            $this->__data = [];
        }
        $this->clearCookie();
    }

    /**
     * Update the last active time on the session.
     */
    public function ping() {
        // Make the cookie last longer in the database.
        Database::getInstance()->update('session', ['last_ping' => time()], ['session_id' => $this->id]);
        // Make the cookie last longer in the browser.
        $this->setCookie();
    }

    /**
     * Output the cookie to the requesting web server (for relay to the client).
     */
    public function setCookie() {
        Output::setCookie(Configuration::get('session.cookie'), $this->session_key, Configuration::get('session.remember_ttl'), '/', Configuration::get('cookie_domain'));
    }

    /**
     * Sends a blank cookie to overwrite and forget any current session cookie.
     */
    static function clearCookie() {
        if (!headers_sent()) {
            unset($_COOKIE[Configuration::get('session.cookie')]);
            Output::clearCookie(Configuration::get('session.cookie'));
        }
    }

    /**
     * Gets a new random unique session id.
     *
     * @return mixed
     */
    static function getNewSessionId() {
        do{
            $key = Random::getInstance()->get(64, Random::BASE64);
            if (empty($key)) {
                return FALSE;
            }
        } while(Database::getInstance()->check('session', ['session_key'=>$key]));
        return $key;
    }

    /**
     * Dumps all sessions for the current user
     *
     * @param int $exception
     *   A session ID that can be left as active.
     *
     * @throws \Exception
     */
    public function dumpSessions($exception=0) {
        // Remove password state for all other sessions.
        Database::getInstance()->update(
            'session',
            [
                'state' => ['expression' => 'state & ' . self::STATE_REMEMBER],
            ],
            [
                'user_id' => $this->user_id,
                'session_id' => ['!=', $exception],
            ]
        );
        // Delete sessions that are not in the remember state.
        Database::getInstance()->delete('session',
            [
                'user_id' => $this->user_id,
                'state' => ['!&', self::STATE_REMEMBER],
                'session_id' => ['!=', $exception],
            ]
        );
    }

    /**
     * Remove all expired sessions from the database.
     *
     * @return integer
     *   The number of sessions removed.
     *
     * @throws \Exception
     */
    public static function clearExpiredSessions() {
        $timeouts = [];
        $timeouts[static::STATE_ANONYMOUS] = Configuration::get('session.anonymous_ttl', 86400);
        $timeouts[static::STATE_REMEMBER] = Configuration::get('session.remember_ttl', $timeouts[static::STATE_ANONYMOUS]);
        $timeouts[static::STATE_PASSWORD] = Configuration::get('session.password_ttl', $timeouts[static::STATE_REMEMBER]);
        $timeouts[static::STATE_APP] = Configuration::get('session.app_ttl', $timeouts[static::STATE_REMEMBER]);

        arsort($timeouts);
        $deletions = 0;
        $aggregate_state = 0;
        $db = Database::getInstance();
        foreach ($timeouts as $state => $timeout) {
            $deletions += $db->delete(
                'session',
                [
                    'last_ping' => ['<', time() - $timeout],
                    'state' => ['&', $aggregate_state, 0],
                ]
            );
            $aggregate_state |= $state;
        }
        return $deletions;
    }

    /**
     * Issue a new random key to the session. Everything else stays the same.
     */
    public function scramble() {
        $new_sess_id = static::getNewSessionId();
        if (empty($new_sess_id)) {
            Output::error('Session error.');
        }
        Database::getInstance()->update('session', ['session_key'=>$new_sess_id], ['session_id'=>$this->id]);
        $this->session_key = $new_sess_id;
        $this->setCookie();
    }

    /**
     * Destroy all instances of sessions for a user.
     *
     * @param integer $user_id
     *   The user id.
     */
    public function destroy_all($user_id) {
        Database::getInstance()->delete('session', ['user_id'=>$user_id]);
    }

    /**
     * Tell the browser to drop the session.
     */
    public function blank_session () {
        Output::clearCookie(Configuration::get('session.cookie'));
    }

    public static function isInitialized() {
        return static::$initialized;
    }

    public static function impliedInitialization() {
        static::$initialized = true;
    }
}
