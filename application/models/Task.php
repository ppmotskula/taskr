<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 *
 */
/**
 * Model class: Task
 *
 * @property int $id primary key (autoincrement)
 * @property Taskr_Model_User $user owner of this task
 *      (each task must have one user)
 * @property Taskr_Model_Project $project project, to which this task belongs
 *      (each task must have zero or one projects)
 * @property string $title must not be NULL
 * @property string $scrap
 * @property int $liveline if set, must be an Unix timestamp
 * @property int $deadline if set, must be an Unix timestamp
 * @property int $added Unix timestamp of when the task was added
 * @property int $lastStarted Unix timestamp
 * @property int $lastStopped Unix timestamp
 * @property bool $finished
 * @property bool $archived
 * @property int $duration in seconds
 */
class Taskr_Model_Task extends My_MagicAbstract
{
    /**
     * @ignore (magic property)
     */
    protected $_magicId;

    /**
     * @ignore (magic property)
     */
    protected $_magicUser;

    /**
     * @ignore (magic property)
     */
    protected $_magicProject;

    /**
     * @ignore (magic property)
     */
    protected $_magicTitle;

    /**
     * @ignore (magic property)
     */
    protected $_magicScrap;

    /**
     * @ignore (magic property)
     */
    protected $_magicLiveline;

    /**
     * @ignore (magic property)
     */
    protected $_magicDeadline;

    /**
     * @ignore (magic property)
     */
    protected $_magicAdded;

    /**
     * @ignore (magic property)
     */
    protected $_magicLastStarted;

    /**
     * @ignore (magic property)
     */
    protected $_magicLastStopped;

    /**
     * @ignore (magic property)
     */
    protected $_magicFinished;

    /**
     * @ignore (magic property)
     */
    protected $_magicArchived;

    /**
     * @ignore (magic property)
     */
    protected $_magicDuration;

    /**
     * Tells the mapper to start the task
     */
    public function start()
    {
        Taskr_Model_DataMapper::getInstance()->startTask($this);
    }

    /**
     * Tells the mapper to stop the task
     */
    public function stop()
    {
        Taskr_Model_DataMapper::getInstance()->stopTask($this);
    }

    /**
     * Tells the mapper to finish the task
     *
     * If the task is the last unfinished task in a project,
     * finish the project as well.
     */
    public function finish()
    {
        if ($this->project) {
            $this->project->finish($this);
        }
        Taskr_Model_DataMapper::getInstance()->finishTask($this);
    }

    /**
     * Tells the mapper to archive/unarchive the task
     *
     * @param bool $archive OPTIONAL unarchives the task if set to FALSE
     */
    public function archive($archive = TRUE)
    {
        Taskr_Model_DataMapper::getInstance()->archiveTask($this, $archive);
    }

}
