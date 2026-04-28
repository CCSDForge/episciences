<?php

class Ccsd_Website_Navigation_Page_Index extends Ccsd_Website_Navigation_Page
{    
    /**
     * Widgets de la page
     * @var unknown_type
     */
    protected $_widgets         =   array();

    /**
     * Chargement des widgets
     */
    public function loadWidgets()
    {
        
    }
    
    /**
     * Récupération du contenu de la page
     * @param string $lang
     * @return 
     */
    public function getWidgets($lang = '')
    {
        if (count($this->_widgets) == 0) {
            $this->loadWidgets();
        }
        
        if ($lang === '') {
            return $this->_widgets;
        } else if (isset($this->_widgets[$lang])) {
            return $this->_widgets[$lang];
        } else {
            return '';
        }
    }
    
    /**
     * Initialisation des widgets d'une page
     * @param string | array $widgets
     * @param string $lang
     */
    public function setWidgets($widgets, $lang = '')
    {
        if ($lang === '') {
            $this->_widgets = $widgets;
        } else {
            $this->_widgets[$lang] = $widgets;
        }
    }
    
    /**
     * Enregistrement des widgets
     */
    public function saveWidgets()
    {
        
    }
}