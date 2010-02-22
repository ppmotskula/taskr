<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 */
/**
 * Account controller
 */
class AccountController extends Zend_Controller_Action
{
    /**
     * @ignore (internal)
     * @var Taskr_Model_User
     */
    protected static $_user;

    /**
     * @ignore (internal)
     * @var Taskr_Model_DataMapper
     */
    protected static $_mapper;

    /**
     * @ignore (internal)
     * @var Zend_Controller_Action_Helper_Redirector
     */
    protected static $_redirector;

    /**
     * Initializes the controller
     */
    public function init()
    {
        self::$_redirector = $this->_helper->Redirector;
        if (Zend_Auth::getInstance()->hasIdentity()) {
            self::$_user = Zend_Auth::getInstance()->getIdentity();
        }
        self::$_mapper = Taskr_Model_DataMapper::getInstance();
    }

    /**
     * Displays the Account page to logged-in users
     */
    public function indexAction()
    {
        // @todo action body
    }

    /**
     * Processes the login form, redisplaying it if there were any errors
     */
    public function loginAction()
    {
        // forward to Task controller if user is logged in already
        if (isset(self::$_user)) {
            self::$_redirector->gotoSimple('index', 'task');
        }

        // only process POST requests
        $request = $this->getRequest();
        if ($request->isPost()) {
            $formData = $request->getPost();
            if (!$username = $formData['username']) {
                $formErrors['username'] = 'Username is required';
            }
            if (!$password = $formData['password']) {
                $formErrors['password'] = 'Password is required';
            }
            if (!isset($formErrors)) {
                // skip auth if credentials not given
                if ('Reset password' == $formData['submit']) {
                    // special processing for 'reset password'
                    $user = self::$_mapper->findUserByUsername($username);
                    if ($user && isset($user->email)) {
                        // send password reset email
                        $link = 'http://' . $_SERVER['SERVER_NAME'] .
                            $this->view->url(array(
                                'controller' => 'account',
                                'action' => 'reset-password',
                                'uid' => $user->id,
                                'key' => Taskr_Util::hashPassword($user->password),
                            ));
                        $mail = new Zend_Mail('utf-8');
                        $mail
                            ->setFrom('support@taskr.eu', 'Taskr')
                            ->setSubject('Reset your Taskr password')
                            ->addTo($user->email)
                            ->setBodyText(<<<END
Hi {$user->username},

somebody (possibly yourself) has requested resetting your Taskr password.

If it was you, follow the link below, and choose a new password afterwards.

$link

If it wasn't you, just ignore and delete this email.
Your password will remain unchanged.
END
);
                        try {
                            $mail->send();
                            $formErrors['reset'] = <<<END
<h2>Reset password</h2>
<p>We've sent a 'password reset' link to the email address you have given us.
It should arrive shortly.</p>
<p>Please check your inbox and follow the instructions
therein to actually reset your password.</p>
END;
                        } catch(Exception $e) {
                            $formErrors['reset'] = <<<END
<h2>Reset password</h2>
<p>{$e->getMessage()}</p>
<p>Your password cannot be reset at this time. Sorry.</p>
END;
                        }
                    } else {
                        // user not found or user has no email address
                        $formErrors['reset'] = <<<END
<h2>Reset password</h2>
<p>We don't have an e-mail address for the user '$username',
so unfortunately we cannot reset this user's password.</p>
<p>If you manage to recall the password, try logging in again.
If not, sign up as a new user.</p>
END;
                    }
                } else {
                    // process any other submit actions
                    $auth = Zend_Auth::getInstance();
                    $adapter = new Taskr_Auth_Adapter_Password($username, $password);
                    $result = $auth->authenticate($adapter);
                    if (!$result->isValid()) {
                        $formErrors['credentials'] =
                            'User not found or incorrect password';
                    } else {
                        // success; persist identity
                        $auth->setStorage(new Zend_Auth_Storage_Session(
                            'Taskr'
                        ));
                        $auth->getStorage()->write($auth->getIdentity());
                        $session = new Zend_Session_Namespace(
                            $auth->getStorage()->getNamespace()
                        );
                        if ($formData['rememberme']) {
                            Zend_Session::rememberMe();
                        }
                        // forward to Task controller
                        self::$_redirector->gotoSimple('index', 'task');
                    }
                }
            }
        }
        // repopulate the form and error messages if any
        $this->view->formData = $formData;
        $this->view->formErrors = $formErrors;
        // next, the login form will be shown
    }

    /**
     * Terminates the user session
     */
    public function logoutAction()
    {
        // bail out if nobody is logged in
        if (!isset(self::$_user)) {
            self::$_redirector->gotoSimple('index', 'index');
        }

        // stop active task if any
        if ($task = self::$_user->activeTask()) {
            $task->stop();
        }

        // clear identity, forget me, and go to welcome page
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::forgetMe();
        self::$_redirector->gotoSimple('index', 'index');
    }

    /**
     * Processes the signup form, redisplaying it if there were any errors
     */
    public function signupAction()
    {
        // forward to Task controller if user is logged in already
        if (isset(self::$_user)) {
            self::$_redirector->gotoSimple('index', 'task');
        }

        // only process POST requests
        $request = $this->getRequest();
        if ($request->isPost()) {
            $formData = $request->getPost();

            // check username
            $username = $formData['username'];
            if (strlen($username) < 6) {
                $formErrors['username'] = 'At least 6 characters, please';
            } elseif (self::$_mapper->findUserByUsername($username)) {
                $formErrors['username'] = 'This username is already taken';
            }

            // check password
            $password = $formData['password'];
            if (strlen($password) < 6) {
                $formErrors['password'] = 'At least 6 characters, please';
            }
            if ($password != $formData['repeat']) {
                $formErrors['repeat'] = 'Passwords do not match';
            }

            // if email is given, check it for structural validity
            if ($email = $formData['email']) {
                if (!preg_match(
                    '/^[a-zA-Z0-9._+-]+@(?:[a-zA-Z0-9_+-]+\.)+[a-zA-Z]{2,4}$/',
                    $email
                )) {
                    $formErrors['email'] = 'Not a valid email address';
                }
            }

            // check acceptance of terms
            if (1 != $formData['acceptterms']) {
                $formErrors['acceptterms'] = 'Sorry, but you have to';
            }

            // skip further processing if form data is invalid
            if (!isset($formErrors)) {
                // create and save new user
                $user = new Taskr_Model_User(array(
                    'username' => $username,
                    'password' => Taskr_Util::hashPassword($password),
                    'email' => $email,
                    // @todo add support for tzDiff
                ));
                self::$_mapper->saveUser($user);

                if ($email) {
                    // @todo send email for checking
                    // and create a "check your email for confirmation link" task
                }

                // proceed to login
                $this->loginAction();
            }
        }
        // repopulate the form and error messages if any
        $this->view->formData = $formData;
        $this->view->formErrors = $formErrors;
        // next, the signup form will be shown
    }

    /**
     * Processes the reset password action
     */
    public function resetPasswordAction()
    {
        // forward to Task controller if user is logged in already
        if (isset(self::$_user)) {
            self::$_redirector->gotoSimple('index', 'task');
        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            // process form data
            $formData = $request->getPost();

            // check password
            $password = $formData['password'];
            if (strlen($password) < 6) {
                $formErrors['password'] = 'At least 6 characters, please';
            }
            if ($password != $formData['repeat']) {
                $formErrors['repeat'] = 'Passwords do not match';
            }

            $uid = $formData['uid'];
            $key = $formData['key'];
        } else {
            // get parameters from the URL
            $uid = $this->_getParam('uid');
            $key = $this->_getParam('key');
        }

        // bail out if uid or key is missing or invalid
        if (!$uid ||
            !$key ||
            !($user = self::$_mapper->findUser($uid)) ||
            !Taskr_Util::testPassword($user->password, $key)
        ) {
            $formErrors['key'] = <<<END
<h2>Reset password</h2>
<p>Invalid key. You might have followed an outdated password reset link.
Your password has <em>not</em> been reset.</p>
END;
        }

        if (!isset($formErrors) && isset($password)) {
            // save entered password, proceed to login
            $user->password=Taskr_Util::hashPassword($password);
            self::$_mapper->saveUser($user);
            $this->loginAction();
        }

        // show the password reset form
        $formData['username'] = $user->username;
        $formData['uid'] = $uid;
        $formData['key'] = $key;
        $this->view->formData = $formData;
        $this->view->formErrors = $formErrors;
    }

    /**
     * Processes the confirm email action
     */
    public function confirmEmailAction()
    {
        /*
         * @todo
         *
         * if !key
         *   go to frontpage
         * elseif !loggedIn
         *   show login form with redirect-upon-success set to confirmemailaction
         * elseif user has no pending email change request
         *   go to tasklist
         * else
         *   set email = pendingemail
         *   go to tasklist
         * fi
         */
    }

}
