<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 */
/**
 * Archive controller
 */
class ArchiveController extends Zend_Controller_Action
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

        // bail out if nobody is logged in
        if (Zend_Auth::getInstance()->hasIdentity()) {
            self::$_user = Zend_Auth::getInstance()->getIdentity();
        	self::$_mapper = Taskr_Model_DataMapper::getInstance();
            self::$_mapper->initContext(self::$_user);
        } else {
            self::$_redirector->gotoSimple('index', 'index');
        }

        self::$_mapper = Taskr_Model_DataMapper::getInstance();

        // initialise timer if current user has an active task
        if ($task = self::$_user->activeTask()) {
            $this->view->headScript()
                ->appendFile($this->view->baseUrl() . '/js/timer.js')
            ;
            $this->view->onLoad = 'timer(' . (
                $task->duration + time() - $task->lastStarted
            ) . ', 0)';
        }
    }

    /**
     * Shows the task list
     */
    public function indexAction()
    {
        $this->view->taskId = $this->_getParam('id');
        $this->view->date = $this->_getParam('date')
            ? $this->_getParam('date')
            : Taskr_Util::tsToDate(time(), self::$_user->tzDiff);
        $this->view->user = self::$_user;
    }

}

