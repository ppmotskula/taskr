<?php
/**
 * @package My
 * @subpackage Magic
 * @author Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @copyright Copyright © 2010 Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 */
/**
 * Flexible magic overloader class without any hardcoded magic properties
 */
class My_MagicFlex extends My_MagicAbstract
{
    /**
     * @ignore (internal)
     * var array This is the container for magic properties
     */
    protected $_magic = array();
}
