<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 */
/**
 *
 */
class Taskr_Auth_Adapter_Password implements Zend_Auth_Adapter_Interface
{
    protected $_username; /// string
    protected $_password; /// string
    protected $_mapper = NULL; /// Model_DataMapper

    public function __construct($username, $password)
    {
        $this->_mapper = new Model_DataMapper();
        $this->_username = $username;
        $this->_password = $password;
    }

    public function authenticate() /// Zend_Auth_Result
    {
        if (! is_a($this->_mapper, 'Model_DataMapper')) {
            throw new Zend_Auth_Adapter_Exception(
                'Cannot authenticate: no data mapper'
            );
        }

        $user = $this->_mapper->findUserByUsername($this->_username);
        if (! is_a($user, 'Model_User')) {
            return new Zend_Auth_Result(
                Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND,
                $this->_username
            );
        }

        if (!self::testPassword($this->_password, $user->password)) {
            return new Zend_Auth_Result(
                Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,
                $this->_username
            );
        }

        return new Zend_Auth_Result(
            Zend_Auth_Result::SUCCESS,
            $user
        );
    }

    public static function hashPassword($password)
    {
        $salt = '';
        for ($i = 0; $i < 4; $i++) {
            $salt .= bin2hex(chr(rand(0, 256)));
        }
        $hash = sha1($password . $salt) . $salt;
        return $hash;
    }

    public static function testPassword($password, $saltedHash)
    {
        $hash = substr($saltedHash, 0, 40);
        $salt = substr($saltedHash, 40);
        $test = sha1($password . $salt);
        return $test == $hash;
    }

}
