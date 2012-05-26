<?php

class CheckoutController extends Pub_Controller_Action
{
    public function indexAction()
    {
        if (!$this->_request->isPost()) {
            echo 'Processing Failed Due To Unexpected Error. Further processing is not allowed.';
            exit;
        }

        $params = $this->_request->getPost();
        $this->dump($params, true);
    }
}
