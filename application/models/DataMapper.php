<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
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
    const SHORT_SCRAP_LIMIT = 60;       // width of t_task.scrap field

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

    /**
     * @ignore (internal)
     * constructor prevents direct creation of object
     */
    protected function __construct()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
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
     * Dispatcher entry point
     */
    public function dispatch( $client, $what, $parameters = NULL )
    {
        if ( is_object($client) )
        {
            $clientClassName = get_class($clientInstance = $client);
        }
        else
        {
            $clientClassName = $client; $clientInstance = NULL;
        }
        assert( is_string($what) );
        My_Dbg::trc($clientClassName . '**dispatch**', $what);
    }
    
    
    /**
     * Execute prepared statement and ceheck for results
     * Fetch error message or array of (possible) results
     * @return array | string
     */
    protected function _execForResult($stmt, $noExceptions = FALSE)
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        $stmt->execute();             // excute the prepared stuff
        $stmt->closeCursor();
        
        $stmt = self::$_db->prepare('SELECT @error, @res1, @res1');
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
    protected function _execForRows($stmt)
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        $stmt->execute();              // excute the prepared stuff
        $data = $stmt->fetchAll();
        $stmt->closeCursor();
        
        if ( count($data) === 0 ) {
            $data = NULL;
        }
        return $data;
    }
    
    /**
     * Save user data and set user id, if it was missing.
     * @return int | string
     */
    public function userSave( Taskr_Model_User $obj )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $obj->username . '(#' . $obj->id . ')' );
        
        if ( $obj->id ) {      // just save changes
            $requireKey = FALSE;
            $stmt = self::$_db->prepare('call p_user_save(?,?,?,?,?)');
            $stmt->bindValue(1, $obj->id, PDO::PARAM_INT);
        } else {                // create new user
            $requireKey = TRUE;
            $stmt = self::$_db->prepare('call p_user_create(?,?,?,?)');
            $stmt->bindValue(1, $obj->username, PDO::PARAM_STR);
        }
        $stmt->bindValue(2, $obj->password, PDO::PARAM_STR);
        $stmt->bindValue(3, $obj->tzDiff, PDO::PARAM_INT);
        $stmt->bindValue(4, $obj->emailTmp, PDO::PARAM_STR);
        if( !$requireKey ) { 
            $stmt->bindValue(5, $obj->email, PDO::PARAM_STR);
        }
        if( !is_string($data = $this->_execForResult($stmt)) ) {
            $data = $data[0];
            
            if( $requireKey ) { $obj->id = $data; }
        }
        return $data;
    }
     
    /**
     * Fetch user data and create class instance
     * @return NULL | Taskr_Model_User
     */
    public function findUserById( $userId )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $userId);
        
        $stmt = self::$_db->prepare(
            'SELECT id, username, password, email, emailTmp' .
            ', tzDiff, activeTask, proUntil, credits, added' .
            ' FROM t_user WHERE id = ? AND ' .
            '(proUntil is NULL OR proUntil > now())' );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        
        if ( $data = $this->_execForRows($stmt) ) { 
            $data = new Taskr_Model_User($data[0]);
        }
        return $data;
    }
    
    /**
     * Fetch user data and create class instance
     * @return NULL | Taskr_Model_User
     */
    public function findUserByUsername( $userName )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $userName);
        
        $stmt = self::$_db->prepare('call pv_user_byname(?)');
        $stmt->bindValue(1, $userName, PDO::PARAM_STR);
        
        if ( $data = $this->_execForRows($stmt) ) { 
            $data = new Taskr_Model_User($data[0]);
        }
        return $data;
    }
    
    /**
     * Fetches the user by email from the database
     *
     * @param string $email
     * @return Taskr_Model_User NULL if the user is not found
     */
    public function findUserByEmail($email)
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        
        $stmt = self::$_db->prepare(
            'SELECT id, username, password, email, emailTmp' .
            ', tzDiff, activeTask, proUntil, credits, added' .
            ' FROM t_user WHERE email = ? AND ' .
            '(proUntil is NULL OR proUntil > now())' );
        $stmt->bindValue(1, $email, PDO::PARAM_STR);
        
        if ( $data = $this->_execForRows($stmt) ) { 
            $data = new Taskr_Model_User($data[0]);
        }
        return $data;
    }

    /**
     * Set up authenticated user connection environment in database server
     *
     * @param int $userId
     * @todo make it lazy and protected
     */
    public function initUserConnection( $userId )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $userId);
        $stmt = self::$_db->prepare('call p_user_connect(?)');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }
    
    
    /**
     * Returns the instance of the class, setting it up on the first call
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


    public function loadItems( array $which, $args = NULL )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, implode('.', $which ));
       	
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
     * Fetches the given user's active task from the database
     *
     * @param Taskr_Model_User $user
     * @return Taskr_Model_Task
     *      NULL if user not found or user has no active task
     */
    public function loadActiveTask()
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__);
        $stmt = self::$_db->prepare('call pv_activetask()');
        $res = $this->_execForRows($stmt);
        // My_Dbg::dump($res, '***** from activeTASKS');
        
        if ($res) {
            $res = $res[0];
            $res = new Taskr_Model_Task($res);
        }
        return $res;
    }

    protected function _scrapSave( $taskId, $scrap )
    {
        $stmt = self::$_db->prepare('call p_scrap_save(?,?)');
        $stmt->bindValue(1, $taskId, PDO::PARAM_INT);
        $stmt->bindValue(2, $scrap, PDO::PARAM_LOB);
        $this->_execForResult($stmt);
    }

    /**
     * Save task data and create class instance
     * @return int | string
     */
    public function taskSave( $obj, $scrapWasChanged = NULL )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $obj->id);
        $requireKey = NULL; $transaction = FALSE;

        $i = 0; $scrap = $obj->scrap;
        
        if ( strlen($scrap) >= self::SHORT_SCRAP_LIMIT )
        {
            self::$_db->beginTransaction();
            $inTransaction = $scrapWasChanged;
            $scrap = substr( $scrap, 0, self::SHORT_SCRAP_LIMIT );
        }
        try
        {
            if ( $obj->id ) {      // just save changes
                if ( $inTransaction ) {
                    $this->_scrapSave( NULL, $obj->scrap );
                }
                $stmt = self::$_db->prepare('call p_task_save(?,?,?,?)');
            } else {                // create new user
                $stmt = self::$_db->prepare('call p_task_create(?,?,?,?,?)');
                $stmt->bindValue(++$i, $obj->title, PDO::PARAM_STR);
                $requireKey = TRUE;
            }
            $stmt->bindValue(++$i, $obj->projectId, PDO::PARAM_INT);
            $stmt->bindValue(++$i, $obj->liveline, PDO::PARAM_INT);
            $stmt->bindValue(++$i, $obj->deadline, PDO::PARAM_INT);
            $stmt->bindValue(++$i, $scrap, PDO::PARAM_STR);
            $data = $this->_execForResult($stmt);
            
            $data = $data[0];

            if ( !$obj->id ) {
                if ( $inTransaction ) {
                    $this->_scrapSave( $data, $obj->scrap );
                }
                $obj->id = $data;
            }
    
            // if( $requireKey ) { $obj->id = $data; }

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
     * Read long scrap (only if such exists)
     * @return string scrap
     */
    public function scrapRead( $taskId )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $taskId);
        $stmt = self::$_db->prepare('select longScrap from t_scrap where taskId = ?');
        $stmt->bindValue(1, $taskId, PDO::PARAM_INT);
        if ( $data = $this->_execForRows($stmt) ) { 
            My_dbg::dump($data, '===SCRAP====');
            $data = $data[0]['longScrap'];
        }
        return $data;
    }
    
    /**
     * Save task data and create class instance
     * @return int | string
     */
    public function taskStart( $id )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $id);
        
        $stmt = self::$_db->prepare('call p_task_start(?)');
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }


    /**
     * Save task data and create class instance
     * @return int | string
     */
    public function taskStop( $obj )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $obj->id);
        
        $stmt = self::$_db->prepare('call p_task_stop(?,?)');
        $stmt->bindValue(1, $obj->id, PDO::PARAM_INT);
        $stmt->bindValue(2, $obj->finished, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }
    
    public function tasksArchive( $userId )
    {
        My_Dbg::trc(__CLASS__, __FUNCTION__, $userId);
        
        $stmt = self::$_db->prepare(
            'update t_task set archived = 2 where userId = ? and finished > 0');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $this->_execForResult($stmt);
    }
     
    /**
     * Fetches the given user's tasks from the database
     * @usedby loadItems()
     * @return array of Taskr_Model_Task
     */
    protected function _loadTasks(array $which, $parms)
    {
        $result = array(); $k = array_shift($which);

        $stmt = self::$_db->prepare('call pv_tasks(?,?)');
        $stmt->bindValue(1, substr($k,0,3), PDO::PARAM_STR);
        $stmt->bindValue(2, $parms, PDO::PARAM_INT);
        $rows = $this->_execForRows($stmt);
        // My_Dbg::dump($rows, '***** from PV_TASKS');
        if ( $rows ) {
            foreach ($rows as $row) {
                array_push($result, new Taskr_Model_Task($row));
            }
        }
        return $result;
    }
    

}

