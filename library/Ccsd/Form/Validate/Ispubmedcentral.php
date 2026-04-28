<?php

class Ccsd_Form_Validate_Ispubmedcentral extends Zend_Validate_Regex {

    protected $_messageTemplates = array(
        self::INVALID   => "La valeur saisie ne peut pas être validée. Il ne s'agit pas d'un identifiant PubMed Central.",
        self::NOT_MATCH => "'%value%' n'est pas un identifiant PubMed Central valide. Il ne doit contenir que des caractères numériques.",
        self::ERROROUS  => "Une erreur interne s'est produite, veuillez recommencer.",
    );

    /** force pattern
     * Todo: Why does our constructor take an arg?
     * It's just the call to the parent which need argument
     * If we can put a different regexp, it's not a validate for Pmc?
     * @param string $pattern
     */
    public function __construct()
    {
        parent::__construct("/^PMC[0-9]+$/");
    }

}