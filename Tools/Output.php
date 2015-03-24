<?
/**
 * @file
 * A class for managing output.
 */

namespace Lightning\Tools;
use Lightning\Pages\Message;

/**
 * Class Output
 *
 * @package Lightning\Tools
 */
class Output {
    /**
     * The default output for access denied errors.
     */
    const ACCESS_DENIED = 1;

    /**
     * The default output for successful executions.
     */
    const SUCCESS = 2;

    /**
     * The default output for an error.
     */
    const ERROR = 3;

    /**
     * A list of cookies to output.
     *
     * @var array
     */
    protected static $cookies = array();

    protected static $statusStrings = array(
        1 => 'access denied',
        2 => 'success',
        3 => 'error',
    );

    public static function isJSONRequest() {
        $headers = apache_request_headers();
        if (empty($headers['Accept'])) {
            return false;
        }
        return strpos($headers['Accept'], 'json') > 0;
    }

    /**
     * Output data as json and end the request.
     *
     * @param array|integer $data
     *   The data to output as JSON.
     */
    public static function json($data = array(), $suppress_status = false) {
        // Predefined outputs.
        if ($data == self::ACCESS_DENIED) {
            $data = array('status' => 'access_denied');
        }
        elseif ($data == self::SUCCESS) {
            $data = array('status' => 'success');
        }
        elseif ($data == self::ERROR) {
            $data = array('status' => 'error');
        }
        elseif (!empty($data['status']) && !empty(self::$statusStrings[$data['status']])) {
            // Convert numeric status to string.
            $data['status'] = self::$statusStrings[$data['status']];
        }

        // Add errors and messages.
        if (Messenger::hasErrors()) {
            $data['errors'] = Messenger::getErrors();
        }
        if (Messenger::hasMessages()) {
            $data['messages'] = Messenger::getMessages();
        }

        if (!$suppress_status && empty($data['status']) && empty($data['errors'])) {
            $data['status'] = self::$statusStrings[self::SUCCESS];
        }

        // Output the data.
        header('Content-type: application/json');
        echo json_encode($data);

        // Terminate the script.
        exit;
    }

    public static function jsonData($data, $include_cookies = false) {
        $output = array(
            'data' => $data,
            'status' => 'success',
            'errors' => Messenger::getErrors(),
            'messages' => Messenger::getMessages(),
        );
        if ($include_cookies) {
            $output['cookies'] = self::$cookies;
        }
        echo json_encode($output);
        exit;
    }

    /**
     * Die on an error with a message in json format.
     *
     * @param string $error
     *   The error message.
     *
     * @deprecated
     *   The error() function will determine if json should be output based on the headers.
     */
    public static function jsonError($error = '') {
        $data = array(
            'errors' => Messenger::getErrors(),
            'messages' => Messenger::getMessages(),
            'status' => 'error',
        );

        if (!empty($error)) {
            $data['errors'][] = $error;
        }

        // Output the data.
        header('Content-type: application/json');
        echo json_encode($data);

        // Terminate the script.
        exit;
    }

    public static function XMLSegment($items, $type = null) {
        $output = '';
        foreach ($items as $key => $item) {
            if (is_numeric($key) && $type) {
                $key = $type;
            }
            if (is_array($item)) {
                $output .= "<$key>" . self::XMLSegment($item) . "</$key>";
            } else {
                $output .= "<$key>" . Scrub::toHTML($item) . "</$key>";
            }
        }
        return $output;
    }

    /**
     * Load and render the access denied page.
     */
    public static function accessDenied() {
        Messenger::error('Access Denied');
        // TODO : This can be simplified using the error function below.
        if (static::isJSONRequest()) {
            Output::json();
        } else {
            Template::resetInstance();
            $page = new Message();
            $page->execute();
        }
        exit;
    }

    public static function error($error) {
        Messenger::error($error);
        if(static::isJSONRequest()) {
            static::json();
        } else {
            Template::getInstance()->render('');
        }
        exit;
    }

    /**
     * Queue a cookie to be deleted.
     *
     * @param string $cookie
     *   The cookie name.
     */
    public static function clearCookie($cookie) {
        self::setCookie($cookie, '');
    }

    /**
     * Queue a cookie for output.
     *
     * @param string $cookie
     *   The name of the cookie.
     * @param string $value
     *   The value.
     * @param integer $ttl
     *   How long the cookie should last.
     *   This is not an expiration date like the php setcookie() function.
     * @param string $path
     *   The cookie path.
     * @param string $domain
     *   The cookie domain.
     * @param boolean $secure
     *   Whether the cookie can only be used over https.
     * @param boolean $httponly
     *   Whether the cookie can only be used as an http header.
     */
    public static function setCookie($cookie, $value, $ttl = null, $path = '/', $domain = null, $secure = null, $httponly = true) {
        $settings = array(
            'value' => $value,
            'ttl' => $ttl ? $ttl + time() : 0,
            'path' => $path,
            'domain' => $domain !== null ? $domain : Configuration::get('cookie_domain'),
            'secure' => $secure !== null ? $secure : (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 1 || strtolower($_SERVER['HTTPS']) == 'on')),
            'httponly' => $httponly,
        );
        if (isset(self::$cookies[$cookie])) {
            self::$cookies[$cookie] = $settings + self::$cookies[$cookie];
        } else {
            self::$cookies[$cookie] = $settings;
        }
    }

    /**
     * Set the cookie headers.
     * Does not actually send data until the content begins.
     */
    public static function sendCookies() {
        foreach (self::$cookies as $cookie => $settings) {
            setcookie($cookie, $settings['value'], $settings['ttl'], $settings['path'], $settings['domain'], $settings['secure'], $settings['httponly']);
        }
    }

    /**
     * Disable output buffering for streaming output.
     */
    public static function disableBuffering() {
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', "Off");
        @ini_set('implicit_flush', 1);

        for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
        ob_implicit_flush(true);
        echo str_repeat(' ', 9000);
    }
}
