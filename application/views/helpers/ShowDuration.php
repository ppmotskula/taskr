<?php

class Zend_View_Helper_ShowDuration
{

    public function showDuration($seconds)
    {
        $hours = (int) ($seconds/3600);
        $seconds -= 3600 * $hours;
        if ($hours < 10) {
            $hours = "0$hours";
        }

        $minutes = (int) ($seconds/60);
        $seconds -= 60 * $minutes;
        if ($minutes < 10) {
            $minutes = "0$minutes";
        }

        if ($seconds < 10) {
            $seconds = "0$seconds";
        }

        return "$hours:$minutes:$seconds";
    }
}
