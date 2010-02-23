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
class Taskr_Model_Task extends My_RmoAbstract
{
    /**
     * Constants used to define status scheme.
     *
     * Status scheme defines object behavior and in which context it can be used.
     * Object appearance may be defined by different scheme.
     *
     * When these values are changed, data repository (mapper) should be modified accordingly.   
     * For the sake of debugging, these values are human readable right now.
     */
    const ILLEGAL  = '*BAD*';
    const NORMAL   = 'normal';
    const FUTURE   = 'future';
    const TODAY    = 'today';
    const OVERDUE  = 'overdue';
    const FINISHED = 'finished';
    const ARCHIVED = 'archived';
    const NAME     = 'task';
    
    /**
     * Properties manipulated by MagicAbstract base class.
     * @ignore
     */
    protected $_magicId;
    protected $_magicUserId;
    protected $_magicProjectId;
    protected $_magicLiveline;
    protected $_magicDeadline;
    protected $_magicLastStarted;
    protected $_magicLastStopped;
    protected $_magicFinished;
    protected $_magicArchived;
    protected $_magicDuration;
    protected $_magicTitle;
    protected $_magicScrap;
    protected $_magicAdded;
    
    protected $_scrapChanged = FALSE;   // necessary for avoiding senseless db traffic


    public function __construct(array $magic = NULL)
    {
        parent::__construct( $magic );
        $this->_scrapChanged = FALSE;
    }

    /**
     * @see My_RmoInterface
     */
    public function getClass()
    {
        return self::NAME;
    }
    
    /**
     * NB: It has to be asserted that if liveline is set, it is always before deadline!
     * @see My_RmoInterface
     */
    public function getStatus()
    {
        assert( $this->id !== NULL );
        
        $res = NULL;
		if ($this->archived)                     { $res = self::ARCHIVED; }
		elseif ($this->finished)                 { $res = self::FINISHED; }
		elseif ($this->deadline) {
			if ($this->deadline <= time() )          { $res = self::OVERDUE; }
			elseif ($this->deadline <= time()+86400) { $res = self::TODAY; }
		}
		if (!$res) {
		    if ($this->liveline > time()+86400)  { $res = self::FUTURE; }
		    else                                 { $res = self::NORMAL; }
		}
		assert( $res !== NULL );
    	return $res;
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
     * Place yourself into data storage and connect to business model
     * @usedby TaskController::addAction()
     */
    public function checkIn()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, '$this->title');
        Taskr_Model_DataMapper::getInstance()->taskSave($this, TRUE);
        // $this->dispatch('SAVE');
        $this->_scrapChanged = FALSE;
        return $this;
    }

    /**
     * If task is the selected one, then all scrap will be given, possibly queing
     * it from the database.
     * Otherwise, we give just a brrief line of scrap for indication.
     */
    public function getScrap()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
        $ch = $this->_scrapChanged; $l = strlen($this->_magicScrap);
        My_Dbg::log("changed: {$ch}, len={$l}");
        if ( !$this->_scrapChanged
         &&  strlen($this->_magicScrap) == Taskr_Model_DataMapper::SHORT_SCRAP_LIMIT )
        {
            // load he scrap from database HERE
            $this->_magicScrap = Taskr_Model_DataMapper::getInstance()->scrapRead($this->id);
            $this->_scrapChanged = FALSE;
        }
        return $this->_magicScrap;
    }
    
    public function setScrap( $newContent )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
        if ( $newContent != $this->_magicScrap ) {
            $this->_magicScrap = $newContent;
            $this->_scrapChanged = TRUE;
        }
    }

    /**
     * Stop the task
     * @return not-NULL if the task was running
     */
    public function stop()
    {
        //My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
        if ( $this-> isActive() ) {
    	    return $this->duration += ($this->lastStopped = time()) - $this->lastStarted;
    	}
    }

    /**
     * Finish the task.
     */
    public function finish()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
    	if ($this->stop()) {
            return $this->finished = TRUE;
        }
    }

    /**
     * Tells the mapper to archive/unarchive the task
     *
     * @param bool $archive OPTIONAL unarchives the task if set to FALSE
     */
    public function archive($archive = TRUE)
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
        Taskr_Model_DataMapper::getInstance()->archiveTask($this, $archive);
        // $this->dispatch( 'ARHIVE' );
    }

    /**
     * Tells the mapper to save the task
     */
    public function save()
    {
        Taskr_Model_DataMapper::getInstance()->taskSave($this, $this->_scrapChanged);
        // $this->dispatch( 'SAVE' );
        $this->_scrapChanged = FALSE;
    }
    
    /**
     * @todo IMPL!
     */
    public function getProject()
    {
        return NULL;
    }
    
    /**
     * @todo IMPL!
     */
    public function setProject( $obj )
    {
    }
    

    /*
     * Show auxiliary scrap indication without control symbols.
     * Useful for brief indication e.g. in task list.
     * Parameter $outlen specifies overall width, including title and $separator.
     * If $separator is empty string, then $outlen limits scrap indication only.
     * @todo implement a permanent solution for UI task status indication
     * @usedby views/scripts/task/index.phtml
     */
    public function getScrapline($outlen = 80, $separator = ' - ')
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
    	$result = ''; $scrap = "(#{$this->id})";              //!debug $this->scrap;
    	//$scrap = $this->_magicScrap;
    	
    	if ( ($txtlen = strlen($scrap)) > 0  &&             // we have anything to show and...
    	     ($seplen = strlen($separator)) < $outlen ) {     // we have any room left
    	    if ( $seplen > 0 ) {
    	        $outlen -= $seplen + strlen($this->title);
    	    }
    	    if ( $outlen > 0 ) {
    	        $result = preg_replace('/( )+/', ' ', $scrap);
    	        $result = substr($result, 0, $outlen);   //!quickhack
    	        $result = $separator . $result;
    	    }
    	}
    	return $result;
    }
    
}
