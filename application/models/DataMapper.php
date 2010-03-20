<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu> (initial version for sqlite database)
 * @author Villem Alango <valango@gmai.com>  (current version for MySQL database)
 * @todo copyright & license
 * @version 0.1.0
 *
 */
/**
 * Model class DataMapper
 *
 * DataMapper is the only class in Taskr that has knowledge about both
 * the business model and the database; it allows the business objects to
 * remain database-agnostic.
 *
 * In this sense, it can be seen as a Repository from the model side,
 * so it might be worthwhile to split it into two classes
 * (Repository and DataMapper) in future.
 *
 * Taskr_Model_DataMapper also implements the Singleton pattern
 */
class Taskr_Model_DataMapper
{
    const SHORT_SCRAP_LIMIT = 60;       // width of Tasks.scrap field

    /**
     * @ignore (internal)
     * var Zend_Db_Adapter_Abstract database to be used
     */
    protected static $_db;

    /**
     * @ignore (internal)
     * var Taskr_Model_DataMapper instance of the class
     */
    protected static $_instance;

    /*
     * @ignore (internal)
     * Dictionary of tasks lists types
     */
    protected static $_sessionUser; // @todo analyze the actual need for this property

    /*
     * @ignore (internal)
     * Dictionary of tasks lists types
     */
    protected static $_listsDictionary = array(
        'tasks.live'     => array('task.live'),
        'tasks.active'   => array('task.overdue', 'task.today', 'task.active', 'task.future'),
        'tasks.finished' => array('task.finished'),
        'tasks.archived' => array('task.archived'),
        );

    /**
     * @ignore (internal)
     * constructor prevents direct creation of object
     */
    protected function __construct()
    {
        $application = new Zend_Application(
            APPLICATION_ENV,
            APPLICATION_PATH . '/configs/application.ini'
        );
        $bootstrap = $application->getBootstrap();
        $bootstrap->bootstrap('db');
        $db = $bootstrap->getResource('db');
        if (!is_a($db, 'Zend_Db_Adapter_Abstract')) {
            throw new Exception('Failed to initialise database adapter');
        }
        self::$_db = $db;
    }

   /**
    * Initiate working context for user. Called by ...Controller::init()
    *
    * This method should be called for authenticated user only.
    * @param Taskr_Model_User $user
    */
    public function initContext(Taskr_Model_User $user)
    {
        if( !$user ) {
            throw new Exception('no user');
        }
    	if ( self::$_sessionUser && $user !== self::$_sessionUser ) {
    	    throw new Zend_Exception('Extra call to User::initContext()');
    	}

    	$this->_initWorkContext(intval($user->id));
    }

    /**
     * Execute prepared statement and check for results.
     *
     * Fetch error message or array of (possible) results
     * @return array|string
     */
    protected function _execForResult(Zend_Db_Statement $stmt, $noExceptions = FALSE)
    {
        $stmt->execute();             // excute the prepared stuff
        $stmt->closeCursor();

        $stmt = self::$_db->prepare('SELECT @error, @res1');
        $stmt->execute();
        $data = $stmt->fetch();
        $stmt->closeCursor();
        $result = $data['@error'];

        if ( NULL === $result ) {
            $result = array( $data['@res1'] );
        } elseif ( !$noExceptions ) {
            throw new exception($result);
        }
        return $result;
    }

    /**
     * Execute prepared statement and fetch a recordset
     * @return recordset
     */
    protected function _execForRows(Zend_Db_Statement $stmt)
    {
        $stmt->execute();              // excute the prepared stuff
        $data = $stmt->fetchAll();
        $stmt->closeCursor();

        if ( count($data) === 0 ) {
            $data = NULL;
        }
        return $data;
    }

    /**
     * Set up authenticated user connection environment in database server
     *
     * @param int $userId
     */
    protected function _initWorkContext($userId)
    {
        $stmt = self::$_db->prepare('call SetUserContext(?)');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }


    /**
     * Returns the instance of self, setting it up on the first call
     *
     * @return Taskr_Model_DataMapper
     */
    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class;
        }
        return self::$_instance;
    }


    /**
     * Retrieve the list of tasks of type specified by $args.
     *
     * @param string $which class of items to search for
     * @param mixed $args
     * @return NULL|array of items
     */
    public function loadItems(array $which, $args = NULL)
    {
       	switch ( $k = array_shift($which) ) {
       	case 'task':
       	    $res = $this->_loadTasks( $which, $args );
       	    break;
       	default:
       	    throw new exception( "List of {$k} required");
       	}
       	return $res;
    }

    /**
     * Retrieve the contents of compound list.
     *
     * Check if the list contains of sublists; for an empty list, return NULL instead.
     *
     * @param string $listKey matching a key in self::$_listsDictionary
     * @param mixed $args
     * @return NULL|array of items
     */
    public function loadList($listKey, $params = NULL)
    {
        $result = array();
        
        if (NULL !== ($lists = self::$_listsDictionary[$listKey]))
        {
            foreach($lists as $sublist) // recursion into definition levels
            {
                if (NULL !== ($res = $this->loadList($sublist, $params))) {
                    array_splice($result, count($result)-1, 0, $res);
                }
            }
        } else {                        // do the actual work here
            $result = $this->loadItems(explode('.', $listKey), $params);
        }
        
        return count($result) ? $result : NULL;
    }
    
    /****** SECTION: User ******/

    /**
     * Save user data and set user id, if it was missing.
     * 
     * @return int|string (value of id or error message)
     */
    public function saveUser(Taskr_Model_User $user)
    {
        if ( $user->id ) {      // just save changes
            $requireKey = FALSE;
            $stmt = self::$_db->prepare('call SaveUser(?,?,?,?,?)');
            $stmt->bindValue(1, $user->id, PDO::PARAM_INT);
        } else {                // create new user
            $requireKey = TRUE;
            $stmt = self::$_db->prepare('call CreateUser(?,?,?,?)');
            $stmt->bindValue(1, $user->username, PDO::PARAM_STR);
        }
        $stmt->bindValue(2, $user->password, PDO::PARAM_STR);
        $stmt->bindValue(3, $user->tzDiff, PDO::PARAM_INT);
        $stmt->bindValue(4, $user->emailTmp, PDO::PARAM_STR);
        if( !$requireKey ) {
            $stmt->bindValue(5, $user->email, PDO::PARAM_STR);
        }
        if( !is_string($data = $this->_execForResult($stmt)) ) {
            $data = $data[0];

            if( $requireKey ) { $user->id = $data; }
        }
        return $data;
    }

    /**
     * Fetch user data and create class instance
     * @return NULL | Taskr_Model_User
     */
    public function findUserById($userId)
    {
        $stmt = self::$_db->prepare(
            'SELECT id, username, password, email, emailTmp, tzDiff, credits' .
            ', unix_timestamp(proUntil) as proUntil' .
            ', unix_timestamp(added) as added' .
            ' FROM Users WHERE id = ?' );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);

        if ( $data = $this->_execForRows($stmt) ) {
            $data = new Taskr_Model_User($data[0]);
        }
        return $data;
    }

    /**
     * Fetch user data and create class instance.
     *
     * NB: this method should be called only in login sequnce, since it
     * initiates DB interface engine!
     * @param string $userName
     * @return NULL | Taskr_Model_User
     */
    public function findUserByUsername($userName)
    {
    	if ( !($user = self::$_sessionUser) || $user->userName != $username ) {

            $stmt = self::$_db->prepare('call GetUserByName(?)');
            $stmt->bindValue(1, $userName, PDO::PARAM_STR);

            if ( $data = $this->_execForRows($stmt) ) {
                $user = new Taskr_Model_User($data[0]);
            }
        }
        return $user;
    }

    /**
     * Fetch the user by email from the database
     *
     * @param string $email
     * @return Taskr_Model_User NULL if the user is not found
     */
    public function findUserByEmail($email)
    {
        $stmt = self::$_db->prepare(
            'SELECT id, username, password, email, emailTmp' .
            ', tzDiff, activeTask, proUntil, credits, added' .
            ' FROM Users WHERE email = ? AND ' .
            '(proUntil is NULL OR proUntil > now())' );
        $stmt->bindValue(1, $email, PDO::PARAM_STR);

        if ( $data = $this->_execForRows($stmt) ) {
            $data = new Taskr_Model_User($data[0]);
        }
        return $data;
    }

    /**
     * Deletes the user account and any data associated with it
     *
     * @param Taskr_Model_User $user
     * @todo put it into a stored procedure
     */
    public function deleteUser(Taskr_Model_User $user)
    {
        self::$_db->beginTransaction();
        self::$_db->delete('Scraps', "userId = {$user->id}");
        self::$_db->delete('Tasks', "userId = {$user->id}");
        self::$_db->delete('Projects', "userId = {$user->id}");
        self::$_db->delete('Users', "id = {$user->id}");
        self::$_db->commit();
    }

    /****** SECTION: Task ******/

    /**
     * Fetches the given user's active task from the database
     *
     * @param Taskr_Model_User $user
     * @return Taskr_Model_Task
     *      NULL if user not found or user has no active task
     */
    public function activeTask()
    {
        $stmt = self::$_db->prepare('call GetActiveTask()');
        $res = $this->_execForRows($stmt);

        if ($res) {
            $res = $res[0];
            $res = new Taskr_Model_Task($res);
        }
        return $res;
    }

    /**
     * @ignore (internal)
     */
    protected function _scrapSave($taskId, $scrap)
    {
        $stmt = self::$_db->prepare('call SaveScrap(?,?)');
        $stmt->bindValue(1, $taskId, PDO::PARAM_INT);
        $stmt->bindValue(2, $scrap, PDO::PARAM_LOB);
        $this->_execForResult($stmt);
    }

    /**
     * Save task data and create class instance
     *
     * @param Taskr_Model_Task $task
     * @param bool $scrapWasChanged
     * @return int task id
     * @throw exception on database error
     */
    public function saveTask(Taskr_Model_Task $task, $scrapWasChanged = NULL)
    {
        $requireKey = NULL; $transaction = FALSE;

        $i = 0; $scrap = $task->scrap;

        if ( strlen($scrap) >= self::SHORT_SCRAP_LIMIT )
        {
            if ( $inTransaction = $scrapWasChanged ) {
                self::$_db->beginTransaction();
            }
            $scrap = substr( $scrap, 0, self::SHORT_SCRAP_LIMIT );
        }
        try
        {
            if ( $task->id ) {      // just save changes
                if ( $inTransaction ) {
                    $this->_scrapSave( NULL, $task->scrap );
                }
                $stmt = self::$_db->prepare('call SaveTask(?,?,?,?,?)');
                $stmt->bindValue(++$i, $task->flags, PDO::PARAM_INT);
            } else {                // create new record
                $stmt = self::$_db->prepare('call CreateTask(?,?,?,?,?)');
                $stmt->bindValue(++$i, $task->title, PDO::PARAM_STR);
                $requireKey = TRUE;
            }
            $stmt->bindValue(++$i, $task->projectId, PDO::PARAM_INT);
            $stmt->bindValue(++$i, $task->liveline, PDO::PARAM_INT);
            $stmt->bindValue(++$i, $task->deadline, PDO::PARAM_INT);
            $stmt->bindValue(++$i, $scrap, PDO::PARAM_STR);
            $data = $this->_execForResult($stmt);

            $data = $data[0];

            if ( !$task->id ) {
                if ( $inTransaction ) {
                    $this->_scrapSave( $data, $task->scrap );
                }
                $task->id = $data;
            }

            if( $inTransaction ) {
                self::$_db->commit();
            }
        }
        catch ( exception $e )
        {
            if( $inTransaction ) {
                self::$_db->rollBack(); throw $e;
            }
        }

        return $data;
    }

    /**
     * Read long scrap for given task (only if such exists)
     *
     * @param Taskr_Model_Task $task
     * @return string scrap
     */
    public function readScrap(Taskr_Model_Task $task)
    {
        $stmt = self::$_db->prepare('select longScrap from Scraps where taskId = ?');
        $stmt->bindValue(1, $task->id, PDO::PARAM_INT);
        if ( $data = $this->_execForRows($stmt) ) {
            $data = $data[0]['longScrap'];
        }
        return $data;
    }

    /**
     * Save task data and create class instance
     * @return int | string
     */
    public function startTask($id)
    {
        $stmt = self::$_db->prepare('call StartTask(?)');
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }


    /**
     * Stop or finish the task
     * @return int | string
     */
    public function stopTask(Taskr_Model_Task $task)
    {
        $stmt = self::$_db->prepare('call StopTask(?,?,?)');
        $stmt->bindValue(1, $task->id, PDO::PARAM_INT);
        $stmt->bindValue(2, $task->projectId, PDO::PARAM_INT);
        $stmt->bindValue(3, $task->flags, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }

    /**
     * Archive all tasks of given user
     * @return int | string
     */
    public function archiveTasks(Taskr_Model_User $user)
    {
        $stmt = self::$_db->prepare(
            'update Tasks set flags = flags | 16 where userId = ? and flags < 16');
        $stmt->bindValue(1, $user->id, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }

    /****** SECTION: Project ******/

    /**
     * Fetches the given user's active project from the database
     *
     * @param Taskr_Model_User $user
     * @return Taskr_Model_Project
     *      NULL if user not found or user has no active task
     *      or user's active task is not associated to any project
     */
    public function activeProject(Taskr_Model_User $user)
    {
        $task = $user->activeTask();
        if (isset($task) && isset($task->project)) {
            return $task->project;
        }
        return NULL;
    }

    /**
     * Fetches the project by id from the database
     *
     * @param int $id
     * @return Taskr_Model_Project NULL if the task is not found
     */
    public function findProject($id)
    {
        $sql = 'SELECT * FROM ProjectsV WHERE id = ?';

        if ($res = self::$_db->fetchRow($sql, $id)) {
            $res = new Taskr_Model_Project($res);
        }
        return $res;
    }

    /**
     * Returns the sum of the durations of all tasks associated with
     * the given project
     *
     * @param Taskr_Model_Project $project
     */
    public function projectDuration(Taskr_Model_Project $project)
    {
        $sql = 'SELECT SUM(duration) FROM Tasks WHERE projectId = ?';
        $result = self::$_db->fetchOne($sql, $project->id);

        return $result;
    }

    /**
     * Save the project into the database
     *
     * @param Taskr_Model_Project $project
     */
    public function saveProject(Taskr_Model_Project $project)
    {
        $user = $project->user;

        if (!is_a($user, Taskr_Model_User) || NULL == $user->id) {
            throw new Exception('Cannot save project with no owner');
        }
        if (NULL == $project->title) {
            throw new Exception('Cannot save project without title');
        }

        if ( $project->id ) {
            throw new Exception('Cannot save project repeatedly');
        }

        $stmt = self::$_db->prepare('call CreateProject(?,?)');

        $stmt->bindValue(1, $project->userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $project->title, PDO::PARAM_STR);
        $data = $this->_execForResult($stmt);

        $data = $data[0];

        if ( !$project->id ) {
            $project->id = $data;
        }
    }

    /**
     * Finishes a project if $task is its last unfinished task
     *
     * Returns TRUE if $task was the project's last unfinished task or
     * FALSE if not.
     *
     * @param Taskr_Model_Task $task
     * @return bool
     * @throw Exception if $task did not belong to a project or if the
     * project was already finished.
     */
    public function finishProject(Taskr_Model_Task $task)
    {
        if (!$task->projectId) {
            throw new Exception('Task had no project');
        }

        // count unfinished tasks in the same project
        $sql = 'SELECT COUNT(*) from Tasks' .
            ' WHERE projectId = ? AND flags < 8';
        $result = self::$_db->fetchOne($sql, $task->projectId);

        if (1 == $result) {
            // this is the last unfinished task of this project,
            // so let's finish the project too
            $stmt = self::$_db->prepare('call FinishProject(?)');
            $stmt->bindValue(1, $task->projectId, PDO::PARAM_INT);
            $this->_execForResult($stmt);
            $task->project->finished = $task->lastStopped;
            return TRUE;
        } elseif (1 < $result) {
            // the project had other unfinished tasks as well,
            // so we won't finish it yet
            return FALSE;
        } else {
            // the project had no unfinished tasks
            throw new Exception('The project had no unfinished tasks');
        }
    }

    /****** SECTION: Lists ******/

    /**
     * Fetches the given user's archived projects completed between $fromTs and
     * $toTs from the database.
     *
     * If $fromTs is not set, all archived projects will be returned.
     *
     * If $toTs is not set, all archived projects completed no earlier than
     * $fromTs will be returned.
     *
     * @param Taskr_Model_User $user
     * @param int $fromTs Unix timestamp
     * @param int $toTs Unix timestamp
     * @return array of Taskr_Model_Project
     */
    public function archivedProjects(Taskr_Model_User $user, $fromTs, $toTs)
    {
        $params[':userId'] = $user->id;
        $sql = 'SELECT * FROM ProjectsV' .
            ' WHERE userId = :userId';

        if (isset($fromTs)) {
            $params[':fromTs'] = $fromTs;
            $sql .= ' AND finished >= :fromTs';
        } else {
            $sql = ' AND finished >= unix_timestamp(Day(Now()))';
        }
        if (isset($toTs)) {
            $params[':toTs'] = $toTs;
            $sql .= ' AND finished <= :toTs';
        }

        $sql .= ' ORDER BY finished ASC';
        $rows = self::$_db->fetchAll($sql, $params);

        // construct return array
        $result = array();
        foreach ($rows as $row) {
            array_push($result, new Taskr_Model_Project($row));
        }

        return $result;
    }

    /**
     * Fetches the given user's archived tasks completed between $fromTs and
     * $toTs from the database.
     *
     * If $fromTs is not set, all archived tasks will be returned.
     *
     * If $toTs is not set, all archived tasks completed no earlier than
     * $fromTs will be returned.
     *
     * @param Taskr_Model_User $user
     * @param int $fromTs Unix timestamp
     * @param int $toTs Unix timestamp
     * @return array of Taskr_Model_Task
     */
    public function archivedTasks(Taskr_Model_User $user, $fromTs, $toTs)
    {
        $params[':userId'] = $user->id;
        $sql = 'SELECT * FROM ArchievedTasksV' .
            ' WHERE userId = :userId';
        if (isset($fromTs)) {
            $params[':fromTs'] = $fromTs;
            $sql .= ' AND lastStopped >= :fromTs';
        } else {
            $sql = ' AND lastStopped >= unix_timestamp(Day(Now())';
        }
        if (isset($toTs)) {
            $params[':toTs'] = $toTs;
            $sql .= ' AND lastStopped <= :toTs';
        }
        $sql .= ' ORDER BY projectId ASC, lastStopped ASC';
        $rows = self::$_db->fetchAll($sql, $params);

        // construct return array
        $result = array();
        foreach ($rows as $row) {
            array_push($result, new Taskr_Model_Task($row));
        }

        return $result;
    }



    /**
     * Fetches the given user's unfinished projects from the database
     *
     * @param Taskr_Model_User $user
     * @return array of Taskr_Model_Project
     */
    public function unfinishedProjects(Taskr_Model_User $user)
    {
        $sql = 'SELECT * FROM ProjectsV' .
            ' WHERE userId = ? AND finished IS NULL' .
            ' ORDER BY title ASC';
        $rows = self::$_db->fetchAll($sql, $user->id);

        // construct and return result array
        $result = array();
        foreach ($rows as $row) {
            array_push($result, new Taskr_Model_Project($row));
        }
        return $result;

    }

    /**
     * Fetch the current user's tasks from the database
     * @param array $which with a string (act|fut|tod|ove|liv|fin|arc) as a 1-st member,
     *   specifying status of the tasks to look for.
     * @param NULL|int $projectId
     * @return array of Taskr_Model_Task
     */
    protected function _loadTasks(array $which, $projectId)
    {
        $result = array(); $k = array_shift($which);
        $stmt = self::$_db->prepare('call ReadTasks(?,?)');
        $stmt->bindValue(1, substr($k,0,3), PDO::PARAM_STR);
        $stmt->bindValue(2, $projectId, PDO::PARAM_INT);
        $rows = $this->_execForRows($stmt);

        if ( $rows ) {
            foreach ($rows as $row) {
                array_push($result, new Taskr_Model_Task($row));
            }
        }
        return $result;
    }


}

