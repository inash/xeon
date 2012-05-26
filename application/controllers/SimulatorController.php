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

        if ($params['merchantId'] == '')
            $errors['merchantId'] = 'Merchant Id is required.';

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

        /* Preset required parameters. */
        $amount     = $this->prepareAmount($params['amount']);
        $currency   = $this->prepareCurrency($params['currency']);
        $merRespUrl = 'https://' . $_SERVER['SERVER_NAME'] .
                      $this->_request->getBaseUrl() . '/simulator/response';

        /* Calculate signature. */
        $signature  = $this->calculateSignature(
            $params['password'], $params['merchantId'], '407387',
            $params['orderId'], $amount, $currency
        );

        /* Prepare request post parameters. */
        $request = array(
            'version'          => '1.0.0',
            'merchantId'       => $params['merchantId'],
            'acquirerId'       => '407387',
            'purchaseAmount'   => $amount,
            'purchaseCurrency' => $currency,
            'purchaseCurrencyExponent' => 2,
            'orderId'    => $params['orderId'],
            'merRespUrl' => $merRespUrl,
            'signature'  => $signature
        );

        /* Store request information in session for the current request. */
        $requestns = new Zend_Session_Namespace('request');
        $requestns->unsetAll();
        foreach ($request as $key => $value) {
            $requestns->__set($key, $value);
        }

        /* Store initial post parameters to session. */
        $postns = new Zend_Session_Namespace('requestOriginal');
        $postns->unsetAll();
        foreach ($params as $key => $value) {
            $postns->__set($key, $value);
        }

        /* Set parameters to the view. */
        $this->view->params = $request;
    }

    public function bmlDirectAction()
    {
        $params = $this->_request->getPost();

        /* Preset required parameters. */
        $amount     = $this->prepareAmount($params['amount']);
        $currency   = $this->prepareCurrency($params['currency']);
        $merRespUrl = 'https://' . $_SERVER['SERVER_NAME'] .
                      $this->_request->getBaseUrl() . '/simulator/response';

        /* Calculate signature. */
        $signature  = $this->calculateSignature(
            $params['password'], $params['merchantId'], '407387',
            $params['orderId'], $amount, $currency
        );
        /* Prepare request params. */
        $request = array(
            'Version' => '1.0.0',
            'MerID'   => $params['merchantId'],
            'AcqID'   => '407387',
            'PurchaseAmt' => $amount,
            'PurchaseCurrency' => $currency,
            'PurchaseCurrencyExponent' => 2,
            'OrderID'     => $params['orderId'],
            'Signature'   => $signature,
            'CardNo'      => $params['cardNumber'],
            'CardExpDate' => $params['cardExpiry'],
            'CardCVV2'    => $params['cardCvv2']
        );

        /* Store request information in session for the current request. */
        $requestns = new Zend_Session_Namespace('request');
        $requestns->unsetAll();
        foreach ($request as $key => $value) {
            $requestns->__set($key, $value);
        }

        /* Store initial post parameters to session. */
        $postns = new Zend_Session_Namespace('requestOriginal');
        $postns->unsetAll();
        foreach ($params as $key => $value) {
            $postns->__set($key, $value);
        }

        /* Send request. */
        $client = new Zend_Http_Client();
        $client->setUri('https://testgateway.bankofmaldives.com.mv/SENTRY/PaymentGateway/Application/DirectAuthzLink.aspx');
        $client->setMethod(Zend_Http_Client::POST);
        $client->setParameterPost($request);

        $response = $client->request();
        $body = $response->getBody();
        $responseParams = array();
        parse_str(html_entity_decode($body), $responseParams);

        $_POST = array();
        $this->_request->setPost($responseParams);
        $this->_forward('response');
        return true;
    }

    public function responseAction()
    {
        /* First, get the current post parameters. */
        $params = $this->_request->getPost();

        /* Prepare filter for inflection. */
        $filter = new Zend_Filter_Inflector(':param');
        $filter->setRules(array(
            ':param' => array('Word_CamelCaseToDash', 'StringToLower')
        ));

        /* Get preceding request information from the session. */
        $requestns = new Zend_Session_Namespace('request');
        $requestParamsArray = $requestns->getIterator()->getArrayCopy();

        /* Inflect request params. */
        $requestParams = array();
        foreach ($requestParamsArray as $key => $value) {
            $param = $filter->filter(array('param' => $key));
            $param = str_replace('-', ' ', $param);
            $param = ucwords($param);
            $requestParams[$param] = $value;
        }

        /* Get preceding post request information from the session. */
        $postns = new Zend_Session_Namespace('requestOriginal');

        /* Inflect response params. */
        $responseParams = array();
        foreach ($params as $key => $value) {
            $param = $filter->filter(array('param' => $key));
            $param = str_replace('-', ' ', $param);
            $param = ucwords($param);
            $responseParams[$param] = $value;
        }

        $this->view->requestParams  = $requestParams;
        $this->view->postParams     = $postns->getIterator()->getArrayCopy();
        $this->view->responseParams = $responseParams;
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
        $hash = $password . $merchantId . $acquirerId . $orderId . $amount . $currency;
        $hash = sha1($hash, true);
        return base64_encode($hash);
    }
}
