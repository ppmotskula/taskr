<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @copyright Copyright © 2010 Villem Alango & Peeter P. Mõtsküla
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 *
 */

/**
 * Task controller
 *
 */
class TaskController extends Zend_Controller_Action
{

    /**
     * @ignore (internal)
     * @var Taskr_Model_User
     *
     */
    protected static $_user = null;

    /**
     * @ignore (internal)
     * @var Taskr_Model_DataMapper
     *
     */
    protected static $_mapper = null;

    /**
     * @ignore (internal)
     * @var Zend_Controller_Action_Helper_Redirector
     *
     */
    protected static $_redirector = null;

    /**
     * Initializes the controller
     *
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
     *
     */
    public function indexAction()
    {
        $this->view->user = self::$_user;
    }

    /**
     * Shows or processes the edit form
     *
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
                case 'cancel':
                    self::$_redirector->gotoSimple('index', 'task');
                case 'stop':
                    $task->stop();
                    self::$_redirector->gotoSimple('index', 'task');
                case 'finish':
                    self::$_redirector->gotoSimple('finish', 'task');
                case 'save':
                    $task->scrap = $formData['scrap'];

                    if ($formData['project']) {
                        // new project creation requested
                        if (!$formData['finish']) {
                            // there is no point to add project when finishing a task
                            if (!$project =
                                self::$_user->addProject($formData['project'])
                            ) {
                                // couldn't create project, show error message
                                $formErrors['project'] =
                                    'To create new projects, you must either ' .
                                    'finish an existing project or ' .
                                    'sign up for Taskr Pro.';
                            }
                        }
                    } elseif ($formData['projects']) {
                        // existing project selected
                        $project =
                            self::$_mapper->findProject($formData['projects']);
                    } else {
                        // (no project) selected
                        $project = NULL;
                    }

                    if ($task->project && $task->project != $project) {
                        // task belonged to a project and
                        // the task's project is being changed so
                        // check if the old project must be finished
                        // @todo: check this branch!
                        $task->project->finish($task);
                    }

                    $task->project = $project;

                    $liveline = Taskr_Util::dateToTs($formData['liveline'],
                        self::$_user->tzDiff);
                    if (FALSE === $liveline) {
                        $formErrors['liveline'] =
                            'Liveline: invalid date entered';
                    } else {
                        $task->liveline = $liveline;
                    }

                    $deadline = Taskr_Util::dateToTs($formData['deadline'],
                        self::$_user->tzDiff);
                    if (FALSE === $deadline) {
                        $formErrors['deadline'] =
                            'Deadline: invalid date entered';
                    } else {
                        $task->deadline = $deadline;
                    }

                    if ($task->deadline > 0
                            && $task->deadline < $task->liveline) {
                        $formErrors['deadline'] =
                            'Deadline cannot be before liveline';
                    }

                    // show the form again if errors found
                    if (isset($formErrors)) {
                        break;
                    }

                    $task->save();
                    self::$_redirector->gotoSimple('index', 'task');
                // ignore any other buttons including 'edit'
            }
        }

        // set view parameters and show edit form
        $this->view->user = self::$_user;
        $this->view->task = $task;
        $this->view->formData = $formData;
        if (isset($formErrors)) {
            $this->view->formErrors = $formErrors;
        }
    }

    /**
     * Adds a new task
     *
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

                // also create scrap if multi-line entry
                if (strpos($taskText, "\n")) {
                    $task->title = substr($taskText, 0, strpos($taskText, "\n"));
                    $task->scrap = preg_replace("/^.*?\n\s*/", '', $taskText);
                } else {
                    $task->title = $taskText;
                }

                // liveline & deadline detection
                $dateChars = '[-0-9janfebmrpyulgsoctnvd]';
                $matches = array();
                if (preg_match(
                        "/^(?:(.+) +)?($dateChars*):($dateChars*)(?: +(.+))?$/i",
                        $task->title, $matches)) {
                    $title = trim($matches[1] . ' ' . $matches[4]);
                    $liveline = Taskr_Util::dateToTs($matches[2],
                        self::$_user->tzDiff);
                    $deadline = Taskr_Util::dateToTs($matches[3],
                        self::$_user->tzDiff);
                }

                if (isset($title)
                    && (FALSE !== $liveline)
                    && (FALSE !== $deadline)
                ) {
                    // liveline and/or deadline found
                    $task->title = $title;
                    $task->liveline = $liveline;
                    $task->deadline = $deadline;
                }

                // project detection
                $matches = array();
                if (preg_match('/^(?:(.+) +)?(#[^# ]*)(?: +(.+))?$/',
                        $task->title, $matches)) {
                    // #NULL or #projectName found in new task's title
                    $title = trim($matches[1] . ' ' . $matches[3]);
                    $projectName = substr($matches[2], 1);
                } elseif ($activeProject = self::$_user->activeProject()) {
                    // no # references found in title, use current user's
                    // active project if any
                    $title = $task->title;
                    $projectName = $activeProject->title;
                }

                // project assignment
                if (isset($projectName)) {
                    $task->title = $title;
                    // try to find an existing unfinished project first
                    foreach (self::$_user->unfinishedProjects() as $project) {
                        if ($projectName == $project->title) {
                            $task->project = $project;
                            break;
                        }
                    }
                    if (!isset($task->project)) {
                        // didn't find a suitable project, try to create one
                        if ($project = self::$_user->addProject(array(
                                'title' => $projectName))) {
                            $task->project = $project;
                        } else {
                            // failed to create new project for the user,
                            // add notice to the head of scrap
                            $task->scrap =
                                "To create new project '$projectName' " .
                                'you must either finish another project or ' .
                                "sign up for Taskr Pro.\n\n";
                        }
                    }
                }

                // try to add the new task
                try {
                    self::$_user->addTask($task);
                } catch(Exception $e) {
                    throw $e;
                    // just ignore the exception -- task couldn't be created
                }
            }
        }

        self::$_redirector->gotoSimple('index', 'task');
    }

    /**
     * Starts a task
     *
     */
    public function startAction()
    {
        if ($taskId = $this->_getParam('id', 0)) {
            Taskr_Model_DataMapper::getInstance()->startTask($taskId);
        }

        self::$_redirector->gotoSimple('index', 'task');
    }

    /**
     * Hides (archives) finished tasks
     *
     */
    public function hideAction()
    {
        self::$_user->archiveFinishedTasks();

        self::$_redirector->gotoSimple('index', 'task');
    }

    /**
     * Shows or processes the finish form
     *
     */
    public function finishAction()
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
                case 'cancel':
                    self::$_redirector->gotoSimple('index', 'task');
                case 'save':
                    $task->scrap = $formData['scrap'];
                    $task->finish();
                    self::$_redirector->gotoSimple('index', 'task');
                // ignore any other buttons including 'finish'
            }
        }

        // set view parameters and show edit form
        $this->view->user = self::$_user;
        $this->view->task = $task;
        $this->view->formData = $formData;
    }

}
