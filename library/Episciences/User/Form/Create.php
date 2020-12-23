<?php

class Episciences_User_Form_Create extends Ccsd_User_Form_Accountcreate
{
    /**
     * @return $this|void
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();
        try {
            $formConfig = new Zend_Config_Ini(__DIR__ . '/../config/account.ini', 'episciences-account');
            $this->addElements($formConfig->elements->toArray());
        } catch (Zend_Config_Exception $zend_Config_Exception) {
            error_log($zend_Config_Exception->getMessage());
        }
        return $this;
    }
}
