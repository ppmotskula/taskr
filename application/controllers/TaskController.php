<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 */
/**
 * Task controller
 */
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
        $this->view->user = self::$_user;
    }

    /**
     * Shows or processes the edit form
     */
    public function editAction()
    {
        // bail out if current user has no active task
        if (!$task = self::$_user->activeTask()) {
            $this->_redirector->gotoSimple('index', 'task');
        }

        // handle POST requests
        $request = $this->getRequest();
        if ($request->isPost()) {
            $formData = $request->getPost();

            // protect scraps from HTML injection
            if (isset($formData['scrap'])) {
                $formData['scrap'] = strtr($formData['scrap'], array(
                    '<' => '&lt;',
                    '>' => '&gt;',
                    '&' => '&amp;',
                ));
            }

            // choose action according to submit button label
            switch ($formData['submit']) {
                case 'save':
                    $task->scrap = $formData['scrap'];
                    if ($formData['finish']) {
                        $task->finish();
                    } else {
                        self::$_mapper->saveTask($task);
                    }
                    self::$_redirector->gotoSimple('index', 'task');
                case 'cancel':
                    self::$_redirector->gotoSimple('index', 'task');
                case 'stop':
                    $task->stop();
                    self::$_redirector->gotoSimple('index', 'task');
                // ignore any other buttons including 'edit'
            }
        }

        // display the edit form
        $this->view->task = $task;
    }

    /**
     * Adds a new task
     */
    public function addAction()
    {
        $request = $this->getRequest();

        if ($request->isPost()) {
            $taskText = trim($request->getPost('task-text'));
            $taskText = strtr($taskText, array(
                '<' => '&lt;',
                '>' => '&gt;',
                '&' => '&amp;',
            ));

            if ($taskText) {
                $task = new Taskr_Model_Task(array(
                    'user' => self::$_user,
                ));

                if (strpos($taskText, "\n")) {
                    $task->title = substr($taskText, 0, strpos($taskText, "\n"));
                    $task->scrap = preg_replace("/^.*?\n\s*/", '', $taskText);
                } else {
                    $task->title = $taskText;
                }

                self::$_mapper->saveTask($task);
            }
        }

        self::$_redirector->gotoSimple('index', 'task');
    }

    /**
     * Starts a task
     */
    public function startAction()
    {
        if (
            ($taskId = $this->_getParam('id', 0))
            && ($task = self::$_mapper->findTask($taskId))
            && (self::$_user->id == $task->user->id)
        ) {
            $task->start();
        }

        self::$_redirector->gotoSimple('index', 'task');
    }

    /**
     * Hides (archives) finished tasks
     */
    public function hideAction()
    {
        $tasks = self::$_user->finishedTasks();

        foreach ($tasks as $task) {
            $task->archive();
        }

        self::$_redirector->gotoSimple('index', 'task');
    }


}









