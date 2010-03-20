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
 * @property int $liveline if set, must be an Unix timestamp
 * @property int $deadline if set, must be an Unix timestamp
 * @property int $added Unix timestamp of when the task was added
 * @property int $lastStarted Unix timestamp
 * @property int $lastStopped Unix timestamp
 * @property bool $finished
 * @property bool $archived
 * @property int $duration in seconds
 *
 * @property int $id internal database key
 * @property int $projectId internal database key
 * @property int $flags internal status 
 */
class Taskr_Model_Task extends My_MagicAbstract
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

    const FINISH_FLAG   = 8;            // used in _magicFlags
    const ARCHIVE_FLAG  = 16;           // used in _magicFlags
    
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
     * @ignore (internal property)
     * Accessed by getFinished(), getArchived()
     */
    protected $_magicFlags = 0;

    /**
     * @ignore (magic property)
     */
    protected $_magicDuration;

    /**
     * @ignore (internal)
     */
    protected $_scrapChanged = FALSE;   // necessary for avoiding senseless db traffic
    
    /**
     * @ignore (internal)
     */
    protected $_user;                   // user instance so we won't access db redundantly


    public function __construct(array $magic = NULL)
    {
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
            $this->_magicScrap = Taskr_Model_DataMapper::getInstance()->scrapRead($this->id);
            $this->_scrapChanged = FALSE;
        }
        return $this->_magicScrap;
    }
    
    public function setScrap( $newContent )
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
        if ( !$this->_user ) {
            $this->_user = Taskr_Model_DataMapper::getInstance()->
               findUserById( $this->_magicUserId );
        }
        return $this->_user;
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
