<?php
/**
 * @package My
 * @subpackage Dbg
 * @author Villem Alango <valango@gmail.com>
 * @copyright Copyright (c) Villem Alango <valango@gmail.com>
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 */
 
/**
 * Class My_Logger supports application logging and debugging.
 * @uses Zend_Log priority constants
 * @uses Zend_Registry to find object labeled as "logger"
 * @todo application configuration check to authomaticaly activate in dev. mode.
 */
class My_Dbg
{
    protected static $_enabled;
    
    protected static $_noTrace = array();     // names classes we dont want to trace
    
    protected static $_logger;
    
    /**
     * Initiate tracer explicitly
     * @param string | Zend_Log
     */
    public static function init( $logger )
    {
        self::_init( self::_start( $logger ) );
    }
    
    /**
     * Enable tracing.
     */
    public static function enable()
    {
        self::_init(TRUE); 
    }
    
    /**
     * Disable tracing
     */
    public static function disable()
    {
        self::_init(FALSE); 
    }
    
    /**
     * General logging function
     *
     * @param string $text a message to be logged
     * @param int $priority OPTIONAL (defaults to Zend_Log::INFO)
     * @return boolean TRUE if logging was enabled
     */
    public static function log( $text, $priority = Zend_Log::INFO )
    {
        if ( !self::_check() ) { return FALSE; }
        
        self::$_logger->log($text,$priority);
        return TRUE;
    }
    
    /**
     * Enable, disable or enquiry class tracing mode
     * @param array | string | object $class
     * @param int $mode negative: just enquiry, 0: disable, >0: enable
     * @return boolean TRUE if traceing was on, undefined if array was supplied
     */
    public static function setClassTracing( $classes, $mode = -1 )
    {
        if ( is_string($classes) ) { $classes = explode(' ', $classes); }
        
        foreach( $classes as $class ) {
            if ( !is_string($class) ) { $class = get_class($class); }
            $ban = array_key_exists( $class, self::$_noTrace )
                   &&  self::$_noTrace[$class];
                   
            if ( $mode >= 0 ) { self::$_noTrace[$class] = !$mode; }
        }
        return !$ban;
    }
    
    /**
     * Dump any value
     */
    public static function dump( $value, $name = '' )
    {
        //if ( !self::_check() ) { return FALSE; }
        $type = gettype($value);
        return self::log( "{$type}:{$name} <" . print_r($value,TRUE) . '>' );
    }
    
    /**
     * Trace execution
     * @todo: execution stack pretty-printing
     */
    public static function trc( $class, $method = '', $params = NULL )
    {
        if ( !self::_check()
          || (array_key_exists( $class, self::$_noTrace )
              &&  self::$_noTrace[$class]) ) {
            return;
        }
        
                 // **** kui on vaja jälgida mingi asja käivituskonteksti...
        if ( FALSE  &&  $method == '!_findUser' ) {
            $data = new Zend_Exception( 'My_Dbg_hook' );
        } else {
            $data = "#====> {$class}::{$method}("; $sep = '';
            for( $i = 2; $i < func_num_args(); $i++ ) {
                $data .= $sep . func_get_arg($i); $sep = ',';
            }
            $data .= ')';
        }        
        My_Dbg::log( $data );
    }
    
    
    /**
     * check if the logger is good and write a startup message
     */
    protected static function _start( $logger )
    {
        try {
            if ( is_string( $logger ) ) {
                $writer = new Zend_Log_Writer_Stream($logger);
                $logger = new Zend_Log($writer);
            }
            $logger->log('**** PHP script started ****', Zend_Log::INFO);
        } catch (exception $e) {
            return NULL;
        }
        self::$_logger = $logger;
        return TRUE;
    }
    
    protected static function _init( $yes )
    {
        if (  NULL === self::$_logger )
        {
            if ( $yes ) 
            {   
                if ( Zend_Registry::isRegistered('logger') ) {
                    $logger = Zend_Registry::get('logger');
                }
                else {
                    $logger = APPLICATION_PATH . '/../../logs/log.txt';
                                                // or we have smth in configuration
                    $app = new Zend_Application(
                        APPLICATION_ENV,
                        APPLICATION_PATH . '/configs/application.ini' );
                
                    $config = $app->getOptions();
                    
                    if ( array_key_exists( 'tracer', $config ) ) {
                        $config = $config['tracer'];
                        if ( array_key_exists( 'path', $config ) ) {
                            $logger = $config['path'];
                        }
                    }
                }
                self::$_enabled = self::_start( $logger );
            }
        } else {
            self::$_enabled = $yes;
        }
    }
    
    /**
     * Initiate the subsystem if necessary and and check if tracing is enabled
     * @return boolean TRUE if enabled
     */
    protected static function _check()
    {
        if ( self::$_enabled === NULL ) {
            self::_init( APPLICATION_ENV == 'development' );
        }
        return self::$_enabled;
    }
    
    
}
