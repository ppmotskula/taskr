<?php
/**
 * @package My
 * @subpackage Util
 * @author Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @copyright Copyright © 2010 Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @license http://opensource.org/licenses/bsd-license.html New BSD license
 * @version 0.1.0
 */
/**
 * Class My_Util contains various static utility functions
 */
class My_Util
{
    /**
     * Converts UTC date YYMMDD to Unix timestamp
     *
     * @since version 0.1.0
     * @param string $ymd format: YYMMDD
     * @return mixed Unix timestamp or FALSE
     */
    public static function ymdToTs(string $ymd)
    {
        $time = strptime($ymd, '%y%m%d');
        if (!$time) {
            return FALSE;
        }
        $ts = gmmktime(0, 0, 0,
            $time['tm_mon'] + 1, $time['tm_mday'], $time['tm_year'] - 100);
        return $ts;
    }

}
