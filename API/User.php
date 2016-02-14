<?php

namespace Lightning\API;

use Lightning\Tools\ClientUser;
use Lightning\Tools\Configuration;
use Lightning\Tools\Messenger;
use Lightning\Tools\Output;
use Lightning\Tools\Request;
use Lightning\Tools\Session;
use Lightning\Model\User as UserModel;
use Lightning\Tools\SocialDrivers\Facebook;
use Lightning\Tools\SocialDrivers\Google;
use Lightning\Tools\SocialDrivers\SocialMediaApi;
use Lightning\Tools\SocialDrivers\SocialMediaApiInterface;
use Lightning\Tools\SocialDrivers\Twitter;
use Lightning\View\API;

class User extends API {
    public function postLogin() {
        $email = Request::post('email', 'email');
        $pass = Request::post('password');
        $login_result = UserModel::login($email, $pass);
        $data = array();
        if (!$login_result) {
            // BAD PASSWORD COMBO
            Messenger::error('Invalid password.');
        } else {
            $session = Session::getInstance();
            $session->setState(Session::STATE_APP);
            $data['cookies'] = array('session' => $session->session_key);
            Output::setJsonCookies(true);
            return $data;
        }
    }

    public function postFacebookLogin() {
        if ($token = SocialMediaApi::getToken()) {
            $fb = Facebook::getInstance(true, $token['token'], $token['auth']);
            $this->finishSocialLogin($fb);
            exit;
        }
        Output::error('Invalid Token');
    }

    public function postGoogleLogin() {
        if ($token = SocialMediaApi::getToken()) {
            $google = Google::getInstance(true, $token['token'], $token['auth']);
            $this->finishSocialLogin($google);
            exit;
        }
        Output::error('Invalid Token');
    }

    public function postTwitterLogin() {
        if ($token = Twitter::getAccessToken()) {
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
        $data['cookies'] = array('session' => Session::getInstance()->session_key);
        Output::json($data);
    }

    public function postRegister() {
        $email = Request::post('email', 'email');
        $pass = Request::post('password');
        
        // Validate POST data
        if (!$this->validateData($email, $pass)) {
            // Immediately output all the errors
            Output::error("Invalid Data");
        }
        
        // Register user
        $res = UserModel::register($email, $pass);
        if ($res['success']) {
            Output::json($res['data']);
        } else {
            Output::error($res['error']);
        }
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
