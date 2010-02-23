<?php
/**
 * @package My
 * @subpackage RMO (Resident Model Objects)
 * @author Villem Alango <valango@gmail.com>
 * @copyright Copyright (c) Villem Alango <valango@gmail.com>
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 */

/**
 * @see My_RmoInterface
 */
require_once 'My/RmoInterface.php';

/**
 * My_RmoAbstract adds interface for communicating with RMO Manager.
 * @uses Zend_Log priority constants
 * @uses Zend_Registry to find object labeled as "logger"
 * @todo application configuration check to authomaticaly activate in dev. mode.
 */
abstract class My_RmoAbstract extends My_MagicAbstract implements My_RmoInterface
{
    protected static $_dispatchPlugin;

   /**
    * Class name for RMO manager to know
    */
    public function getClass()
    {
        return get_class($this);
    }
    
   /**
    * Object identity string for RMO manager to know
    */
    public function getIdentity()
    {
        return '#' . $this->id;
    }
    
   /**
    * Object status string for RMO manager to know
    */
    public function getStatus()
    {
        return '';
    }
    
   /**
    * Get dispatcher object
    */
    public function getDispatcher()
    {
        return self::$_dispatchPlugin;
    }
    
   /**
    * Set dispatcher object
    */
    protected function _setDispatcher( $what, $parameters = NULL )
    {
        $previous = $this->getDispatcher();

        self::$_dispatchPlugin = $what; return $previous;
    }
    
   /**
    * Do actual dispatching
    */
    protected function _dispatch( $what, $parameters = NULL )
    {
        if ( self::$_dispatchPlugin )
        {
            return self::$_dispatchPlugin->dispatch( $this, $what, $parameters );
        }
    }
    
   /**
    * Do actual dispatching
    */
    public static function dispatchMsg( $class, $what, $parameters = NULL )
    {
        if ( self::$_dispatchPlugin )
        {
            return self::$_dispatchPlugin->dispatch( $class, $what, $parameters );
        }
    }
}
