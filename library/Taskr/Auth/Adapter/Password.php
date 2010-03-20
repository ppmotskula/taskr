<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 */
/**
 * Password adapter for Taskr
 *
 * Works with salted SHA-1 hashes
 */
class Taskr_Auth_Adapter_Password implements Zend_Auth_Adapter_Interface
{
    /**
     * @ignore (internal)
     * @var string
     */
    protected $_username;

    /**
     * @ignore (internal)
     * @var string
     */
    protected $_password;

    /**
     * @ignore (internal)
     * @var Taskr_Model_DataMapper
     */
    protected $_mapper = NULL;

    /**
     * @param string $username
     * @param string $password
     */
    public function __construct($username, $password)
    {
        $this->_mapper = Taskr_Model_DataMapper::getInstance();
        $this->_username = $username;
        $this->_password = $password;
    }

    /**
     * Performs authentication attempt
     * @return Zend_Auth_Result
     * @throws Zend_Auth_Adapter_Exception
     */
    public function authenticate()
    {
        // no mapper, no luck
        if (!is_a($this->_mapper, 'Taskr_Model_DataMapper')) {
            throw new Zend_Auth_Adapter_Exception(
                'Cannot authenticate: no data mapper');
        }

        // try to find user by username, bail out if not found
		$user = $this->_mapper->findUserByUsername($this->_username);
        if (!is_a($user, 'Taskr_Model_User')) {
            return new Zend_Auth_Result(
                Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND,
                $this->_username);
        }

        // check if given password matches, bail out if not
        if (!Taskr_Util::testPassword($this->_password, $user->password)) {
            return new Zend_Auth_Result(
                Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,
                $this->_username);
        }

        // success!
        return new Zend_Auth_Result(
            Zend_Auth_Result::SUCCESS,
            $user);
    }

}
