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
 * @property-read Taskr_Model_User $user owner of this task
 *      (each task must have one user)
 * @property Taskr_Model_Project $project project, to which this task belongs
 *      (each task must have zero or one projects)
 * @property string $title must not be NULL
 * @property string $scrap
 * @property-read string scrapLine
 *      for indication in task lists
 * @property int $liveline if set, must be an Unix timestamp
 * @property int $deadline if set, must be an Unix timestamp
 * @property int $added Unix timestamp of when the task was added
 * @property int $lastStarted Unix timestamp
 * @property int $lastStopped Unix timestamp
 * @property-read bool $finished
 * @property-read bool $archived
 * @property int $duration in seconds
 *
 * @property int $id internal database key
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
     * @ignore (internal)
     */
    protected $_scrapChanged;           // necessary for avoiding senseless db traffic
    
    /**
     * Initiate a Task instance
     */
    public function __construct(array $magic = NULL)
    {
        if (array_key_exists('finished', $magic)) {     // Workaround for initializing
            $this->_magicFinished = $magic['finished']; // the read-only property
            unset($magic['finished']);
        }
        if (array_key_exists('archived', $magic)) {
            $this->_magicArchived = $magic['archived'];
            unset($magic['archived']);
        }
        parent::__construct( $magic );
        $this->_scrapChanged = FALSE;
    }

    /**
     * Check if the task is running
     * @return bool
     */
    public function isActive()
    {
        return ($this->lastStarted > $this->lastStopped);
    }
    
    /**
     * If task is the selected one, then all scrap will be given, possibly queing
     * it from the database.
     * Otherwise, we give just a brief line of scrap for indication.
     */
    public function getScrap()
    {
        $ch = $this->_scrapChanged; $l = strlen($this->_magicScrap);

        if ( !$this->_scrapChanged
         &&  strlen($this->_magicScrap) == Taskr_Model_DataMapper::SHORT_SCRAP_LIMIT )
        {
            // load he scrap from database HERE
            $this->_magicScrap = Taskr_Model_DataMapper::getInstance()->readScrap($this);
            $this->_scrapChanged = FALSE;
        }
        return $this->_magicScrap;
    }
    
    /**
     * Mutator method for scrap property
     *
     * @param string $newContent
     */
    public function setScrap($newContent)
    {
        if ( $newContent != $this->_magicScrap ) {
            $this->_magicScrap = $newContent;
            $this->_scrapChanged = TRUE;
        }
    }
    
    /**
     * @property-read string scrapLine
     */
    public function getScrapLine()
    {
        return $this->makeScrapLine();
    }

    /**
     * @ignore
     * @throw Exception Trying to set a read-only property
     */
    public function setFinished()
    {
        throw new Exception('Trying to set a read-only property');
    }
    
    /**
     * @ignore
     * @throw Exception Trying to set a read-only property
     */
    public function setArchived()
    {
        throw new Exception('Trying to set a read-only property');
    }
    
    /**
     * Tells the mapper to start the task
     */
    public function start()
    {
        Taskr_Model_DataMapper::getInstance()->startTask($this->id);
    }

    /**
     * Tells the mapper to stop the task
     */
    public function stop()
    {
        Taskr_Model_DataMapper::getInstance()->stopTask($this);
    }

    /**
     * Finish (and by default - archive) the task.
     */
    public function finish($archive = TRUE)
    {
        if ($this->project) {
            $this->project->finish($this);
        }
        $this->_magicFinished = TRUE;
        if ( $archive ) { $this->_magicArchived = TRUE; }
        
        $this->stop();
    }

    /**
     * Tells the mapper to archive/unarchive the task
     *
     * @param bool $archive OPTIONAL unarchives the task if set to FALSE
     * @return Taskr_Model_Task 
     */
    public function archive($archive = TRUE)
    {
        $this->_magicArchived = (bool)$archive;
        return $this->save();
    }

    /**
     * Tells the mapper to save the task
     * @return Taskr_Model_Task
     */
    public function save()
    {
        Taskr_Model_DataMapper::getInstance()->saveTask($this, $this->_scrapChanged);
        $this->_scrapChanged = FALSE;
        return $this;
    }
    
    /*
     * Show auxiliary scrap indication without control symbols.
     *
     * Useful for brief indication e.g. in task list.
     * Parameter $outlen specifies overall width, including title and $separator.
     * If $separator is empty string, then $outlen limits scrap indication only.
     * In development mode shows task id before the scrap!
     * 
     * @param int $outlen maximub lenght of string to be composed
     * @param string $separator between the task title and scrap part
     * @return indication string starting with $separator
     */
    public function makeScrapLine($outlen = 80, $separator = ' - ')
    {
        $scrap = $result = ''; 
        
        if( APPLICATION_ENV == 'development' ) {
            $scrap = "[{$this->id}] ";
        }
        $scrap = preg_replace('/( )+/', ' ', $scrap . $this->_magicScrap);
        
        if ( ($txtlen = strlen($scrap)) > 0 ) {     // we have anything to show and...
            if ( $outlen >= 0 ) {
                $outlen -= strlen($this->title) + strlen($separator);
            } else {
                $separator = ''; $outlen = abs($outlen);
            }
            if ( $outlen > 0 ) {                    // ... and we have any room left
                $result = $separator . substr($scrap, 0, $outlen);
            }
        }
        return $result;
    }
    
}
