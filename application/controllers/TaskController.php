<?php

class TaskController extends Zend_Controller_Action
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
     * Initializes the controller
     */
    public function init()
    {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            self::$_user = Zend_Auth::getInstance()->getIdentity();
        }
        self::$_mapper = Taskr_Model_DataMapper::getInstance();
    }

    public function indexAction()
    {
        // action body
    }

    public function editAction()
    {
        // action body
    }

    public function addAction()
    {
        // action body
    }

    public function startAction()
    {
        // action body
    }

    public function hideAction()
    {
        // action body
    }


}









