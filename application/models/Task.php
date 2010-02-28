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
    const ILLEGAL       = '*BAD*';
    const NORMAL        = 'normal';
    const FUTURE        = 'future';
    const TODAY         = 'today';
    const OVERDUE       = 'overdue';
    const FINISHED      = 'finished';
    const ARCHIVED      = 'archived';
    const FINISH_FLAG   = 8;
    const ARCHIVE_FLAG  = 16;
    const NAME          = 'task';

    /**
     * @ignore (magic property)
     */
    protected $_magicId;

    /**
     * @ignore (magic property)
     */
    protected $_magicUserId;

    /**
     * @ignore (magic property)
     */
    protected $_magicProjectId;

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
    protected $_magicLastStarted;

    /**
     * @ignore (magic property)
     */
    protected $_magicLastStopped;

    /**
     * @ignore (magic property)
     */
    protected $_magicFlags = 0;

    /**
     * @ignore (magic property)
     */
    protected $_magicDuration;

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
    protected $_magicAdded;
    
    /**
     * @ignore (internal)
     */
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
		if ($this->_magicFlags >= ARCHIVE_FLAG)     { $res = self::ARCHIVED; }
		elseif ($this->_magicFlags & FINISH_FLAG)   { $res = self::FINISHED; }
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
    public function isRunning()
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
        // My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
        $ch = $this->_scrapChanged; $l = strlen($this->_magicScrap);
        //My_Dbg::log("changed: {$ch}, len={$l}");
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
        //My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
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
        return $this->scrapLine();
    }

    /**
     * @property-read boolean finished
     */
    public function getFinished()
    {
        return ($this->_magicFlags & self::FINISH_FLAG) == self::FINISH_FLAG;
    }
    
    /**
     * @property-read boolean archived
     */
     public function getArchived()
    {
        return ($this->_magicFlags & self::ARCHIVE_FLAG) == self::ARCHIVE_FLAG;
    }
    
    /**
     * @property-read Taskr_Model_User user
     */
    public function getUser()
    {
        return Taskr_Model_DataMapper::getInstance()->findUserById( $this->_magicUserId );
    }
    
   
    /**
     * Tells the mapper to start the task
     */
    public function start()
    {
        Taskr_Model_DataMapper::getInstance()->startTask($this->id);
    }

    /**
     * Stop the task
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
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
        
        if ($this->project) {
            $this->project->finish($this);
        }
        $flags = self::FINISH_FLAG;
        if ( $archive ) { $flags |= self::ARCHIVE_FLAG; }
        
        $this->_magicFlags |= $flags;
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
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
        $stat = $this->_magicFlags & ~self::ARCHIVE_FLAG;
        $this->_magicFlags = $stat | ($archive ? self::ARCHIVE_FLAG : 0);
        return $this->save();
    }

    /**
     * Tells the mapper to save the task
     * @return Taskr_Model_Task
     */
    public function save()
    {
        Taskr_Model_DataMapper::getInstance()->saveTask($this, $this->_scrapChanged);
        // $this->dispatch( 'SAVE' );
        $this->_scrapChanged = FALSE;
        return $this;
    }
    
    /**
     * Get project instance
     */
    public function getProject()
    {
        if ( $pid = $this->_magicProjectId ) {
            return Taskr_Model_DataMapper::getInstance()->findProject( $pid );
        }
        return NULL;
    }
    
    /**
     * Set project
     */
    public function setProject( $project )
    {
        $this->_magicProjectId = $project ? $project->id : NULL;
    }
    

    /*
     * Show auxiliary scrap indication without control symbols.
     * Useful for brief indication e.g. in task list.
     * Parameter $outlen specifies overall width, including title and $separator.
     * If $separator is empty string, then $outlen limits scrap indication only.
     * In development mode shows task id before the scrap!
     * @todo implement a permanent solution for UI task status indication
     * @usedby views/scripts/task/index.phtml
     */
    public function scrapLine($outlen = 80, $separator = ' - ')
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $this->id);
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
