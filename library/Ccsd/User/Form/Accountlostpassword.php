<?php

/**
 *
 * Formulaire si perte de mot de passe
 *
 */
class Ccsd_User_Form_Accountlostpassword extends Ccsd_Form
{

    public function init ()
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/accountlostpassword.ini'));
    }
    
}




