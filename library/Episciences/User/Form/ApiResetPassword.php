<?php

class Episciences_User_Form_ApiResetPassword extends Ccsd_Form
{
    public function init() : void
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Episciences/User/config/api_reset_password.ini'));
    }


}
