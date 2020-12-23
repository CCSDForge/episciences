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
        parent::__construct($nav, $options);
    }
    
    /**
     * Enregistrement des données d'accès
     */
    public function save() {}
    
    
} 