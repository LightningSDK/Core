<?php

namespace Lightning\Pages;

use Lightning\Tools\ClientUser;
use Lightning\Tools\Configuration;
use Lightning\Tools\Database;
use Lightning\Tools\Form;
use Lightning\Tools\Messenger;
use Lightning\Tools\Navigation;
use Lightning\Tools\Request;
use Lightning\Tools\Template;
use Lightning\View\Page;
use Lightning\Model\User as UserObj;

class User extends Page {

    protected $page = 'user';

    public function get() {
        parent::__construct();
        Form::requiresToken();
        $user = ClientUser::getInstance();
        Template::getInstance()->set('redirect', Request::get('redirect'));
        if($user->id > 0){
            // USER IS LOGGED IN, REDIRECT TO THE DEFAULT PAGE
            $this->loginRedirect();
        }
    }

    public function postRegister() {
        $email = Request::post('email', 'email');
        $pass = Request::post('password');
        $pass2 = Request::post('password2');
        if ($email && $pass == $pass2){
            $user = ClientUser::getInstance();
            $previous_user = $user->id;
            if($user->create($email, $pass)){
                $user->login($email, $pass2);
                if($previous_user != 0) {
                    $user->merge_users($previous_user);
                }
                $redirect = Request::post('redirect');
                if(!empty($redirect) && !preg_match('|/?user[/$?]|', $redirect)) {
                    Navigation::redirect($redirect);
                } else {
                    Navigation::redirect(Configuration::get('user.login_url'));
                }
            }
        }
    }

    /**
     * Handle the user attempting to log in.
     */
    public function postLogin() {
        $email = Request::post('email', 'email');
        $pass = Request::post('password');
        $login_result = UserObj::login($email, $pass);
        if (!$login_result) {
            // BAD PASSWORD COMBO
            Messenger::error("You entered the wrong password. If you are having problems and would like to reset your password, <a href='/user?action=reset'>click here</a>");
            Template::getInstance()->set('action', 'login');
            return $this->get();
        } else {
            $this->loginRedirect();
            exit;
        }
    }

    /**
     * Log the user out and redirect them to the exit page.
     */
    public function getLogout() {
        ClientUser::getInstance()->logOut();
        Navigation::redirect(Configuration::get('logout_url') ?: '/');
        exit;
    }

    /**
     * Unsubscribe the user from all mailing lists.
     */
    public function getUnsubscribe() {
        if ($cyphserstring = Request::get('u', 'encrypted')) {
            $user = UserObj::loadByEncryptedUserReference($cyphserstring);
            $user->setActive(0);
            Messenger::message('Your email ' . $user->details['email'] . ' has been removed from all mailing lists.');
        } else {
            Messenger::error('Invalid request');
        }
    }

    public function getReset() {
        Template::getInstance()->set('action', 'reset');
    }

    /**
     * Send a temporary password.
     *
     * @todo This is not secure. There should be a security question and email should just be a link.
     */
    public function postReset() {
        if (!$email = Request::get('email', 'email')) {
            Messenger::error('Invalid email');
            return;
        }
        elseif (!$user = UserObj::loadByEmail($email)) {
            Messenger::error('User does not exist.');
            return;
        }
        if ($user->sendResetLink()) {
            Navigation::redirect('message?msg=reset');
        }
    }

    public function getSetPassword() {
        $key = Request::get('key', 'base64');
        if ($user = UserObj::loadByTempKey($key)) {
            Template::getInstance()->set('action', 'set_password');
            Template::getInstance()->set('key', $key);
        } else {
            $this->page = '';
            Messenger::error('Invalid Access Key');
        }
    }

    public function postSetPassword() {
        if ($user = UserObj::loadByTempKey(Request::get('key', 'base64'))) {
            if (($pass = Request::post('password')) && $pass == Request::post('password2')) {
                $user->setPass($pass);
                $user->registerToSession();
                $user->removeTempKey();
                $this->loginRedirect();
            } else {
                Messenger::error('Please enter a valid password and verify it by entering it again..');
            }
        } else {
            $this->page = '';
            Messenger::error('Invalid Access Key');
        }
    }

    public function getChangePass() {
    }

    /**
     * @todo this method needs to be updated.
     */
    public function postChangePass() {
        $template = Template::getInstance();
        $user = ClientUser::getInstance();
        $template->set('content', 'user_reset');
        if($_POST['new_pass'] == $_POST['new_pass_conf']){
            if(isset($_POST['new_pass'])){
                if($user->change_temp_pass($_POST['email'], $_POST['new_pass'], $_POST['code']))
                    $template->set("password_changed", true);
            } else {
                $template->set("change_password", true);
            }
        } else {
            Messenger::error('Your password is not secure. Please pick a more secure password.');
            $template->set("change_password", true);
        }
    }

    public function loginRedirect($page = null) {
        $redirect = Request::get('redirect');
        if ($redirect && !preg_match('|^[/?]user|', $redirect)) {
            Navigation::redirect($redirect);
        }
        elseif (!empty($page)) {
            Navigation::redirect($page);
        }
        else {
            Navigation::redirect(Configuration::get('user.login_url'));
        }
    }
}
