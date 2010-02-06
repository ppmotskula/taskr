<?php
/**
 * @package Taskr
 * @author Peeter P. Mõtsküla <ppm@taskr.eu>
 * @todo copyright & license
 * @version 0.1.0
 */
/**
 * Index controller
 */
class IndexController extends Zend_Controller_Action
{
    /**
     * If the user is logged in, forwards to Task controller;
     * otherwise, the welcome page will be shown.
     */
    public function indexAction()
    {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $this->_helper->Redirector->gotoSimple('index', 'task');
        }
    }

}

