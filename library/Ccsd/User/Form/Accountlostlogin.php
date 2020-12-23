<?php

/**
 *
 * Formulaire si perte de login
 *
 */
class Ccsd_User_Form_Accountlostlogin extends Ccsd_Form
{

    public function init ()
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/accountlostlogin.ini'));
    }
    
}




