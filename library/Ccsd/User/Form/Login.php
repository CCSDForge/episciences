<?php

/**
 * Login form for MySQL authentication
 * Uses Bootstrap 3 styling with appropriate icons
 */
class Ccsd_User_Form_Login extends Ccsd_Form
{
    public function init(): void
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/login.ini'));

        // Add translated placeholders
        $translate = Zend_Registry::get('Zend_Translate');

        // Username field placeholder
        if ($username = $this->getElement('username')) {
            $username->setAttrib('placeholder', $translate->translate("Identifiant de connexion"));
        }

        // Password field placeholder
        if ($password = $this->getElement('password')) {
            $password->setAttrib('placeholder', $translate->translate("Mot de passe"));
        }
    }
}
