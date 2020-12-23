<?php

class Episciences_Website_Navigation_Page_Folder extends Episciences_Website_Navigation_Page
{
protected $_controller = 'folder';
    
    protected $_action = 'list';
        
    public function getAction()
    {
    	return $this->_action . $this->getPageId();
    }
    
} 