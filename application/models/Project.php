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
 * @property bool $finished
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
    protected $_magicDuration;

    /**
     * Does nothing, as $duration is read-only property
     */
    public function setDuration()
    {
    }

    /**
     * Asks the mapper to calculate the project's overall duration
     */
    public function getDuration()
    {
        return Taskr_Model_DataMapper::getInstance()->projectDuration($this);
    }

}

