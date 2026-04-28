<?php

class Ccsd_Form_Element_Liste extends Ccsd_Form_Element_Thesaurus
{
    protected $_tagcode     = 'code';
    protected $_taglabel    = 'libelle';
    protected $_tagdisplay  = 'display';
    protected $_separator   = ':';

    public function getTagcode ()
    {
        return $this->_tagcode;
    }

    public function setTagcode ($libelle)
    {
        $this->_tagcode = $libelle;
        return $this;
    }

    public function getTaglabel ()
    {
        return $this->_taglabel;
    }

    public function setTaglabel ($libelle)
    {
        $this->_taglabel = $libelle;
        return $this;
    }

    public function getTagdisplay ()
    {
        return $this->_tagdisplay;
    }

    public function setTagdisplay ($str)
    {
        $this->_tagdisplay = $str;
        return $this;
    }
    
    public function getSeparator ()
    {
        return $this->_separator;
    }
    
    public function setSeparator ( $sep = ':' )
    {
        $this->_separator = $sep;
        return $this;
    }
    
    protected function _update ($a = array ())
    {
        $return = array ();
        
        foreach ($a as $item) {
            $return[$item[$this->_tagcode]] = $item;
        }

        return $return;
    }
    
    /**
     * Load default decorators
     *
     * @return Zend_Form_Element
     */
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }
    
        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            $this->addDecorator('Errors')
            ->addDecorator('Description', array('tag' => 'p', 'class' => 'description'))
            ->addDecorator('Liste')
            ->addDecorator('HtmlTag', array('tag'   => 'dd',
                    'id'    => $this->getName() . '-element'/*,
                    'style' => 'width: inherit;'*/))
                    ->addDecorator('Label', array('tag' => 'dt'));
        }
        return $this;
    }
}