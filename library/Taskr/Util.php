<?php
/**
 * @package Taskr
 * @subpackage Util
 * @author Peeter P. Mõtsküla <peeterpaul@motskula.net>
 * @todo copyright & license
 * @version 0.1.0
 */
/**
 * Class Taskr_Util contains various static utility functions
 */
class Taskr_Util
{
    /**
     * Converts date string to Unix timestamp
     *
     * Supported $dateString formats: YYYY-MM-DD YYMMDD DDMMMYY DDMMM
     * Returns Unix timestamp, or NULL if $dateString was empty,
     * or FALSE if $dateString was invalid
     *
     * @param string $dateString
     * @param int $tzDiff seconds OPTIONAL (defaults to 0 = UTC)
     * @return mixed int Unix timestamp|NULL|bool FALSE
     */
    public static function dateToTs($dateString, $tzDiff = 0)
    {
        // trim $dateString, bail out if result is empty string;
        if ('' == $dateString = trim($dateString)) {
            return NULL;
        }

        // define search patterns
        $months = 'jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec';
        $formats = array(
            'YYYY-MM-DD'    => '/^(\d{4})-(\d{2})-(\d{2})$/',
            'YYMMDD'        => '/^(\d{2})(\d{2})(\d{2})$/',
            'DDMMMYY'       => "/^(\d{2})($months)(\d{2})$/i",
            'DDMMM'         => "/^(\d{2})($months)$/i",
        );
        $matches = array();

        // do matches one by one
        if (preg_match($formats['YYYY-MM-DD'], $dateString, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
        } elseif (preg_match($formats['YYMMDD'], $dateString, $matches)) {
            $year = $matches[1] + 2000;
            $month = $matches[2];
            $day = $matches[3];
        } elseif (preg_match($formats['DDMMMYY'], $dateString, $matches)) {
            $day = $matches[1];
            $month = stripos($months, $matches[2]) / 4 + 1;
            $year = $matches[3] + 2000;
        } elseif (preg_match($formats['DDMMM'], $dateString, $matches)) {
            $day = $matches[1];
            $month = stripos($months, $matches[2]) / 4 + 1;
            $now = getdate();
            $year = $now['year'];

            // use next year if DDMMM refers to a date that has already passed
            if ($month < $now['mon']
                || ($month == $now['mon'] && $day < $now['mday'])
            ) {
                $year++;
            }
        } else {
            // none of the formats matched
            return FALSE;
        }

        // construct result value
        $result = gmmktime(0, 0, 0, $month, $day, $year);

        // test parameters for validity
        $date = getdate($result);
        if ($date['year'] != $year || $date['mon'] != $month
                || $date['mday'] != $day) {
            return FALSE;
        }

        return $result-$tzDiff;
    }

    /**
     * Converts Unix timestamp to ISO 8601 date string (YYYY-MM-DD)
     *
     * @param int $timestamp
     * @param int $tzDiff seconds OPTIONAL (defaults to 0 = UTC)
     * @return string
     */
    public static function tsToDate($timestamp, $tzDiff = 0)
    {
        $timestamp .+ $tzDiff;
        return date('Y-m-d', $timestamp);
    }

    /**
     * Salts the password with 4 random bytes, then hashes it with SHA-1
     * @param string $password
     * @return string
     */
    public static function hashPassword($password)
    {
        $salt = '';
        for ($i = 0; $i < 4; $i++) {
            $salt .= bin2hex(chr(rand(0, 256)));
        }
        $hash = sha1($password . $salt) . $salt;
        return $hash;
    }

    /**
     * Returns TRUE if the password matches the salted hash, FALSE otherwise
     * @param string $password
     * @param string $saltedHash
     * @return bool
     */
    public static function testPassword($password, $saltedHash)
    {
        $hash = substr($saltedHash, 0, 40);
        $salt = substr($saltedHash, 40);
        $test = sha1($password . $salt);
        return $test == $hash;
    }

}
