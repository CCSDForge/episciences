<?php

/**
 * Formulaire de création de compte
 * @author rtournoy
 *
 */
class Ccsd_User_Form_Accountcreate extends Ccsd_Form
{

    const ACCOUNT_CREATED_SUCCESS = 'Compte créé';

    const ACCOUNT_CREATED_FAIL = 'Échec de la création du compte';

    public function init ()
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/accountcreate.ini'));

        $elem = $this->getElement('USERNAME');
        if ($elem) {
            $options = array(
                    'table' => 'T_UTILISATEURS',
                    'field' => 'USERNAME',
                    'adapter' => Ccsd_Db_Adapter_Cas::getAdapter()
            );
            $validator = new Zend_Validate_Db_NoRecordExists($options);
            $elem->addValidator($validator);
        }

        $email = $this->getElement('EMAIL');
        if ($email) {
            $options = array(
                'table' => 'T_UTILISATEURS',
                'field' => 'EMAIL',
                'adapter' => Ccsd_Db_Adapter_Cas::getAdapter()
            );
            $validator = new Zend_Validate_Db_NoRecordExists($options);
            $validator -> setMessage("A record matching email (%value%) was found.  Use login retrieve tools");
            $email->addValidator($validator);
        }
    }
}



