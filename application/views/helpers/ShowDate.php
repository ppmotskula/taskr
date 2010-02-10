<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 *
 */
/**
 * View helper: showDate
 */
class Zend_View_Helper_ShowDate
{
    /**
     * Formats a timestamp, honoring user preferences if set
     *
     * @param int $timestamp Unix timestamp
     * @return string default timezone: UTC, default date format 'Y-m-d'
     */
    public function showDate($timestamp)
    {
        // return empty string if timestamp wasn't set
        if (0 == $timestamp) {
            return '';
        }

        // initialize formatting parameters
        $tzDiff = 0;
        $dateFormat = 'Y-m-d';
        if (isset($this->view->user)) {
            $user = $this->view->user;
            if (isset($user->tzDiff)) {
                $tzDiff = $user->tzDiff;
            }
            if (isset($user->dateFormat)) {
                $dateFormat = $user->dateFormat;
            }
        }

        return date($dateFormat, $timestamp + $tzDiff);
    }
}
