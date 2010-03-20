/**
 * @package Taskr
 * @subpackage Mysql
 * @todo inline documenting
 * @author Villem Alango <valango@gmail.com>
 * @copyright Copyright (c) Villem Alango <valango@gmail.com>
 * @version 0.1.0
 */

DROP PROCEDURE IF EXISTS `CreateUser`;   	-- CREATE new user
DROP PROCEDURE IF EXISTS `SaveUser`;     	-- save user data changes
DROP PROCEDURE IF EXISTS `SetUserContext`;  -- set up user environment
DROP PROCEDURE IF EXISTS `GetUserByName`;  	-- fetch user record
DROP PROCEDURE IF EXISTS `CreateTask`;
DROP PROCEDURE IF EXISTS `SaveTask`;
DROP PROCEDURE IF EXISTS `SaveScrap`;
DROP PROCEDURE IF EXISTS `StopTask`;
DROP PROCEDURE IF EXISTS `StartTask`;
DROP PROCEDURE IF EXISTS `ReadTasks`;
DROP PROCEDURE IF EXISTS `GetActiveTask`;
DROP PROCEDURE IF EXISTS `CreateProject`;
DROP PROCEDURE IF EXISTS `FinishProject`;
DROP PROCEDURE IF EXISTS `riseError`;

DROP FUNCTION  IF EXISTS `getUserId`;

DROP VIEW      IF EXISTS `ArchievedTasksV`;
DROP VIEW      IF EXISTS `FinishedTasksV`;
DROP VIEW      IF EXISTS `ProjectsV`;

DELIMITER //

/**
 * Generate exception.
 * @param string errmessage
 * @access internal
 */
CREATE PROCEDURE riseError (
    errmessage varchar(255)
)  
BEGIN
    declare specialty condition for SQLSTATE '45100';
    
    set @error = errmessage;
    /* NB: the following line is OK only for MySQL 5.5 and above */
    -- signal specialty set message_text = errmessage;
    
END //

/**
 * Set up user environment.
 * This must be the 1st CALL during the connection from authenticated user.
 * Variables "@res..." are used as workaround for MySQL bug #27362
 *
 * @param int userId
 */
CREATE PROCEDURE SetUserContext
(
    userId integer unsigned
  )
BEGIN
	set @error = 'record not found';

    select id, activeTask, UNIX_TIMESTAMP(), NULL, NULL, NULL
      into @taskr_user, @taskr_task, @taskr_connected, @error, @res1, @res2
      from Users where id = userId;
END //

/**
 * Create new user record
 */
CREATE PROCEDURE CreateUser
(
  /* INOUT -- due to bug #27362: "OUT or INOUT argument is not a variable or..."
     we have to rely on odd-ball solution here via session variable @res1  */	
		uname	varchar(30),
		pwd		varchar(255),
		tz		smallint,
		mailTmp	varchar(30)
	)
BEGIN
	set @res1 = NULL, @error = NULL;
	
    select sql_no_cache 'User with the same name exists' into @error
    	from Users where username = uname limit 1;
    
    IF @error is NULL THEN
		insert into Users (
			username, password, emailTmp, tzDiff, 
			added, updated
		) values (
			uname, pwd, mailTmp, ifnull(tz,0),
			NULL, NULL	           			-- force CURRENT_TIMESTAMP
		);
  		set @res1 := LAST_INSERT_ID();	-- anal tooth brushing (for bug #27362)
  		
    	set @taskr_user = @res1, @taskr_task = NULL, 
    		@taskr_connected = UNIX_TIMESTAMP();
  	END IF;
END //


/**
 * Save user data changes
 */
CREATE PROCEDURE SaveUser
(
		uid		integer unsigned,
		pwd		varchar(255),
		tz		smallint,
		mailTmp varchar(30),
		mail	varchar(30)
	)
BEGIN
	update Users
		set password = pwd, email = mail, emailTmp = mailTmp, tzDiff = tz,
			updated = NULL
		where id = uid;
		
	set @res1 = NULL, @error = NULL, @i = 1;  -- row_count(); unexplained "-1"

	IF @i <= 0 THEN
	     set @error = concat( 'Users update failed:', conv(@i,10,-10));
	END IF;
END //


/**
 * retrieve user by name
 */
CREATE PROCEDURE GetUserByName
(
    uname varchar(30)
  )
BEGIN
    select	sql_no_cache
    	id, username, password, email, emailTmp, tzDiff,
    	/* activeTask, */ 
    	unix_timestamp(proUntil) as proUntil, credits, 
    	unix_timestamp(added) as added
     from Users where username = uname;
END //

/**
 * Fetch active task
 */
CREATE PROCEDURE GetActiveTask
( )
BEGIN
    select
        id, userId, projectId, title, duration,
        unix_timestamp(liveline) as liveline,
        unix_timestamp(deadline) as deadline,
        flags, added, lastStarted, lastStopped, scrap
      from Tasks
      where userId = @taskr_user and id = @taskr_task;    
END //

/**
 * @todo inline documenting
 */
CREATE FUNCTION getUserId() returns integer unsigned
BEGIN
    IF @taskr_user is NULL THEN
        CALL riseError('getUserId: user context not set');
        RETURN NULL;
    END IF;
    RETURN @taskr_user;
END //

/**
 * @todo inline documenting
 */
CREATE PROCEDURE CreateTask		-- Create a new task record
(
  /* INOUT -- due to bug #27362: "OUT or INOUT argument is not a variable or..."
     we have to rely on odd-ball solution here via session variable @res1  */	
		title	varchar(60),
		proj_id	integer unsigned,
		livelin	integer unsigned,
		deadlin	integer unsigned,
		scrap	varchar(60)
	)
BEGIN
	set @error = NULL;
  	insert into Tasks (
		userId, projectId, title, scrap, 
		liveline, deadline, 
		added, updated
    ) values (
		@taskr_user, proj_id, title, ifnull(scrap,''),
		FROM_UNIXTIME(livelin),
		FROM_UNIXTIME(deadlin),
		NULL, NULL	           			-- force CURRENT_TIMESTAMP
	);
  	set @res1 := LAST_INSERT_ID();	-- anal tooth brushing (for bug #27362)
END //
	
/**
 * @todo inline documenting
 */
CREATE PROCEDURE SaveTask		-- Save task after editing
(
		status  smallint,
		proj_id	integer unsigned,
		livelin	integer unsigned,
		deadlin	integer unsigned,
		s_scrap	varchar(60)
   )
BEGIN
	declare bExists integer default 0;
	
	IF @taskr_task is NULL THEN
        CALL riseError('SaveTask: no task active');
    ELSE
        set @error = NULL, s_scrap = ifnull(s_scrap,'');
        
		IF length(s_scrap) < 60 THEN
			select taskId into bExists from Scraps where taskId = @taskr_task;
			
			IF bExists THEN
				delete from Scraps where taskId = @taskr_task;
			END IF;
		END IF;
		
        update Tasks
          set	liveline = FROM_UNIXTIME(livelin), projectId = proj_id, 
        		deadline = FROM_UNIXTIME(deadlin), scrap = s_scrap,
        		flags = status, updated = NULL
          where id = @taskr_task;
          
		IF ROW_COUNT() <= 0 THEN
	    	CALL riseError('SaveTask: no tasks updated');
	    ELSE
	    	set @res1 = @taskr_task;
        END IF;
    END IF;

END //

/**
 * Save or update long scrap.
 * NB: SaveTask() should be called immediately after this
 */
CREATE PROCEDURE SaveScrap
(
	task_id		integer unsigned,	-- use non-NULL only upon task creation!
	long_scrap	text
	)
BEGIN
	declare mylen integer default NULL;

	IF task_id is NULL and @taskr_task is NULL THEN
        CALL riseError('SaveTask: no task active');
    ELSE
        set @error = NULL;
        
        IF task_id is NULL THEN
        	select length( scrap ), id into mylen, task_id from Tasks where id = @taskr_task;
       	ELSE
       		set mylen = 0;
        END IF;
        
        IF task_id is NULL THEN  -- IF mylen is NULL THEN
        	CALL riseError('SaveScrap: could not read task record');
        ELSE
			IF mylen < 60 THEN					-- we made a long scrap from short one
				insert into Scraps ( taskId, userId, longScrap, added, updated )
					values ( task_id, @taskr_user, long_scrap, NULL, NULL );
			ELSE
				update Scraps set longScrap = long_scrap, updated = NULL
					where taskId = @taskr_task;
			END IF;
			
			IF ROW_COUNT() <= 0 THEN
				CALL riseError('SaveScrap: operation failed');
			END IF;
        END IF;
	END IF;
END //

/**	
 * Stop the task task.
 * Set @res1 to Unix timestamp of WHEN this happened.
 * If this was he last unfinshed task of a project, 
 * finish the project and set @res2 to project id.
 */
CREATE PROCEDURE StopTask
(
  		task_id	integer unsigned,
  		proj_id integer unsigned,
  		status	smallint
	)
BEGIN
    declare t int unsigned DEFAULT unix_timestamp();
	declare n int unsigned default NULL;
    
	set @res1 = NULL, @res2 = NULL, @error = NULL;
	
    IF @taskr_nohaste is NULL THEN
    	update Users set activeTask = NULL
    	  where id = @taskr_user and activeTask = task_id;
    	  
    	IF proj_id is not NULL and (status & 8) THEN
    		select count(*) into n from Tasks
    			where projectId = proj_id and flags < 8;
    			
    		IF n = 1 THEN
    			START TRANSACTION;
    			
    			update Projects set flags = status, finished = from_unixtime(t)
    				where id = proj_id;
    				
    			set @res2 = proj_id;
    		END IF;
    	END IF;
    END IF;
    
	update Tasks
	  set lastStopped = t, duration = duration + (t - lastStarted),
	      flags = status, updated = NULL
	    where id = task_id and userId = @taskr_user and lastStarted > lastStopped;
	    
	IF ROW_COUNT() <= 0 THEN
		IF n = 1 THEN ROLLBACK; END IF;
		CALL riseError('StopTask: no tasks updated');
	ELSE IF n = 1 THEN COMMIT; END IF;
		set @res1 = t, @error = NULL;
	END IF;
END //

/**	
 * Start a new task, stopping the old one IF necessary.
 * @RETURN unix_timestamp in @res1
 * @todo checks, documenting
 */
CREATE PROCEDURE StartTask
(
  		task_id	integer unsigned
	)
BEGIN
	declare othertask integer unsigned;
	declare otherproj integer unsigned;
	
	select sql_no_cache activeTask into othertask from Users where id = @taskr_user;
	
	IF othertask is not NULL THEN
		set @taskr_nohaste = ifnull(@taskr_nohaste,'StartTask');
		select sql_no_cache projectId into otherproj from Tasks where id = othertask;
		CALL StopTask( othertask, otherproj, 0 );
	ELSE
		set @res1 = UNIX_TIMESTAMP(), @error = NULL;
	END IF;

    update Users set activeTask = task_id
    	where id = @taskr_user;
    
	update Tasks
	  set lastStarted = @res1, lastStopped = 0, updated = NULL
	    where id = task_id and userId = @taskr_user;

	IF ROW_COUNT() <= 0  THEN
	    CALL riseError('StartTask: no tasks updated');
	ELSE
		set @taskr_task = task_id;
	END IF;
	IF @taskr_nohaste = 'StartTask' THEN set @taskr_nohaste = NULL; END IF;
	
END //

/**
 * @todo inline documenting
 */
CREATE PROCEDURE ReadTasks 
(
    what    char(3),    -- liv, act, fut, tod, ove, fin, arc
    project integer unsigned
)
BEGIN
    declare bdone tinyint default 0;
    declare vdone tinyint default FALSE;
    declare varch tinyint default FALSE;
    declare t_horiz datetime default date_add(now(), interval 1 day);
    declare today datetime default Day(Now());

    IF @taskr_user is NULL THEN
        CALL riseError("ReadTasks(): user context is missing");
        set bdone = TRUE;
    ELSE
    	set @error = NULL;
   	
		CASE what
		WHEN 'act' THEN set varch = FALSE;
		WHEN 'fut' THEN set varch = FALSE;
		WHEN 'tod' THEN set varch = FALSE;
		WHEN 'ove' THEN set varch = FALSE;
		WHEN 'liv' THEN set varch = FALSE;
		WHEN 'fin' THEN
			select * from FinishedTasksV;
			set bdone = TRUE;
		WHEN 'arc' THEN
			select * from ArchievedTasksV;
			set bdone = TRUE;
		ELSE set @error = concat('illegal mode: "', what,'"' );
		END CASE;
		
		IF @error is not NULL THEN
			CALL riseError(concat('ReadTasks(): ', @error));
        	set bdone = TRUE;
		END IF;
    END IF;
    
    IF not bdone THEN
		select
			id, userId, projectId, title, duration, flags,
			unix_timestamp(liveline) as liveline,
			unix_timestamp(deadline) as deadline,
			unix_timestamp(added) as added,
			lastStarted, lastStopped, scrap
		  from Tasks
		  where    userId = @taskr_user and flags < 8
			  and (@taskr_task is NULL or id <> @taskr_task)
			  and (project is NULL or projectId = project)
			  and ( (what = 'ove' and deadline < today)
			   or   (what = 'tod' and Day(deadline) = today)
			   or   (what = 'fut' and Day(liveline) > today) 
			   or   (what = 'act'
				  and (liveline is NULL or Day(liveline) <= t_horiz)
				  and (deadline is NULL or Day(deadline) > t_horiz)
					 )
			   ) 
		  order by lastStopped ASC
		  ;
     END IF;
END //

CREATE PROCEDURE CreateProject (
	userid	integer unsigned,
	title	varchar(30)
)
BEGIN
	insert into Projects ( userId, title, added, updated )
		values ( userid, title, NULL, NULL );
			
  	set @res1 := LAST_INSERT_ID();	-- anal tooth brushing (for bug #27362)
END //

CREATE PROCEDURE FinishProject
(
	proj_id	integer unsigned
	)
BEGIN
	update Projects set flags = 8 | 16, finished = now()+0
		where id = proj_id;
END //

DELIMITER ;

/** 
 * Upcoming tasks (excluding the active task)
 * @todo checks, documenting
 */
CREATE VIEW FinishedTasksV (
        id, userId, projectId, title, duration, flags,
        liveline,
        deadline,
        added, lastStarted, lastStopped, scrap
)
as select
        id, userId, projectId, title, duration, flags,
        unix_timestamp(liveline) as liveline,
        unix_timestamp(deadline) as deadline,
		unix_timestamp(added) as added,
        lastStarted, lastStopped, scrap
  from Tasks
    where userId = getUserId() and flags >= 8 and flags < 16
    order by lastStopped ASC;


CREATE VIEW ArchievedTasksV (
        id, userId, projectId, title, duration, flags,
        liveline,
        deadline,
        added, lastStarted, lastStopped, scrap
)
as select
        id, userId, projectId, title, duration, flags,
        unix_timestamp(liveline) as liveline,
        unix_timestamp(deadline) as deadline,
		unix_timestamp(added) as added,
        lastStarted, lastStopped, scrap
  from Tasks
    where userId = getUserId() and flags >= 16
    order by lastStopped ASC;

/**
 * All projects of the current user
 */
CREATE VIEW ProjectsV (
		id, userId, title, finished, added
)
as select
		id, userId, title,
		unix_timestamp(finished),
		unix_timestamp(added)
	from	Projects
		where	userId = getUserId();
