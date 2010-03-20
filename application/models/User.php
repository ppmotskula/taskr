<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 *
 */
/**
 * Model class: User
 *
 * @property int $id primary key (autoincrement)
 * @property string $username must not be NULL
 * @property string $password must not be NULL;
 *      contains salted and hashed password
 * @property string $email email address
 * @property int $tzDiff difference of user's time from UTC in seconds
 * @property int $added Unix timestamp of when user was created
 * @property int $proUntil Unix timestamp of when user's "Pro" status expires
 * @property int $credits number of referral credits user has
 */
class Taskr_Model_User extends My_MagicAbstract
{
    /**
     * @ignore (magic property)
     */
    protected $_magicId;

    /**
     * @ignore (magic property)
     */
    protected $_magicUsername;

    /**
     * @ignore (magic property)
     */
    protected $_magicPassword;

    /**
     * @ignore (magic property)
     */
    protected $_magicEmail;

    /**
     * @ignore (magic property)
     */
    protected $_magicEmailTmp;

    /**
     * @ignore (magic property)
     */
    protected $_magicTzDiff;

    /**
     * @ignore (magic property)
     */
    protected $_magicProUntil;

    /**
     * @ignore (magic property)
     */
    protected $_magicCredits;

    /**
     * @ignore (magic property)
     */
    protected $_magicAdded;
    

   /**
    * Saves user info
    */
    public function save()
    {
        return Taskr_Model_DataMapper::getInstance()->saveUser( $this );
    }
     
    /**
     * Adds a new task
     *
     * @param string|array|Taskr_Model_Task $task
     * @return Taskr_Model_Task
     * @throws Exception if $task is neither an instance of
     * Taskr_Model_Task nor an array or string suitable for creating one
     */
    public function addTask($task)
    {
        if (is_string($task)) {
            // string to array
            $task = array('title' => $task);
        }

        if (is_array($task)) {
            // array to Taskr_Model_Task
            $task = new Taskr_Model_Task($task);
        }

        if (!is_a($task, 'Taskr_Model_Task') || !isset($task->title)) {
            throw new Exception('Invalid task');
        }

        $task->userId = $this->id;

        return $task->save();
    }

   /**
    * Retrieves the user's active task from the mapper
    * @usedby TaskController
    *
    * @return Taskr_Model_Task
    */
    public function getActiveTask()
    {
        return Taskr_Model_DataMapper::getInstance()->activeTask();
    }

    /**
     * Retrieves the user's upcoming tasks from the mapper
     *
     * @return array of Taskr_Model_Task
     */
    public function upcomingTasks()
    {
        return Taskr_Model_DataMapper::getInstance()->loadList( 'tasks.active' );
    }

    /**
     * Retrieves the user's finished tasks from the mapper
     *
     * @return array of Taskr_Model_Task
     */
    public function finishedTasks()
    {
        return Taskr_Model_DataMapper::getInstance()->loadList( 'tasks.finished' );
    }

    /**
     * Retrieves the user's archived tasks from the mapper
     *
     * @param int $fromDate Unix timestamp of oldest task to retrieve
     * @return array of Taskr_Model_Task
     */
    public function archivedTasks($fromTs = NULL, $toTs = NULL)
    {
        return Taskr_Model_DataMapper::getInstance()->archivedTasks($this, $fromTs, $toTs);
    }

    /**
     * Archives finished tasks
     *
     * @return int number of tasks archived
     */
    public function archiveFinishedTasks()
    {
        Taskr_Model_DataMapper::getInstance()->tasksArchive( $this->id );
    }

    /**
     * Retrieves the user's active task from the mapper
     * @todo IMPLEMENT!
     * @return Taskr_Model_Task
     */
    public function activeProject()
    {
        return Taskr_Model_DataMapper::getInstance()->activeProject($this);
    }
    
    /**
     * Adds a new project
     * @todo IMPL
     * @param string|array|Taskr_Model_Project $project
     * @return Taskr_Model_Project or NULL if the user isn't allowed to add
     * new projects
     * @throws Exception if $project is neither an instance of
     * Taskr_Model_Project nor an array or string suitable for creating one
     */
    public function addProject($project)
    {
        if (is_string($project)) {
            // string to array
            $project = array('title' => $project);
        }

        if (is_array($project)) {
            // array to Taskr_Model_Project
            $project = new Taskr_Model_Project($project);
        }

        if (!is_a($project, 'Taskr_Model_Project') || !isset($project->title)) {
            // bail out -- not a project
            throw new Exception('Invalid project');
        }

        if ($this->isPro() || !count($this->unfinishedProjects())) {
            // user is Pro or has no unfinished projects, go ahead
            $project->userId = $this->id;
            Taskr_Model_DataMapper::getInstance()->saveProject($project);
            return $project;
        } else {
            return NULL;
        }
    }

    /**
     * Retrieves the user's unfinished projects from the mapper
     *
     * @return array of Taskr_Model_Project
     */
    public function unfinishedProjects()
    {
        return Taskr_Model_DataMapper::getInstance()->unfinishedProjects($this);
    }

    /**
     * Retrieves the user's archived projects from the mapper
     *
     * @param int $fromTs Unix timestamp of oldest task to retrieve
     * @return array of Taskr_Model_Task
     */
    public function archivedProjects($fromTs = NULL, $toTs = NULL)
    {
        return Taskr_Model_DataMapper::getInstance()->
                archivedProjects($this, $fromTs, $toTs);
    }

    /**
     * Check the user's Pro status
     *
     * @return bool TRUE if the user currently has a Pro status, FALSE if not
     */
    public function isPro()
    {
        return $this->proUntil > time();
    }
}



