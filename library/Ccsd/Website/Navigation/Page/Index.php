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
    public function __construct(mixed ...$args)
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        if (get_parent_class($this) !== false && method_exists(get_parent_class($this), '__construct')) {
            parent::__construct(...$args);
        }
    }

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