<?php

class Zend_View_Helper_ShowScrap
{

    public function showScrap($scrap)
    {
        $result =
            '<div class="scrap">' . "\n" .
            My_LinkFinder::parseText($scrap) .
            '</div> <!-- /scrap -->' . "\n"
        ;
        return $result;
    }
}
