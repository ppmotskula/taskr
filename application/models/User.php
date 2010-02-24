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
class Taskr_Model_User extends My_RmoAbstract
{
    const NAME = 'user';
    
    /**
     * Properties manipulated by MagicAbstract base class.
     * @ignore
     */
    protected $_magicId;
    protected $_magicUsername;
    protected $_magicPassword;
    protected $_magicEmail;
    protected $_magicEmailTmp;
    protected $_magicTzDiff;
    protected $_magicProUntil;
    protected $_magicCredits;
    protected $_magicAdded;
    
    /**
     * active task object
     * @todo check logics ... false etc.
     */
    protected $_activeTask;
    
    /*
     * RmoManager
     * @todo check out why we'll get the session manager error if using $_rmo
     */
    // protected $_rmo;
    protected static $_rmoManager;
    
    protected static $_sessionUser;


    /**
     * @see My_RmoInterface
     */
    public function getClass()
    {
        return self::NAME;
    }
    
   /**
    * Initiate working context for user. Called by Controller init()
    *
    * This method should be called for authenticated user only. 
    * @usedby TaskController.init()
    */
    public function initContext()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        
    	if ( self::$_sessionUser && $this !== self::$_sessionUser ) {
    	    throw new Zend_Exception('Extra call to User::initContext()');
    	}
    	self::$_sessionUser = $this;
    	
    	if ( !$this->rmoM() ) { self::_initRmoManager(); }
    	
    	$this->_setDispatcher( Taskr_Model_DataMapper::getInstance() );
    	$this->_dispatch( 'connection' );
    	
     	// return Taskr_Model_DataMapper::getInstance()
     	return $this->getDispatcher()
     	           ->initUserConnection(intval($this->id));
     	// return $this->dispatch( 'connection' );
    }
    
    public function rmoM()
    {
        return self::$_rmoManager;
    }
    
    protected static function _initRmoManager()
    {
        self::$_rmoManager = new My_RmoManager( array(
            'tasks.live' => array( 'task.live' ),
            'tasks.active' => array( 
                           'task.overdue', 'task.today', 'task.active', 'task.future' ),
            'tasks.finished' => array( 'task.finished' ),
            'tasks.archived' => array( 'task.archived' ),
             ) );
    }
    
   /**
    * Retrieves user object by name
    *
    * @usedby AccountController.signupAction()
    * @usedby Taskr_Auth_Adapter_Password.authenticate()
    * @return Taskr_Model_User
    * @todo static self pointer may be questionable here
    */
    public static function getByUsername($username)
    {
    	if ( !($user = self::$_sessionUser) || $user->userName != $username ) {
    	    if ( !self::$_rmoManager ) {
    	        self::_initRmoManager();
    	    }
    	    $user = Taskr_Model_DataMapper::getInstance()->findUserByUsername( $username );
    	    // $user = self::dispatchMsg( self::NAME, array( 'GET', $username ) );
     	}
     	return $user;
    }

   /**
    * Saves user info
    */
    public function save()
    {
        return Taskr_Model_DataMapper::getInstance()->userSave( $this );
        // return $this->dispatch( 'SAVE' );
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
        //Taskr_Model_DataMapper::getInstance()->saveTask($task);
        return $task->checkIn();
    }

   /**
    * Start specified task
    *
    * If there is other task running, it will be stopped.
    * Data storage will be synchronized. This is the only entry for UI.
    * @usedby TaskController
    *
    * @param int task index or NULL
    */
    public function activateTaskById( $id )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        
        $task = $this->_activeTask;
        $this->_activeTask = NULL;                // invalidate, so it will be loaded again

        if ( $id ) {
            Taskr_Model_DataMapper::getInstance()->taskStart($id);
            // self::dispatchMsg( 'task' ,'START', intval($id) );
        } elseif( $task ) {
            Taskr_Model_DataMapper::getInstance()->taskStop($task);
            // $task->stop();
        }
    }
	
   /**
    * Retrieves the user's active task from the mapper
    * @usedby TaskController
    *
    * @return Taskr_Model_Task
    */
    public function getActiveTask()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        
    	if ( !$this->_activeTask ) {
            $this->_activeTask = 
                Taskr_Model_DataMapper::getInstance()->loadActiveTask();
    	    // self::dispatchMsg( 'task' ,'GET' ) );
        }
        return $this->_activeTask;
    }

    /**
     * Retrieves the user's upcoming tasks from the mapper
     *
     * @return array of Taskr_Model_Task
     */
    public function getUpcomingTasks()
    {
        return $this->rmoM()->loadList( 'tasks.active' );
        // return $this->dispatch( 'load', 'tasks.active' );
    }

    /**
     * Retrieves the user's finished tasks from the mapper
     *
     * @return array of Taskr_Model_Task
     */
    public function getFinishedTasks()
    {
        return $this->rmoM()->loadList( 'tasks.finished' );
        // return $this->dispatch( 'load', 'tasks.finished' );
    }

    /**
     * Retrieves the user's archived tasks from the mapper
     *
     * @param int $fromDate Unix timestamp of oldest task to retrieve
     * @return array of Taskr_Model_Task
     */
    public function getArchivedTasks($fromDate)
    {
        return $this->rmoM()->loadList( 'tasks.archived' );
    }

    /**
     * Archives finished tasks
     *
     * @return int number of tasks archived
     */
    public function archiveFinishedTasks()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        Taskr_Model_DataMapper::getInstance()->tasksArchive( $this->id );
    }

    /**
     * Retrieves the user's active task from the mapper
     * @todo IMPLEMENT!
     * @return Taskr_Model_Task
     */
    public function getActiveProject()
    {
        return NULL; //Taskr_Model_DataMapper::getInstance()->activeProject($this);
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
            $project->user = $this;
            Taskr_Model_DataMapper::getInstance()->saveProject($project);
            return $project;
        } else {
            return NULL;
        }
    }

    /**
     * @todo IMPLEMENT!
     */
    public function getProjects()
    {
        return NULL;
    }
    
    /**
     * @todo IMPLEMENT!
     */
    public function getArchivedProjects()
    {
        return NULL;
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

