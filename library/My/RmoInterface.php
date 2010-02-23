<?php
/**
 * @package My
 * @subpackage RMO (Resident Model Objects)
 * @author Villem Alango <valango@gmail.com>
 * @copyright Copyright (c) Villem Alango <valango@gmail.com>
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 * @todo documenting
 */
 
 

/**
 * My_Rmo_Interface is used by My_Rm_Repository
 */
interface My_RmoInterface
{
    public function getClass();
    
    public function getIdentity();
    
    public function getStatus();
}
