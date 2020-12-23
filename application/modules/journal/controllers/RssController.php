<?php

class RssController extends Zend_Controller_Action
{
	public function init()
	{
		$params = $this->getRequest()->getParams();
		if (!array_key_exists('max', $params) || !is_numeric($params['max'])) {
			$page = new Episciences_Website_Navigation_Page_Rss;
			$page->load();
			$params['max'] = $page->getNbResults();
		}
		if (method_exists($this, $params['action'] . 'Action')) {
			$feeds = new Episciences_Rss($params);
		}
	}
	
	public function indexAction()
	{
		
	}
	
	public function papersAction()
	{
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
	}
	
	public function newsAction()
	{
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
	}
	
}