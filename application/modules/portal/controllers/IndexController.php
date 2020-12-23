<?php

class IndexController extends Zend_Controller_Action
{
	
	// Homepage
	public function indexAction()
	{
		$this->view->controller = 'index';
    	$this->_forward('index', 'page');
	}
	
	
}