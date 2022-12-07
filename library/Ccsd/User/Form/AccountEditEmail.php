<?php

/**
 *
 * edit account email form
 *
 */
class Ccsd_User_Form_AccountEditEmail extends Ccsd_Form
{

    public function init (): void
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/account_edit_email.ini'));

        $email = $this->getElement('EMAIL');
        $userUid = $this->getElement('USER_UID');

        if($userUid){

            $userUid->setValue(Episciences_Auth::getUid());

        }


        if ($email) {
            $options = array(
                'table' => 'T_UTILISATEURS',
                'field' => 'EMAIL',
                'adapter' => Ccsd_Db_Adapter_Cas::getAdapter()
            );
            $validator = new Zend_Validate_Db_NoRecordExists($options);
            $validator -> setMessage(Zend_Registry::get('Zend_Translate')->translate("A record matching email (%value%) was found. Use login retrieve tools"));
            $email->addValidator($validator);
        }
    }
    
}




