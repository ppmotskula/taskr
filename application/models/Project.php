<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 *
 */
/**
 * Model class: Project
 *
 * @property int $id primary key (autoincrement)
 * @property Taskr_Model_User $user owner of this project
 *      (each project must have one user)
 * @property string $title must not be NULL
 * @property int $finished
 * Unix timestamp of when the last task in this project was finished
 * @property-read int $duration in seconds
 */
class Taskr_Model_Project extends My_MagicAbstract
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
    protected $_magicTitle;

    /**
     * @ignore (magic property)
     */
    protected $_magicFinished;

    /**
     * @ignore (magic property)
     */
    protected $_magicAdded;

    /**
     * @ignore (magic property)
     */
    protected $_magicDuration;

    /**
     * @ignore
     * @throw Exception Trying to set a read-only property
     */
    public function setDuration()
    {
        throw new Exception('Trying to set a read-only property');
    }

    /**
     * Asks the mapper to calculate the project's overall duration
     */
    public function getDuration()
    {
        return Taskr_Model_DataMapper::getInstance()->projectDuration($this);
    }
    
    /**
     * Asks the mapper to finish the project
     *
     * Returns TRUE if $task was the project's last unfinished task or
     * FALSE if not.
     *
     * @param Taskr_Model_Task $task
     * @return bool
     * @throw Exception if $task did not belong to a project or if the
     * project was already finished.
     */
    public function finish(Taskr_Model_Task $task)
    {
        try {
            return Taskr_Model_DataMapper::getInstance()->
                    finishProject($task);
        } catch(Exception $e) {
            throw $e;
        }
    }

}

