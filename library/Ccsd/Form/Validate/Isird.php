<?php

class Ccsd_Form_Validate_Isird extends Zend_Validate_Regex {

    protected $_messageTemplates = array(
            self::INVALID   => "La valeur saisie ne peut pas être validée. Il ne s'agit pas d'un identifiant IRD.",
            self::NOT_MATCH => "'%value%' n'est pas un identifiant IRD valide, par exemple : fdi:123456",
            self::ERROROUS  => "Une erreur interne s'est produite, veuillez recommencer.",
    );
    
    public function __construct($pattern = "/^(fdi:|PAR)\d+$/")
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        parent::__construct($pattern);
    }
    
}