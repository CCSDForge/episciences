<?php

/**
 * Lien exterieur
 * @author yannick
 *
 */
class Episciences_Website_Navigation_Page_File extends Episciences_Website_Navigation_Page
{
	/**
	 * Page multiple
	 * @var boolean
	 */
	protected $_multiple = true;
	
    /**
     * Lien vers le fichier
     * @var string
     */
	protected $_src = '';
    
	/**
	 * Cible du lien
	 * @var string
	 */
    protected $_target = '';
    
   
    
    /**
     * intialisation des options de la page
     * @see Ccsd_Website_Navigation_Page::setOptions($options)
     */
    public function setOptions($options = array())
    {
    	foreach ($options as $option => $value) {
    		$option = strtolower($option);
    		switch($option) {
    			case 'src'   :   $this->setSrc($value);
    			break;
    			
    			case 'target'   :   $this->setTarget($value);
    			break;
    		}
    	}
    	parent::setOptions($options);
    }
    
    /**
     * Retourne le controller de la page (lien exterieur, pas de controller)
     * @see Ccsd_Website_Navigation_Page::getController()
     */
    public function getController()
    {
    	return '';
    }
    
    /**
     * Retourne l'action de la page (lien vers fichier)
     * @see Ccsd_Website_Navigation_Page::getController()
     */
    public function getAction() 
    {
    	return REVIEW_URL . $this->getSrc();
    }
    
    /**
     * Récupération du lien de la page
     * @return string
     */
    public function getSrc()
    {
    	return $this->_src;
    }

    /**
     * Initialisation du lien de le fichier
     * @param string $src
     */
    public function setSrc($src)
    {
    	if ($src != '') {
    		$this->_src = $src;
    	}
    }
    
    /**
     * Récupération de la cible de la page
     * @return string
     */
    public function getTarget()
    {
    	return $this->_target;
    }
    
    /**
     * Initialisation de la cible de la page
     * @param string $link
     */
    public function setTarget($target)
    {
    	$this->_target = $target;
    }
    
    /**
     * Conversion de la page en tableau associatif
     * @see Ccsd_Website_Navigation_Page::toArray()
     */
    public function toArray()
    {
    	$array = parent::toArray();
    	$array['target'] = $this->getTarget();
    	return $array;
    }
    
    /**
     * Enregistrement des fichiers
     */
    public function saveFile()
    {
    	//C'est pas super propre mais bon...
    	if ($this->getSrc() != '') {
    		if (isset($_FILES['pages_' . $this->getPageId()]['tmp_name']['src']) && is_file($_FILES['pages_' . $this->getPageId()]['tmp_name']['src'])) {
    			$this->setSrc(Ccsd_Tools::getNewFileName($this->getSrc(), REVIEW_PATH . 'public/'));
    			rename($_FILES['pages_' . $this->getPageId()]['tmp_name']['src'], REVIEW_PATH . 'public/' . $this->getSrc());
    		}
    	}
    }
    
    
    /**
     * Retour du formulaire de création de la page
     * @see Ccsd_Website_Navigation_Page::getForm()
     */
    public function getForm($pageidx)
    {
    	parent::getForm($pageidx);
    	$this->_form->addElement('file', 'src', 
    			array('required' => true, 
    				'label' => 'Lien', 
    				'value'=>$this->getSrc(), 
    				'belongsTo'	=> 'pages_' . $pageidx));
    	$this->_form->addElement('select', 'target', 
    			array('required' => true, 
    				'label' => 'Cible', 'value'=>$this->getTarget(), 
    				'belongsTo'	=> 'pages_' . $pageidx, 
    				'multioptions' => array (
    					'_self' => 'Page courante (_self)',
    					'_blank' => 'Nouvelle page (_blank)')));
    	return $this->_form;
    }
    
    /**
     * Retourne les informations complémentaires spécifiques à la page
     * @see Ccsd_Website_Navigation_Page::getSuppParams()
     */
    public function getSuppParams()
    {
    	return serialize(array('src' => $this->getSrc(), 'target' => $this->getTarget()));
    }
} 