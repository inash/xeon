<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    public function _initAutoloader()
    {
        $loader = Zend_Loader_Autoloader::getInstance();
        $loader->registerNamespace('Pub');
    }

    public function _initSession()
    {
        Zend_Session::start();

    }

    public function _initRoutes()
    {
        $this->bootstrap('frontController');
        $fc = $this->getResource('frontController');
        $router = $fc->getRouter();

        $router->addRoute('redirect-link', new Zend_Controller_Router_Route(
            'SENTRY/PaymentGateway/Application/RedirectLink.aspx', array(
                'controller' => 'checkout',
                'action'     => 'index'
            )
        ));
    }
}
