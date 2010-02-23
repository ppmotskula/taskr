<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    /**
     * Temporary hub, necessary in development environment only (if at all)
     */
	protected function _initDebug()
	{
	    if ( APPLICATION_ENV == 'development' ) {
            assert_options(ASSERT_ACTIVE,   true);
            assert_options(ASSERT_BAIL,     true);
        }
        
        If ( 1 ) { return; }
        
     /******* TEST HUB for arbitrary code BELOW THIS LINE ******/
        
        die('');
	}

}

