/**
 * @package Taskr
 * @subpackage Mysql
 * @todo inline documenting
 * @author Villem Alango <valango@gmail.com>
 * @copyright Copyright (c) Villem Alango <valango@gmail.com>
 * @version 0.1.0
 */

drop procedure if exists `p_user_create`;   -- create new user
drop procedure if exists `p_user_save`;     -- save user data changes
drop procedure if exists `p_user_connect`;  -- set up user environment
drop procedure if exists `pv_user_byname`;  -- fetch user record
drop procedure if exists `p_task_create`;
drop procedure if exists `p_task_save`;
drop procedure if exists `p_scrap_save`;
drop procedure if exists `p_task_stop`;
drop procedure if exists `p_task_start`;
drop procedure if exists `pv_tasks`;
drop procedure if exists `pv_activetask`;
drop procedure if exists `p_myexception`;

drop function  if exists `f_user_id`;

drop view      if exists `v_tasks_arch`;
drop view      if exists `v_tasks_fini`;

delimiter //

/**
 * Generate exception.
 * @param string errmessage
 * @access internal
 */
create procedure p_myexception (
    errmessage varchar(255)
)  
begin
    declare specialty condition for SQLSTATE '45100';
    
    set @error = errmessage;
    /* NB: the following line is OK only for MySQL 5.5 and above */
    -- signal specialty set message_text = errmessage;
    
end //

/**
 * Set up user environment.
 * This must be the 1st call during the connection from authenticated user.
 * @param int userId
 */
create procedure p_user_connect
(
    userId integer unsigned
  )
begin
	set @error = 'record not found';

    select id, activeTask, UNIX_TIMESTAMP(), NULL
      into @taskr_user, @taskr_task, @taskr_connected, @error
      from t_user where id = userId;
end //

/**
 * Create new user record
 */
create procedure p_user_create
(
  /* INOUT -- due to bug #27362: "OUT or INOUT argument is not a variable or..."
     we have to rely on odd-ball solution here via session variable @res1  */	
		uname	varchar(30),
		pwd		varchar(255),
		tz		smallint,
		mailTmp	varchar(30)
	)
begin
	set @res1 = NULL, @error = NULL;
	
    select sql_no_cache 'User with the same name exists' into @error
    	from t_user where username = uname limit 1;
    
    if @error is NULL then
		insert into t_user (
			username, password, emailTmp, tzDiff, 
			added, updated
		) values (
			uname, pwd, mailTmp, ifnull(tz,0),
			NULL, NULL	           			-- force CURRENT_TIMESTAMP
		);
  		set @res1 := LAST_INSERT_ID();	-- anal tooth brushing (for bug #27362)
  		
    	set @taskr_user = @res1, @taskr_task = NULL, 
    		@taskr_connected = UNIX_TIMESTAMP();
  	end if;
end //


/**
 * Save user data changes
 */
create procedure p_user_save
(
		uid		integer unsigned,
		pwd		varchar(255),
		tz		smallint,
		mailTmp varchar(30),
		mail	varchar(30)
	)
begin
	update t_user
		set password = pwd, email = mail, emailTmp = mailTmp, tzDiff = tz,
			updated = NULL
		where id = uid;
		
	set @res1 = NULL, @error = NULL, @i = 1;  -- row_count(); unexplained "-1"

	if @i <= 0 then
	     set @error = concat( 't_user update failed:', conv(@i,10,-10));
	end if;
end //


/**
 * retrieve user by name
 */
create procedure pv_user_byname
(
    uname varchar(30)
  )
begin
    select	sql_no_cache
    	id, username, password, email, tzDiff,
    	/* activeTask, */ proUntil, credits, added
     from t_user where username = uname;
end //

/**
 * Fetch active task
 */
create procedure pv_activetask
( )
begin
    select
        id, userId, projectId, title, duration,
        unix_timestamp(liveline) as liveline,
        unix_timestamp(deadline) as deadline,
        flags, added, lastStarted, lastStopped, scrap
      from t_task
      where userId = @taskr_user and id = @taskr_task;    
end //

/**
 * @todo inline documenting
 */
create function f_user_id() returns integer unsigned
begin
    if @taskr_user is NULL then
        call p_myexception('f_user_id: user context not set');
        return NULL;
    end if;
    return @taskr_user;
end //

/**
 * @todo inline documenting
 */
create procedure p_task_create		-- Create a new task record
(
  /* INOUT -- due to bug #27362: "OUT or INOUT argument is not a variable or..."
     we have to rely on odd-ball solution here via session variable @res1  */	
		title	varchar(60),
		proj_id	integer unsigned,
		livelin	integer unsigned,
		deadlin	integer unsigned,
		scrap	varchar(60)
	)
begin
	set @error = NULL;
  	insert into t_task (
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
end //
	
/**
 * @todo inline documenting
 */
create procedure p_task_save		-- Save task after editing
(
		status  smallint,
		proj_id	integer unsigned,
		livelin	integer unsigned,
		deadlin	integer unsigned,
		s_scrap	varchar(60)
   )
begin
	declare bExists integer default 0;
	
	if @taskr_task is NULL then
        call p_myexception('p_task_save: no task active');
    else
        set @error = NULL, s_scrap = ifnull(s_scrap,'');
        
		if length(s_scrap) < 60 then
			select taskId into bExists from t_scrap where taskId = @taskr_task;
			
			if bExists then
				delete from t_scrap where taskId = @taskr_task;
			end if;
		end if;
		
        update t_task
          set	liveline = FROM_UNIXTIME(livelin), projectId = proj_id, 
        		deadline = FROM_UNIXTIME(deadlin), scrap = s_scrap,
        		flags = status, updated = NULL
          where id = @taskr_task;
          
		if ROW_COUNT() <= 0 then
	    	call p_myexception('p_task_save: no tasks updated');
        end if;
    end if;

end //

/**
 * Save or update long scrap.
 * NB: p_task_save() should be called immediately after this
 */
create procedure p_scrap_save
(
	task_id		integer unsigned,	-- use non-NULL only upon task creation!
	long_scrap	text
	)
begin
	declare mylen integer default NULL;

	if task_id is NULL and @taskr_task is NULL then
        call p_myexception('p_task_save: no task active');
    else
        set @error = NULL;
        
        if task_id is NULL then
        	select length( scrap ), id into mylen, task_id from t_task where id = @taskr_task;
       	else
       		set mylen = 0;
        end if;
        
        if task_id is NULL then  -- if mylen is NULL then
        	call p_myexception('p_scrap_save: could not read task record');
        else
			if mylen < 60 then					-- we made a long scrap from short one
				insert into t_scrap ( taskId, longScrap, added, updated )
					values ( task_id, long_scrap, NULL, NULL );
			else
				update t_scrap set longScrap = long_scrap, updated = NULL
					where taskId = @taskr_task;
			end if;
			
			if ROW_COUNT() <= 0 then
				call p_myexception('p_scrap_save: operation failed');
			end if;
        end if;
	end if;
end //

/**	
 * Stop the task task.
 * Set @res1 to Unix timestamp of when this happened.
 * @todo checks, documenting
 */
create procedure p_task_stop
(
  		task_id	integer unsigned,
  		status	smallint
	)
begin
    declare t int unsigned DEFAULT UNIX_TIMESTAMP();
    
	set @res1 = NULL, @error = NULL;
	
    if @taskr_nohaste is NULL then
    	update t_user set activeTask = NULL
    	  where id = @taskr_user and activeTask = task_id;
    end if;
    
	update t_task
	  set lastStopped = t, duration = duration + (t - lastStarted),
	      flags = status, updated = NULL
	    where id = task_id and userId = @taskr_user and lastStarted > lastStopped;
	    
	if ROW_COUNT() <= 0 then
	    call p_myexception('p_task_stop: no tasks updated');
	else
		set @res1 = t;
	end if;
end //

/**	
 * Start a new task, stopping the old one if necessary.
 * @return unix_timestamp in @res1
 * @todo checks, documenting
 */
create procedure p_task_start
(
  		task_id	integer unsigned
	)
begin
	declare othertask integer unsigned;
	
	select activeTask into othertask from t_user where id = @taskr_user;
	
	if othertask is not NULL then
		set @taskr_nohaste = ifnull(@taskr_nohaste,'p_task_start');
		call p_task_stop( othertask, 0 );
	else
		set @res1 = UNIX_TIMESTAMP(), @error = NULL;
	end if;

    update t_user set activeTask = task_id
    	where id = @taskr_user;
    
	update t_task
	  set lastStarted = @res1, lastStopped = 0, updated = NULL
	    where id = task_id and userId = @taskr_user;

	if ROW_COUNT() <= 0  then
	    call p_myexception('p_task_start: no tasks updated');
	else
		set @taskr_task = task_id;
	end if;
	if @taskr_nohaste = 'p_task_start' then set @taskr_nohaste = NULL; end if;
	
end //

/**
 * @todo inline documenting
 */
create procedure pv_tasks 
(
    what    char(3),    -- liv, act, fut, tod, ove, fin, arc
    project integer unsigned
)
begin
    declare bdone tinyint default 0;
    declare vdone tinyint default FALSE;
    declare varch tinyint default FALSE;
    declare t_horiz datetime default date_add(now(), interval 1 day);
    declare today datetime default Day(Now());

    if @taskr_user is NULL then
        call p_myexception("pv_tasks(): user context is missing");
        set bdone = TRUE;
    else
    	set @error = NULL;
   	
		case what
		when 'act' then set varch = FALSE;
		when 'fut' then set varch = FALSE;
		when 'tod' then set varch = FALSE;
		when 'ove' then set varch = FALSE;
		when 'liv' then set varch = FALSE;
		when 'fin' then
			select * from v_tasks_fini;
			set bdone = TRUE;
		when 'arc' then
			select * from v_tasks_arch;
			set bdone = TRUE;
		else set @error = concat('illegal mode: "', what,'"' );
		end case;
		
		if @error is not NULL then
			call p_myexception(concat('pv_tasks(): ', @error));
        	set bdone = TRUE;
		end if;
    end if;
    
    if not bdone then
		select
			id, userId, projectId, title, duration,
			unix_timestamp(liveline) as liveline,
			unix_timestamp(deadline) as deadline,
			added, lastStarted, lastStopped, scrap
		  from t_task
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
     end if;
end //


delimiter ;

/** 
 * Upcoming tasks (excluding the active task)
 * @todo checks, documenting
 */
create view v_tasks_fini (
        id, userId, projectId, title, duration,
        liveline,
        deadline,
        added, lastStarted, lastStopped, scrap
)
as select
        id, userId, projectId, title, duration,
        unix_timestamp(liveline) as liveline,
        unix_timestamp(deadline) as deadline,
        added, lastStarted, lastStopped, scrap
  from t_task
    where userId = f_user_id() and flags >= 8 and flags < 16
    order by lastStopped ASC;


create view v_tasks_arch (
        id, userId, projectId, title, duration,
        liveline,
        deadline,
        added, lastStarted, lastStopped, scrap
)
as select
        id, userId, projectId, title, duration,
        unix_timestamp(liveline) as liveline,
        unix_timestamp(deadline) as deadline,
        added, lastStarted, lastStopped, scrap
  from t_task
    where userId = f_user_id() and flags >= 16
    order by lastStopped ASC;

