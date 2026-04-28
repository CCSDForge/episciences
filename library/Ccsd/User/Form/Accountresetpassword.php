<?php

/**
 * Formulaire de RAZ du mot de passe
 * @author rtournoy
 *
 */
class Ccsd_User_Form_Accountresetpassword extends Ccsd_Form
{

    public function __construct() {

        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/accountresetpassword.ini'));
    }

}

