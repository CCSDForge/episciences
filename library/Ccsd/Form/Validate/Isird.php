<?php

class Ccsd_Form_Validate_Isird extends Zend_Validate_Regex {

    protected $_messageTemplates = array(
            self::INVALID   => "La valeur saisie ne peut pas être validée. Il ne s'agit pas d'un identifiant IRD.",
            self::NOT_MATCH => "'%value%' n'est pas un identifiant IRD valide, par exemple : fdi:123456",
            self::ERROROUS  => "Une erreur interne s'est produite, veuillez recommencer.",
    );
    
    public function __construct($pattern = "/^(fdi:|PAR)\d+$/")
    {
        parent::__construct($pattern);
    }
    
}