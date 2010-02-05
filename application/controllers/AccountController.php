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
     * var Taskr_Model_User
     */
    protected $_user;

    /**
     * Initializes the controller
     */
    public function init()
    {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $this->_user = Zend_Auth::getInstance()->getIdentity();
        }
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
        if (isset($this->_user)) {
            $this->_forward('index', 'task');
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
                $auth = Zend_Auth::getInstance();
                $adapter = new Taskr_Auth_Adapter_Password($username, $password);
                $result = $auth->authenticate($adapter);
                if (!$result->isValid()) {
                    $formErrors['credentials'] =
                        'User not found or incorrect password'
                    ;
                } else {
                    // success; persist identity
                    $auth->setStorage(new Zend_Auth_Storage_Session(
                        'Taskr'
                    ));
                    $auth->getStorage()->write($auth->getIdentity());
                    $session = new Zend_Session_Namespace(
                        $auth->getStorage()->getNamespace()
                    );
                    /* $session->setExpirationSeconds(24 * 3600); */
                    if ($formData['rememberme']) {
                        Zend_Session::rememberMe();
                    }
                    // forward to Task controller
                    $this->_forward('index', 'task');
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
        // @todo action body
    }

    /**
     * Processes the signup form, redisplaying it if there were any errors
     */
    public function signupAction()
    {
        // @todo action body
    }

    /**
     * Processes the reset password action
     */
    public function resetPasswordAction()
    {
        // @todo action body
    }

    /**
     * Processes the confirm email action
     */
    public function confirmEmailAction()
    {
        // @todo action body
    }

}











