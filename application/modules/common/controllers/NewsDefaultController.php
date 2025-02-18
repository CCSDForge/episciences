<?php

class NewsDefaultController extends Episciences_Controller_Action
{
	
	public function indexAction()
	{
		if (Episciences_Auth::isSecretary()) { #git 235
        	$this->view->canEdit = true;
		}
		
		$news = new Episciences_News(); 
		$this->view->news = $news->getListNews();
		
	}
		
}