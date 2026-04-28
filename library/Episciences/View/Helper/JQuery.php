<?php

/**
 * jQuery Helper. Functions as a stack for code and loads all jQuery dependencies.
 * CCSD : modified to add version number in url to avoid browser cache effects
 * @property Episciences_View_Helper_JQuery_Container _container
 */
class Episciences_View_Helper_JQuery extends ZendX_JQuery_View_Helper_JQuery
{
    /**
     * Initialize helper
     *
     * Retrieve container from registry or create new container and store in
     * registry.
     */
    public function __construct()
    {
        $registry = Zend_Registry::getInstance();
        if (!isset($registry[__CLASS__])) {
            require_once 'Episciences/View/Helper/JQuery/Container.php';
            $container = new Episciences_View_Helper_JQuery_Container();
            $registry[__CLASS__] = $container;
        }
        $this->_container = $registry[__CLASS__];
    }

}