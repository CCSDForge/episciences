<?php

class Episciences_User_Form_Edit extends Ccsd_User_Form_Accountedit
{
    private $_uid;

    public function init()
    {
        parent::init();

        $ccsd_sub_form = new Ccsd_Form_SubForm();
        $ccsd_sub_form->setElements($this->getElements());
        $ccsd_sub_form->setLegend("Informations de mon compte CCSD");
        $ccsd_sub_form->setDecorators([['ViewScript', ['viewScript' => 'user/form_edit.phtml', 'name' => 'ccsd']]]);
        $ccsd_sub_form->getElement('PHOTO')->getDecorator('Picture')->setUID($this->getUID());

        $this->clearElements();

        $episciences_sub_form = new Ccsd_Form_SubForm();

        try {
            $episciences_sub_form->setConfig(new Zend_Config_Ini(__DIR__ . '/../config/account.ini', 'episciences-account'));
            $episciences_sub_form->setLegend("Informations de mon profil Episciences");
            $episciences_sub_form->setDecorators([['ViewScript', ['viewScript' => 'user/form_edit.phtml', 'name' => 'episciences']]]);
            $this->addSubForms(["ccsd" => $ccsd_sub_form, "episciences" => $episciences_sub_form]);
            $this->setActions(true);
        } catch (Zend_Config_Exception $zend_Config_Exception) {
            error_log($zend_Config_Exception->getMessage());
        }
    }

    public function getUid()
    {
        return $this->_uid;
    }

    public function setUid($uid): \Episciences_User_Form_Edit
    {
        $this->_uid = $uid;
        return $this;
    }
}