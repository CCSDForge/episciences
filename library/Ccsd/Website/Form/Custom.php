<?php

/**
 * Formulaire de création de page personnalisable
 * @author Yannick Barborini
 *
 */
class Ccsd_Website_Form_Custom extends Zend_Form
{
    /**
     * Langues disponibles de la page
     * @var array
     */
    protected $_languages = array();
    
    public function __construct()
    {
        parent::__construct();
        $this->setName('formCustom')
            ->setAttrib('id', 'formCustom')
            ->setAttrib('enctype', 'multipart/form-data');
         
        foreach ($this->_languages as $lang) {
            //Contenu de la page
            $this->addElement('textarea', $lang, array(
                'value' => '',
                'label' => 'content_page_' . $lang,
                'rows' => 20,
                'cols' => 81,
                'class' => 'htmleditor',
                'dest' => SPACE_PAGES
            ));
        }

        //Submit
        $this->addElement('submit', 'valid', array(
            'label' => 'save'
        ));
             
        //PageId
        $this->addElement('hidden', 'id', array('value' => 0));
    }
}