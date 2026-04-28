<?php

class Ccsd_Form_Validate_Isdoi extends Zend_Validate_Regex {

    protected $_messageTemplates = array(
            self::INVALID   => "La valeur saisie ne peut pas être validée. Il ne s'agit pas d'un DOI.",
            self::NOT_MATCH => "'%value%' n'est pas un DOI valide, par exemple : 10.xxx",
            self::ERROROUS  => "Une erreur interne s'est produite, veuillez recommencer.",
    );
    
    public function __construct($pattern = "/^10\..+/")
    {
        parent::__construct($pattern);
    }

}