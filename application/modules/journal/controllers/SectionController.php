<?php

class SectionController extends Zend_Controller_Action
{

    public function indexAction ()
    {
        $this->_helper->redirector('list');
    }
    
    public function listAction () 
    {
    	if ($this->_helper->getHelper('FlashMessenger')->getMessages()) {
            $this->view->flashMessages = $this->_helper->getHelper('FlashMessenger')->getMessages();
        }

        $sections = Episciences_SectionsManager::getList();
        foreach ($sections as &$section) {
        	$section->loadSettings();
        }
        $this->view->sections = $sections;  
    }
    
    public function addAction()
    {
        $request = $this->getRequest();
        $form = Episciences_SectionsManager::getForm();
        
		if ($request->isPost() && array_key_exists('submit', $request->getPost())) {
			
			if ($form->isValid($request->getPost())) {
				
				$section = new Episciences_Section($form->getValues());
				$section->setSetting('status', $form->getValue('status'));
							    		
			    if ($section->save()) {
				    $message = '<strong>'.$this->view->translate("La nouvelle rubrique a bien été créée.").'</strong>';
				    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
				} 
				
				else {
				    $message = '<strong>'.$this->view->translate("La nouvelle rubrique n'a pas pu être créée.").'</strong>';
				    $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
				}
				
				$this->_helper->redirector('index', 'section');
            } 
            
            else {
                $message = '<strong>'.$this->view->translate("Ce formulaire comporte des erreurs.").'</strong>';
                $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                $this->view->form = $form;
            }
        }
        
		$this->view->form = $form;
    }
    
    public function editAction()
    {
        $request = $this->getRequest();
        $id = $request->getQuery('id');
                
        $section = Episciences_SectionsManager::find($id);
        if (empty($section)) {
            $this->_helper->redirector('add');
        }

        $section->loadSettings();
        $defaults = $section->getFormDefaults();
        $form = Episciences_SectionsManager::getForm($defaults);
        
        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {
        
            if ($form->isValid($request->getPost())) {
            	
            	$section->setOptions($form->getValues());
            	$section->setSetting('status', $form->getValue('status'));

                if ($section->save()) {
                    $message = '<strong>'.$this->view->translate("Vos modifications ont bien été prises en compte.").'</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                }
        
                else {
                    $message = '<strong>'.$this->view->translate("Les modifications n'ont pas pu être enregistrées.").'</strong>';
                    $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                }
        
                $this->_helper->redirector('index', 'section');
            }
        
            else {
                $message = '<strong>'.$this->view->translate("Ce formulaire comporte des erreurs.").'</strong>';
                $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                $this->view->form = $form;
            }
        }
        
        $this->view->form = $form;
        
    }
    
    public function deleteAction()
    {
        $request = $this->getRequest();
        
        $ajax = $request->getPost('ajax');
        $params = $request->getPost('params');
        $id = ($params['id']) ? $params['id'] : $request->getQuery('id');
                         
        $respond = Episciences_SectionsManager::delete($id);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
        echo $respond;
    }
    
    public function sortAction()
    {
        $request = $this->getRequest();
        $params = $request->getPost();
        $params['rvid'] = RVID;
         
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
    
        Episciences_SectionsManager::sort($params);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
    }
    
    // Affiche le formulaire d'assignation de rédacteurs à une rubrique
    // (utilisé dans un popover en ajax) 
    public function editorsformAction()
    {
    	$request = $this->getRequest();
    	$sid = $request->getPost('sid');
    	if (!$sid) {
    		return false;
    	}
    	
    	$section = Episciences_SectionsManager::find($sid);
    	$currentEditors = $section->getEditors();    		
    	$this->view->editorsForm = Episciences_SectionsManager::getEditorsForm($currentEditors);
    	 
    	$this->_helper->layout->disableLayout();
    }
    
    // Assignation de rédacteurs à une rubrique
    public function saveeditorsAction()
    {
    	$this->_helper->layout()->disableLayout();
    	$this->_helper->viewRenderer->setNoRender();
    
    	$request = $this->getRequest();
    	$sid = ($request->getPost('sid')) ? $request->getPost('sid'): $request->getParam('sid');
    	
    	if ($request->isPost()) {
    
    		// Rédacteurs nouvellement assignés
    		$submittedEditors = $request->getPost('editors');
    		$editors = ($submittedEditors) ? array_map('intval', $submittedEditors) : array();
    		
    		// Rédacteurs déjà assignés
    		$section = Episciences_SectionsManager::find($sid);    		
    		$currentEditors = ($section->getEditors()) ? array_keys($section->getEditors()) : array();
    		    		
    		// Tri des rédacteurs ajoutés des rédacteurs supprimés
    		$added = array_diff($editors, $currentEditors);
    		$removed = array_diff($currentEditors, $editors);
    		    		
    		if ($added) {
    			
    			// Enregistrement des nouveaux rédacteurs
    			$section->assign($added);
    			
    			// Envoi des mails ?
    			// Voir AdministratepaperController l.670
    		}
    		
    		if ($removed) {
    			// Enregistrement des suppressions de rédacteurs
    			$section->unassign($removed);
    		}
    		    
    		echo true;
    	}
    
    	return;
    }
    
    // Rendu partiel
    // Affiche les rédacteurs d'une section (sid en post)
    public function displayeditorsAction()
    {
    	$request = $this->getRequest();
    	$params = $request->getPost();
    	$sid = $params['sid'];
    	 
    	$section = Episciences_SectionsManager::find($sid);
    	$editors = $section->getEditors();
    	 
    	$this->view->editors = $editors;
    	$this->renderScript('section/editors_list.phtml');
    	$this->_helper->layout->disableLayout();
    }
    
    public function viewAction()
    {
    	$request = $this->getRequest();
    	$sid = $request->getParam('id');
    	 
    	if (!$sid || !is_numeric($sid)) {
    		$message = '<strong>'.$this->view->translate("Identifiant de la rubrique absent ou incorrect.").'</strong>';
    		$this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
    		$this->redirect('/browse/section');
    	}
    	 
    	$section = Episciences_SectionsManager::find($sid);
    	if (!$section) {
    		$message = '<strong>'.$this->view->translate("Cette rubrique n'existe pas.").'</strong>';
    		$this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
    		$this->redirect('/browse/section');
    	}


    	$section->getEditors();
    	 
    	$section->loadIndexedPapers();
    	 
    	$this->view->section = $section;
    }
    
}