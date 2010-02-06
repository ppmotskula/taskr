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
     * Protected constructor prevents direct creation of object
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

    /**
     * @ignore (internal)
     * Converts database row to Taskr_Model_User instance
     *
     * @param array $row database row
     * @return Taskr_Model_User
     */
    protected function _toUser($row)
    {
        if (!is_array($row)) {
            return NULL;
        }
        $user = new Taskr_Model_User(array(
            'id' => $row['id'],
            'username' => $row['username'],
            'password' => $row['password'],
            'email' => $row['email'],
            'tzDiff' => $row['tz_diff'],
            'added' => $row['added'],
            'proUntil' => $row['pro_until'],
            'credits' => $row['credits'],
        ));
        return $user;
    }

    /**
     * Fetches the user by id from the database
     *
     * @param int $id
     * @return Taskr_Model_User NULL if the user is not found
     */
    public function findUser($id)
    {
        $sql = 'SELECT * FROM users' .
            ' WHERE id = :id';
        $row = self::$_db->fetchRow($sql, array(
            ':id' => $id,
        ));
        return $this->_toUser($row);
    }

    /**
     * Fetches the user by username from the database
     *
     * @param string $username
     * @return Taskr_Model_User NULL if the user is not found
     */
    public function findUserByUsername($username)
    {
        $sql = 'SELECT * FROM users' .
            ' WHERE username = :username';
        $row = self::$_db->fetchRow($sql, array(
            ':username' => $username,
        ));
        return $this->_toUser($row);
    }

    /**
     * Saves the user into the database
     *
     * @param Taskr_Model_User &$user
     */
    public function saveUser(Taskr_Model_User &$user)
    {
        if (NULL == $user->username) {
            throw new Exception('Cannot save user without username');
        }
        if (NULL == $user->added) {
            $user->added = time();
        }
        $row = array(
            'id' => $user->id,
            'username' => $user->username,
            'password' => $user->password,
            'email' => $user->email,
            'tz_diff' => $user->tzDiff,
            'added' => $user->added,
            'pro_until' => $user->proUntil,
            'credits' => $user->credits,
        );
        if (NULL == $user->id) {
            unset($row['id']);
            self::$_db->insert('users', $row);
            $user->id = self::$_db->lastInsertId();
        } else {
            self::$_db->update('users', $row, "id = $id");
        }
    }

    /**
     * @ignore (internal)
     * Converts database row to Taskr_Model_Task instance
     *
     * @param array $row database row
     * @return Taskr_Model_Task
     */
    protected function _toTask($row)
    {
        if (!is_array($row)) {
            return NULL;
        }
        $task = new Taskr_Model_Task(array(
            'id' => $row['id'],
            'title' => $row['title'],
            'scrap' => $row['scrap'],
            'deadline' => $row['deadline'],
            'added' => $row['added'],
            'lastStarted' => $row['last_started'],
            'lastStopped' => $row['last_stopped'],
            'finished' => $row['finished'],
            'archived' => $row['archived'],
            'duration' => $row['duration'],
        ));
        if ($row['user_id']) {
            $task->user = $this->findUser($row['user_id']);
        }
        if ($row['project_id']) {
            $task->project = $this->findProject($row['project_id']);
        }
        return $task;
    }

    /**
     * Fetches the task by id from the database
     *
     * @param int $id
     * @return Taskr_Model_Task NULL if the task is not found
     */
    public function findTask($id)
    {
        $sql = 'SELECT * FROM tasks' .
            ' WHERE id = :id';
        $row = self::$_db->fetchRow($sql, array(
            ':id' => $id,
        ));
        return $this->_toTask($row);
    }

    /**
     * Saves the task into the database
     *
     * @param Taskr_Model_Task &$task
     */
    public function saveTask(Taskr_Model_Task &$task)
    {
        $user = $task->user;

        if (!is_a($user, Taskr_Model_User) || NULL == $user->id) {
            throw new Exception('Cannot save unassigned task');
        }
        if (NULL == $task->title) {
            throw new Exception('Cannot save task without title');
        }
        if (NULL == $task->added) {
            $task->added = time();
        }
        if (NULL == $task->lastStarted) {
            $task->lastStarted = 0;
        }
        if (NULL == $task->lastStopped) {
            $task->lastStopped = $task->added;
        }
        if (NULL == $task->duration) {
            $task->duration = 0;
        }

        $row = array(
            'id' => $task->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => $task->title,
            'scrap' => $task->scrap,
            'deadline' => $task->deadline,
            'added' => $task->added,
            'last_started' => $task->lastStarted,
            'last_stopped' => $task->lastStopped,
            'finished' => $task->finished,
            'archived' => $task->archived,
            'duration' => $task->duration,
        );
        if (NULL == $row['id']) {
            unset($row['id']);
            self::$_db->insert('tasks', $row);
            $task->id = self::$_db->lastInsertId();
        } else {
            self::$_db->update('tasks', $row, "id = {$task->id}");
        }
    }

    /**
     * @ignore (internal)
     * Converts database row to Taskr_Model_Project instance
     *
     * @param array $row database row
     * @return Taskr_Model_Project
     */
    protected function _toProject($row)
    {
        if (!is_array($row)) {
            return NULL;
        }
        $project = new Taskr_Model_Project(array(
            'id' => $row['id'],
            'title' => $row['title'],
            'finished' => $row['finished'],
        ));
        if ($row['user_id']) {
            $project->user = $this->findUser($row['user_id']);
        }
        return $project;
    }

    /**
     * Fetches the project by id from the database
     *
     * @param int $id
     * @return Taskr_Model_Project NULL if the task is not found
     */
    public function findProject($id)
    {
        $sql = 'SELECT * FROM projects' .
            ' WHERE id = :id';
        $row = self::$_db->fetchRow($sql, array(
            ':id' => $id,
        ));
        return $this->_toProject($row);
    }

    /**
     * Saves the project into the database
     *
     * @param Taskr_Model_Project &$project
     */
    public function saveProject(Taskr_Model_Project &$project)
    {
        $user = $project->user;

        if (!is_a($user, Taskr_Model_User) || NULL == $user->id) {
            throw new Exception('Cannot save project with no owner');
        }
        if (NULL == $project->title) {
            throw new Exception('Cannot save project without title');
        }
        if (NULL == $project->duration) {
            $project->duration = 0;
        }

        $row = array(
            'id' => $project->id,
            'user_id' => $user->id,
            'title' => $project->title,
            'finished' => $project->finished,
        );
        if (NULL == $row['id']) {
            unset($row['id']);
            self::$_db->insert('projects', $row);
            $project->id = self::$_db->lastInsertId();
        } else {
            self::$_db->update('projects', $row, "id = {$project->id}");
        }
    }

    /**
     * Fetches the given user's active task from the database
     *
     * @param Taskr_Model_User $user
     * @return Taskr_Model_Task
     *      NULL if user not found or user has no active task
     */
    public function activeTask(Taskr_Model_User $user)
    {
        $sql = 'SELECT * FROM tasks' .
            ' WHERE user_id = :userId' .
            ' AND (last_started > last_stopped' .
            ' OR last_started IS NOT NULL AND last_stopped IS NULL)';
        $row = self::$_db->fetchRow($sql, array(
            ':userId' => $user->id,
        ));
        if ($row) {
            return $this->_toTask($row);
        }
    }

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
        $task = $this->activeTask($user);
        if (isset($task) && isset($task->project)) {
            return $task->project;
        }
        return NULL;
    }

    /**
     * Fetches the given user's upcoming tasks from the database
     *
     * @param Taskr_Model_User $user
     * @param Taskr_Model_Project $project OPTIONAL
     * @return array of Taskr_Model_Task
     */
    public function upcomingTasks(Taskr_Model_User $user,
        Taskr_Model_Project $project = NULL)
    {
        // construct and execute SQL query
        $params[':userId'] = $user->id;
        $sql = 'SELECT * FROM tasks' .
            ' WHERE user_id = :userId' .
            ' AND (last_started IS NULL OR last_started = 0 OR last_stopped >= last_started)' .
            ' AND (finished IS NULL OR finished = 0)';
        if (isset($project)) {
            $sql .= ' AND project_id = :projectId';
            $params[':projectId'] = $project->id;
        }
        $sql .= ' ORDER BY last_stopped ASC';
        $rows = self::$_db->fetchAll($sql, $params);

        // construct and return result array
        $result = array();
        foreach ($rows as $row) {
            array_push($result, $this->_toTask($row));
        }
        return $result;
    }

    /**
     * Fetches the given user's finished tasks from the database
     *
     * @param Taskr_Model_User $user
     * @param Taskr_Model_Project $project OPTIONAL
     * @return array of Taskr_Model_Task
     */
    public function finishedTasks(Taskr_Model_User $user,
        Taskr_Model_Project $project = NULL)
    {
        // construct and execute SQL query
        $params[':userId'] = $user->id;
        $sql = 'SELECT * FROM tasks' .
            ' WHERE user_id = :userId' .
            ' AND finished = 1' .
            ' AND (archived IS NULL OR archived = 0)';
        if (isset($project)) {
            $params[':projectId'] = $project->id;
            $sql .= ' AND project_id = :projectId';
        }
        $sql .= ' ORDER BY last_stopped DESC';
        $rows = self::$_db->fetchAll($sql, $params);

        // construct and return result array
        $result = array();
        foreach ($rows as $row) {
            array_push($result, $this->_toTask($row));
        }
        return $result;
    }

    /**
     * Fetches the given user's archived tasks completed between $fromDate and
     * $toDate from the database.
     *
     * If $fromDate is not set, all archived tasks will be returned.
     *
     * If $toDate is not set, all archived tasks completed no earlier than
     * $fromDate will be returned.
     *
     * @param Taskr_Model_User $user
     * @param string $fromDate format: YYYY-MM-DD
     * @param string $toDate format: YYYY-MM-DD
     * @return array of Taskr_Model_Task
     */
    public function archivedTasks(Taskr_Model_User $user, $fromDate = NULL, $toDate = NULL)
    {
        // convert $fromDate and $toDate to timestamps if given
        if (NULL != $fromDate) {
            $fromTs = My_Util::_ymdToTs($fromDate) - $user->tzDiff;
            if (NULL != $toDate) {
                $toTs = My_Util::_ymdToTs($fromDate) - $user->tzDiff + 86400;
            }
        }

        // construct and execute SQL query
        $params[':userId'] = $user->id;
        $sql = 'SELECT * FROM tasks' .
            ' WHERE user_id = :userId' .
            ' AND archived = 1';
        if (isset($fromTs)) {
            $params[':fromTs'] = $fromTs;
            $sql .= ' AND last_stopped >= :fromTs';
        }
        if (isset($toTs)) {
            $params[':toTs'] = $toTs;
            $sql .= ' AND last_stopped < :toTs';
        }
        $sql .= ' ORDER BY project_id ASC, last_stopped ASC';
        $rows = self::$_db->fetchAll($sql, $params);

        // construct return array
        $result = array();
        foreach ($rows as $row) {
            array_push($result, $this->_toTask($row));
        }

        return $result;
    }

    /**
     * Starts a task
     *
     * @param Taskr_Model_Task &$task
     */
    public function startTask(Taskr_Model_Task &$task)
    {
        if ($activeTask = $task->user->activeTask()) {
            // if another task is active, stop it first
            $activeTask->stop();
        }
        $task->lastStarted = time();
        $this->saveTask($task);
    }

    /**
     * @ignore (internal)
     * Stops a task (internal)
     *
     * If $task is active, stops it and returns TRUE. Otherwise, returns FALSE.
     * Used internally by stopTask() and finishTask().
     *
     * @param Taskr_Model_Task &$task
     * @return bool
     */
    protected function _stopTask(Taskr_Model_Task &$task)
    {
        if ($task->lastStarted > $task->lastStopped) {
            // only active tasks can be stopped
            $task->lastStopped = time();
            $task->duration += ($task->lastStopped - $task->lastStarted);
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Stops a task
     *
     * @param Taskr_Model_Task &$task
     */
    public function stopTask(Taskr_Model_Task &$task)
    {
        if ($this->_stopTask($task)) {
            $this->saveTask($task);
        }
    }

    /**
     * Finishes a task, stopping it first if it is active
     *
     * @param Taskr_Model_Task &$task
     */
    public function finishTask(Taskr_Model_Task &$task)
    {
        if ($this->_stopTask($task)) {
            $task->finished = TRUE;
            $this->saveTask($task);
        }
    }

    /**
     * Archives a task
     *
     * @param Taskr_Model_Task &$task
     */
    public function archiveTask(Taskr_Model_Task &$task)
    {
        if ($task->finished) {
            // only finished tasks can be archived
            $task->archived = TRUE;
            $this->saveTask($task);
        }
    }

    /**
     * Returns the sum of the durations of all tasks associated with
     * the given project
     *
     * @param Taskr_Model_Project &$project
     */
    public function projectDuration(Taskr_Model_Project &$project)
    {
        // construct and execute SQL query
        $params[':projectId'] = $project->id;
        $sql = 'SELECT SUM(duration) FROM tasks' .
            ' WHERE project_id = :projectId';
        $result = self::$_db->fetchOne($sql, $params);

        return $result;
    }
}

