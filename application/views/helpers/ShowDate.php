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
    public $view;

    /**
     * Formats a timestamp, honoring user preferences if set
     *
     * @param int $timestamp Unix timestamp, default timezone: UTC
     * @param string $dateFormat OPTIONAL, default 'Y-m-d'
     * @return string
     */
    public function showDate($timestamp, $dateFormat = 'Y-m-d')
    {
        // return empty string if timestamp wasn't set
        if (0 == $timestamp) {
            return '';
        }

        // use current user's tzDiff if possible
        $tzDiff = 0;
        if (isset($this->view->user)) {
            $tzDiff = $this->view->user->tzDiff;
        }

        return date($dateFormat, $timestamp + $tzDiff);
    }

    /**
     * initialises $this->view
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
}
