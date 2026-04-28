<?php

class Ccsd_Form_Validate_Isarxiv extends Zend_Validate_Regex {

    protected $_messageTemplates = array(
            self::INVALID   => "La valeur saisie ne peut pas être validée. Il ne s'agit pas d'un identifiant ARXIV.",
            self::NOT_MATCH => "'%value%' n'est pas un identifiant ARXIV valide, par exemple : 1401.0006 ou math/0602059",
            self::ERROROUS  => "Une erreur interne s'est produite, veuillez recommencer.",
    );
    
    public function __construct($pattern = "/^([0-9]{4}\.[0-9]{4})|([a-zA-Z\.-]+\/[0-9]{7})$/")
    {
        parent::__construct($pattern);
    }
    
}