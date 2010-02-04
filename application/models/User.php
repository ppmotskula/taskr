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
    protected $_magicTzDiff;

    /**
     * @ignore (magic property)
     */
    protected $_magicAdded;

    /**
     * @ignore (magic property)
     */
    protected $_magicProUntil;

    /**
     * @ignore (magic property)
     */
    protected $_magicCredits;

    /**
     * Retrieves the user's active task from the mapper
     *
     * @return Taskr_Model_Task
     */
    public function activeTask()
    {
        return Taskr_Model_DataMapper::getInstance()->activeTask($this);
    }

    /**
     * Retrieves the user's upcoming tasks from the mapper
     *
     * @return array of Taskr_Model_Task
     */
    public function upcomingTasks()
    {
        return Taskr_Model_DataMapper::getInstance()->upcomingTasks($this);
    }

    /**
     * Retrieves the user's finished tasks from the mapper
     *
     * @return array of Taskr_Model_Task
     */
    public function finishedTasks()
    {
        return Taskr_Model_DataMapper::getInstance()->finishedTasks($this);
    }

    /**
     * Retrieves the user's archived tasks from the mapper
     *
     * @param int $fromDate Unix timestamp of oldest task to retrieve
     * @return array of Taskr_Model_Task
     */
    public function archivedTasks($fromDate)
    {
        return Taskr_Model_DataMapper::getInstance()->archivedTasks($this, $fromDate);
    }

    /**
     * Adds a new task
     *
     * @param array|Taskr_Model_Task $task
     * @return Taskr_Model_Task
     */
    public function addTask(Taskr_Model_Task $task)
    {
        if (is_array($task)) {
            $task = new Taskr_Model_Task($task);
        }
        if (!is_a($task, 'Taskr_Model_Task')) {
            throw new Exception('Invalid task');
        }
        $task->user = $this;
        return $task;
    }

    /**
     * Starts a task, stopping the currently active task first if there is one
     *
     * @param int $id
     * @return Taskr_Model_Task
     */
    public function startTask($id)
    {
        if (NULL != ($task = $this->activeTask($this))) {
            $task->stop();
        }

        if (NULL !=
            ($task = Taskr_Model_DataMapper::getInstance()->findTask($id))
        ) {
            if ($task->user->id == $this->id) {
                $task->start();
                return $task;
            }
        }

        throw new Exception(
            "Task $id is not found or does not belong to user {$this->username}"
        );
    }

    /**
     * Archives finished tasks
     *
     * @return int number of tasks archived
     */
    public function archiveFinishedTasks()
    {
        $tasks = $this->finishedTasks();
        if (0 < ($result = count($tasks))) {
            foreach ($tasks as $task) {
                $task->archive();
            }
        }
        return $result;
    }

}

