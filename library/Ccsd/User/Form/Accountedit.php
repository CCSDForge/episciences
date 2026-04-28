<?php

/**
 * Formulaire d'édition des données utilisateur
 * @author rtournoy
 *
 */
class Ccsd_User_Form_Accountedit extends Ccsd_Form
{
  
    public function init ()
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/accountedit.ini', 'ccsd-account-edit'));
    }
    
}



