<?php

class Ccsd_Website_Navigation_Page_Acces extends Ccsd_Website_Navigation_Page
{
    /**
     * Latitude
     * @var float
     */
    public $x;
    
    /**
     * Longitude
     * @var float
     */
    public $y;
    
    /**
     * Explications pour se rendre sur place
     * @var string
     */
    public $description;
    
    /**
     * Constructeur de la page
     */
    public function __construct($nav, $options = array()) {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        parent::__construct($nav, $options);
    }
    
    /**
     * Enregistrement des données d'accès
     */
    public function save() {}
    
    
} 