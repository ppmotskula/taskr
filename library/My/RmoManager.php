<?php
/**
 * @package My
 * @subpackage RMO (Resident Model Objects)
 * @author Villem Alango <valango@gmail.com>
 * @copyright Copyright (c) Villem Alango <valango@gmail.com>
 * @todo license 
 * @version 0.1.0
 */

/**
 * Manage RMO compliant objects and non-object values.
 *
 * RELEASE NOTE: currently, all operations work directly with DB; cache disabled.
 *
 * collectionKey:
 *   <any_legal_identifier>['!'] - exclamation mark means 'no buffering'
 *
 * @todo perhaps it could be a RMO object itself.
 * @todo inline comments
 */
class My_RmoManager
{
    protected $_tree = array();
    
    protected $_lists = array();
    
    /**
     * access database directly, without looking to the cache.
     */
    protected $_directDb;
    
    /**
     * search cache, store database read results to cache
     */
    protected $_useCache;
    
    public function __construct( $definitions)
    {
        if ($definitions) {
            $this->_lists = $definitions;
        }else{
           throw new exception('RMO');
        }
        $this->_directDb = TRUE;
        $this->_useCache = FALSE;
    }
    
    /**
     * @return mixed|NULL
     */
    protected static function _find( $key, array $collection )
    {
        if (array_key_exists($key, $collection)) {
            return $collection[$key];
        }
    }
    
    /**
     * Retrieve the contents of the list.
     *
     * check if the list contains of sublists
     * For an empty list, return NULL instead
     */
    public function loadList( $listKey, $params = NULL )
    {
        $result = array();
        
        if ( NULL !== ($lists = self::_find($listKey, $this->_lists)) ) {
            foreach( $lists as $sublist ) {
                if ( NULL !== ($res = $this->loadList( $sublist, $params )) ) {
                    array_splice($result, count($result)-1, 0, $res);
                }
            }
        } else {                        // do the actual work here
            $result = $this->_loadList( explode( '.', $listKey ), $params );
        }
        if ( !count($result) ) { $result = NULL; }
        return $result;
    }
    
	/**
	 * Internal part of list loader
	 */
    protected function _loadList( $keys, $args )
    {
        if ( $this->_directDb || NULL === ($res = $this->_walk( $keys )) )
        {
            $result = self::_dm()->loadItems( $keys, $args );
            
            if ( NULL !== $result  &&  $this->_useCache ) {
                $this->_walk( $keys, TRUE, $result ); 
            }
            
        }
        return $result;
    }
    
    protected function _walk( array $keys, $set = FALSE, $val = NULL )
    {
    	$node = &$this->_tree; $i = count($keys);
    	
        foreach( $keys as $k ) {
            $i--;
            if ( !array_key_exists($k, $node) ) {
                if( !$set ) { return NULL; }
                if( $i == 0 ) {
                    $node[$k] = $val;
                } else {
                    $node[$k] = array();
                }
            }
            $node = &$node[$k];
        } 
        return $node;
    }

    protected static function _dm()   // just save some typing ... ;)
    {
        return Taskr_Model_DataMapper::getInstance();
    }
    
    /* 
    private function _callbackUcase( &$item )
    {
    	if ( is_string($item) ) { $item = strtoupper( $item ); }
    }
    */ 

    
}
