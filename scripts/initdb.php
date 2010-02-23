<?php
/**
 * Database initializer script
 *
 * @author-of-modification Villem Alango <valango@gmail.com>
 *
 * Differences from original design:
 * - new command line options;
 * - should work with most of DBMS platforms supported by PDO
 *
 * Right now, the only file-based database it recognizes is sqlite (see: $is_file)
 *
 * If database file or a logical database doe not exist, it will be created.
 *
 * @mod 100209: sql scripts defaulting added, eg.:
 * - first check for "schema.sqlite.sql" and if not found, then use "schema.sql";
 *   similar rule applies to data.sql files.
 */
 
function execute_sql( $dbAdapter, $textbuffer, $mode, $sep = ";\n" )
{
    if( strpos($mode,'b') ) {
        if( strpos($mode,'v') ) { echo "****** SQL batch\n"; }
        if( !strpos($mode,'n') ) {
            $dbAdapter->getConnection()->exec($textbuffer);
        }
        return;
    }
	$statements = explode($sep, $textbuffer);
    foreach( $statements as $sql ){
      	if( !(''==rtrim($sql)) ){
            $sql .= $sep; $v = '';
      	    if( strpos($mode,'v') ) { echo '****** ' . $sql; }
      	    if( !strpos($mode,'n') ) { 
        	    if( !($v = $dbAdapter->getConnection()->query($sql)) ){
        	        if( strpos($mode,'v') ){
                        $v = 'FAILED!';
                    }else{
        	            throw new Exception("*** Query failed: $sql");
        	        }
        	    }
        	}
        	if( strpos($mode,'v') ){ echo " ==> $v \n"; }
        }
    }
}

function read_file( $prefix, $dbtype, $mode )
{
	$path = dirname(__FILE__) . '/';
    $file = $prefix . '.' . $dbtype . '.sql';
    if( !file_exists($path . $file) ) {
    	$file2 = $prefix . '.sql';
        if( !file_exists($path . $file2) ) {
            throw new Exception("Neither '$file' nor '$file2' not found!");
        }
        $file = $file2;
    }
    if( !strpos($mode,'s') ){
        echo "********** READING FILE: $file\n";
    }
	return file_get_contents($path . $file);
}

// Initialize the application path and autoloading
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));
set_include_path(implode(PATH_SEPARATOR, array(
    APPLICATION_PATH . '/../library',
    get_include_path(),
)));
require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance();

// Define some CLI options
$getopt = new Zend_Console_Getopt(array(
    'noaction|n' => 'Database contents will not be altered in any way',
    'verbose|v'  => 'Dump lot of diagnostics',
    'silent|s'   => 'Silent mode - does not override verbosity, but skips warning countdown',
    'env|e-s'    => 'Application environment for which to create database (defaults to development)',
    'withdata|w' => 'Load database with sample data',
    'help|h'     => 'Help -- usage message',
));
try {
    $getopt->parse();
} catch (Zend_Console_Getopt_Exception $e) {
    // Bad options passed: report usage
    echo $e->getUsageMessage();
    return false;
}

// If help requested, report usage message
if ($getopt->getOption('h')) {
    echo $getopt->getUsageMessage();
    return true;
}

// Initialize values based on presence or absence of CLI options
$withData = $getopt->getOption('w');
$noaction = $getopt->getOption('n');
$verbose  = $getopt->getOption('v');
$env      = $getopt->getOption('e');
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (null === $env) ? 'development' : $env);

$silent   = ('testing' == APPLICATION_ENV) ? true : $getopt->getOption('s');

$mode = ' ' . ($noaction ? 'n' : '') . ($verbose ? 'v' : '') . ($silent ? 's' : '');

// Initialize Zend_Application
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

$config = $application->getOptions();
$dbtype = $config['resources']['db']['adapter'];
$dbname = $config['resources']['db']['params']['dbname'];

if( 'PDO_' == substr($dbtype,0,4) ){
    $dbtype = substr($dbtype,4);
}
$dbtype = strtolower($dbtype);

if (($is_file = (strlen( dirname($dbname) ) > 1))) {  // true, if this is a single-file DB
    if ( $dbtype === 'sqlite' ) {
        $mode .= 'b';		// execute sql in batch mode
    }
}

if( !$is_file ){
    // Because we can't expect the database existing,
    // we have to get rid off it's name before we connect
    $config['resources']['db']['params']['dbname'] = '';
    $application = new Zend_Application( APPLICATION_ENV, $config);
}
// Initialize and retrieve DB resource
$bootstrap = $application->getBootstrap();
$bootstrap->bootstrap('db');
$dbAdapter = $bootstrap->getResource('db');

// let the user know whats going on (we are actually creating a 
// database here)
if( !(true===$silent) ){ 
	if( true===$verbose ){
	    // Zend_Debug::dump($application->getOptions(), 'APP-OPTIONS');
	    // Zend_Debug::dump($dbAdapter, '$dbAdapter');
        echo 'APPLICATION_PATH: ' . APPLICATION_PATH . "\n";
        echo 'APPLICATION_ENV : ' . APPLICATION_ENV . "\n";
	}
    echo 'SERVER TYPE     : ' . $dbtype . "\n";
    echo 'DATABASE        : ' . $dbname . "\n";
}

if( !(true===$silent) && !(true===$noaction) ){
    echo '*** Will write Database in (control-c to cancel): ' . PHP_EOL;
    for ($x = 5; $x > 0; $x--) {
        echo ' ' . $x . " seconds\r"; sleep(1);
    }
    echo "                \n";
}

// this block executes the actual statements that were loaded from 
// the schema file.
try {
    if( $is_file ){
        if( file_exists($dbname) ) {
            if( $verbose ) { echo "****** DELETING: $dbname \n"; }
            if( !$noaction ) { unlink($dbname); }
        }
        $use_stmt = $buffer = '';
    }else{
        $use_stmt =  'USE ' . $dbname . ";\n";
        $buffer = 'CREATE DATABASE IF NOT EXISTS ' . $dbname . ";\n" . $use_stmt;
    }
    
    $buffer .= read_file('schema', $dbtype, $mode);

    // use the connection directly to load sql in batches
    execute_sql( $dbAdapter, $buffer, $mode );
    if( !$noaction ) {
        if( $is_file ){
            chmod(dirname($dbname), 0777);
            chmod($dbname, 0666); 
        }
	}
    if ( !(true===$silent) ) {
        echo 'Database Created';
        echo PHP_EOL;
    }
    
    if ($withData) {
        $buffer = read_file('data', $dbtype, $mode);
        execute_sql( $dbAdapter, $buffer, $mode );
        if ( !$silent ) {
            echo 'Data Loaded.';
            echo PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo 'AN ERROR HAS OCCURED:' . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    return false;
}

// generally speaking, this script will be run from the command line
return true;

