<?php

class SimulatorController extends Pub_Controller_Action
{
    public function indexAction()
    {
        if ($this->_request->isPost()) {
            $this->_forward('process');
            return true;
        }
    }

    public function processAction()
    {
        $params = $this->_request->getPost();
        unset($params['action']);

        /* Validate fields. */
        $errors = array();
        if (isset($params['password']) && $params['password'] == '')
            $errors['password'] = 'Password is required.';

        if ($params['merId'] == '')
            $errors['merId'] = 'Merchant Id is required.';

        /* Validate amount field. */
        if ($params['amount'] == '') {
            $errors['amount'] = 'Amount is required.';
        } else {
            $amount = floatval($params['amount']);
            if (!is_float($amount)) {
                $errors['amount'] = 'Invalid amount.';
            }
        }

        /* Validate order id field. */
        if ($params['orderId'] == '')
            $errors['orderId'] = 'Order Id is required.';

        /* If validation errors exists, display initial form with errors. */
        if (is_array($errors) && count($errors) > 0) {
            $messages = array('Correct the errors below.');
            $this->view->errors   = $errors;
            $this->view->messages = $messages;
            $this->view->params   = $params;
            $this->render('index');
            return false;
        }

        /* Identify processor method. */
        $action = strtolower($params['vendor']) . '-' . strtolower($params['method']);

        $this->_forward($action);
        return true;
    }

    public function bmlRedirectAction()
    {
        $params = $this->_request->getPost();
        $params['purchaseAmount']   = $this->prepareAmount($params['amount']);
        $params['purchaseCurrency'] = $this->prepareCurrency($params['currency']);
        $params['merRespUrl'] =
            'https://' . $_SERVER['SERVER_NAME'] .
            $this->_request->getBaseUrl() . '/simulator/response';
        $params['signature'] = $this->calculateSignature(
            $params['password'],
            $params['merId'],
            '407387',
            $params['orderId'],
            $params['purchaseAmount'],
            $params['purchaseCurrency']
        );

        /* Store request information in session for the current request. */
        $requestns = new Zend_Session_Namespace('request');
        $requestns->vendor   = $params['vendor'];
        $requestns->method   = $params['method'];
        $requestns->merId    = $params['merId'];
        $requestns->amount   = $params['amount'];
        $requestns->currency = $params['currency'];
        $requestns->orderId  = $params['orderId'];
        $requestns->password = $params['password'];

        $this->view->params = $params;
    }

    public function responseAction()
    {
        /* First, get the current post parameters. */
        $params = $this->_request->getPost();

        /* Get preceding request information from the session. */
        $requestns = new Zend_Session_Namespace('request');

        $this->view->initialParams = $requestns->getIterator()->getArrayCopy();
        $this->view->responseParams = $params;
    }

    private function prepareCurrency($mnemonic)
    {
        $mnemonic = strtoupper($mnemonic);
        $currencies = array(
            'MVR' => 462,
            'USD' => 840);

        if (!array_key_exists($mnemonic, $currencies)) {
            throw new Exception('Invalid currency specified.');
        }

        return $currencies[$mnemonic];
    }

    private function prepareAmount($amount)
    {
        $amount = number_format($amount, 2, '.', null);
        $amount = str_replace('.', '', $amount);
        $amount = str_pad($amount, 12, '0', STR_PAD_LEFT);
        return $amount;
    }

    private function calculateSignature($password, $merchantId, $acquirerId, $orderId, $amount, $currency)
    {
        $hash = $password . $merchantId . $acquirerId . $amount . $currency;
        $hash = sha1($hash, true);
        return base64_encode($hash);
    }
}
