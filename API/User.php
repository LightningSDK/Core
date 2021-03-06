<?php

namespace lightningsdk\core\API;

use Exception;
use lightningsdk\core\Tools\ClientUser;
use lightningsdk\core\Tools\Configuration;
use lightningsdk\core\Tools\Messenger;
use lightningsdk\core\Tools\Output;
use lightningsdk\core\Tools\Request;
use lightningsdk\core\Model\User as UserModel;
use lightningsdk\core\Tools\Session\DBSession;
use lightningsdk\core\Tools\SocialDrivers\Facebook;
use lightningsdk\core\Tools\SocialDrivers\Google;
use lightningsdk\core\Tools\SocialDrivers\SocialMediaApi;
use lightningsdk\core\Tools\SocialDrivers\SocialMediaApiInterface;
use lightningsdk\core\Tools\SocialDrivers\Twitter;
use lightningsdk\core\View\API;

class User extends API {
    public function get() {
        return ['logged_in' => !ClientUser::getInstance()->isAnonymous()];
    }

    public function postLogin() {
        $email = Request::post('email', 'email');
        $pass = Request::post('password');
        $login_result = UserModel::login($email, $pass);
        $data = [];
        if (!$login_result) {
            // BAD PASSWORD COMBO
            Messenger::error('Invalid password.');
        } else {
            $session = DBSession::getInstance();
            $session->setState(DBSession::STATE_APP);
            $data['cookies'] = ['session' => $session->session_key];
            $data['user_id'] = ClientUser::getInstance()->id;
            Output::setJsonCookies(true);
            return $data;
        }
    }

    public function postFacebookLogin() {
        if ($token = SocialMediaApi::getRequestToken()) {
            $fb = Facebook::getInstance(true, $token['token'], $token['auth']);
            $this->finishSocialLogin($fb);
            exit;
        }
        Output::error('Invalid Token');
    }

    public function postGoogleLogin() {
        if ($token = SocialMediaApi::getRequestToken()) {
            Google::setApp(true);
            $google = Google::getInstance(true, $token['token'], $token['auth']);
            $this->finishSocialLogin($google);
            exit;
        }
        Output::error('Invalid Token');
    }

    public function postTwitterLogin() {
        // Do not verify the twitter token against the session,
        // because it's coming in through the API which means
        // an app created it.
        if ($token = Twitter::getAccessToken(false)) {
            $twitter = Twitter::getInstance(true, $token);
            $this->finishSocialLogin($twitter);
            exit;
        }
        Output::error('Invalid Token');
    }

    /**
     * @param SocialMediaApiInterface $social_api
     */
    protected function finishSocialLogin($social_api) {
        $social_api->setupUser();
        $social_api->activateUser();
        $social_api->afterLogin();

        // Output the new cookie.
        $data['cookies'] = ['session' => DBSession::getInstance()->session_key];
        $data['user_id'] = ClientUser::getInstance()->id;
        Output::json($data);
    }

    /**
     * Register a user.
     *
     * @return int
     *
     * @throws Exception
     */
    public function postRegister() {
        $email = Request::post('email', 'email');
        $pass = Request::post('password');
        
        // Validate POST data
        if (!$this->validateData($email, $pass)) {
            // Immediately output all the errors
            Output::error("Invalid Data");
        }
        
        // Register user
        $user = UserModel::registerAndSignIn($email, $pass);
        return Output::SUCCESS;
    }

    public function postReset() {
        if (!$email = Request::get('email', 'email')) {
            Output::error('Invalid email');
        }
        elseif (!$user = UserModel::loadByEmail($email)) {
            Output::error('User does not exist.');
        }
        elseif ($user->sendResetLink()) {
            return Output::SUCCESS;
        }
        Output::error('Could not reset password.');
    }

    public function postLogout() {
        $user = ClientUser::getInstance();
        $user->logOut();
    }
    
    /**
     * Validates POST data (email, password and confirmation).
     * 
     * @param string $email
     * @param string $pass
     *
     * @return boolean
     */
    protected function validateData($email, $pass) {
        // Default value
        $result = TRUE;
        
        // Are all fields filled?
        if (is_null($email) OR is_null($pass)) {
            Messenger::error('Please fill out all the fields');
            $result = FALSE;
        }
        
        // Is email correct?
        if ($email === FALSE) {
            Messenger::error('Please enter a valid email');
            $result = FALSE;
        }

        // Are passwords strong enough? Check its length
        $min_password_length = Configuration::get('user.min_password_length');
        if (strlen($pass) < $min_password_length) {
            Messenger::error("Passwords must be at least {$min_password_length} characters");
            $result = FALSE;
        }

        return $result;
    }
}
