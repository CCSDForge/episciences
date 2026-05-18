<?php

class Ccsd_Form_Validate_Ispubmed extends Zend_Validate_Regex {

    protected $_messageTemplates = array(
            self::INVALID   => "La valeur saisie ne peut pas être validée. Il ne s'agit pas d'un identifiant PubMed.",
            self::NOT_MATCH => "'%value%' n'est pas un identifiant PubMed valide. Il ne doit contenir que des caractères numériques.",
        self::ERROROUS  => "Une erreur interne s'est produite, veuillez recommencer.",
    );

    public function __construct($pattern = "/^[0-9]+$/")
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