<?php

class Ccsd_Form_Validate_NotSame extends Zend_Validate_Abstract
{
    private $_count = array ();
    protected $_group = true;
    
    const SAME          = 'same';
    const MISSING_TOKEN = 'missingToken';
    
    protected $_messageTemplates = array(
            self::SAME          => "Vous ne pouvez pas soumettre plus de deux valeurs pour une même langue",
            self::MISSING_TOKEN => 'Les valeurs passées ne sont pas valides',
    );
    
    public function __construct($grouponly = true)
    {
        $this->setGroup ($grouponly);
    }
    
    public function setGroup ($group)
    {
        $this->_group = $group;
    }
    
    public function isGroup ()
    {
        return $this->_group;
    }
        
    public function isValid ($value) 
    {
        if (is_array ($value)) {
            foreach ($value as $lang => $val) {
                if (!array_key_exists($lang, $this->_count)) {
                    $this->_count[$lang] = true;
                } else {
                    $this->_count[$lang] = $this->_count[$lang] && false;
                }   
            }
            
            if (!array_reduce ($this->_count, function ($v, $w) { return $v && $w; }, true)) {
                $this->_error(self::SAME);
                return false;
            }
            
            return true;
        }
        
        $this->_error(self::MISSING_TOKEN);
        return false;
    }
}