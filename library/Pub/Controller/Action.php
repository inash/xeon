<?php

class Pub_Controller_Action extends Zend_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->view->baseUrl = $this->_request->getBaseUrl();

        /* Set flash messages. */
        if ($this->_helper->flashMessenger->hasMessages()) {
            $this->view->messages = $this->_helper->flashMessenger->getMessages();
        }
    }

    protected function dump($var, $exit = false)
    {
        Zend_Debug::dump($var);
        if ($exit === true) exit;
    }
}
