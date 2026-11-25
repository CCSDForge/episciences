<?php

use Episciences\User\Validate\EmailAvailability;

/**
 *
 * edit account email form
 *
 */
class Ccsd_User_Form_AccountEditEmail extends Ccsd_Form
{
    /** UID excluded from the email-availability check: the account being edited. */
    private int $_excludeUid = 0;

    public function setExcludeUid(int $uid): self
    {
        $this->_excludeUid = $uid;
        return $this;
    }

    public function init (): void
    {
        parent::init();
        $this->setConfig(new Zend_Config_Ini('Ccsd/User/configs/account_edit_email.ini'));

        $email = $this->getElement('EMAIL');

        if ($email) {
            $message = Zend_Registry::get('Zend_Translate')->translate("A record matching email (%value%) was found. Use login retrieve tools");
            $validator = new EmailAvailability($this->_excludeUid, $message);
            $email->addValidator($validator);
        }
    }

}




