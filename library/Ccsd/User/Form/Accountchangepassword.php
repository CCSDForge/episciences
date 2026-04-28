<?php

/**
 * Formulaire de changement du mot de passe
 * @author rtournoy
 *
 */
class Ccsd_User_Form_Accountchangepassword extends Ccsd_Form
{

    public function init () {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/accountchangepassword.ini'));
    }

}