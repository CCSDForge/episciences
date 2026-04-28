<?php

class Ccsd_View_Helper_Truncate extends Zend_View_Helper_Abstract 
{
	private $_string;
    private $_length;
    private $_postfix;
    private $_cutatspace = true;
    
    public function truncate($string) 
    {
    	$this->_string = trim($string);
        $this->_defaultValues();
        return $this;
	}

    private function _defaultValues() 
    {
    	$this->toLength(100);
        $this->withPostfix('&#0133;'); // Postfix par défaut : ...
	}

    public function midword() 
    {
    	$this->_cutatspace = false;
    	return $this;
	}

    public function toLength($int) 
    {
    	$this->_length = (int) $int;
    	return $this;
	}

	public function withPostfix($str) 
	{
    	$this->_postfix = $str;
        return $this;
	}

    public function render() 
    {
    	// Renvoie une chaîne vide si max length < 1
        if ($this->_length < 1) {
			return '';
		}

        // Renvoie la chaîne entière si max_length plus long que la chaîne 
        if ($this->_length >= strlen($this->_string)) {
        	return $this->_string;
		}

        // Renvoie la chaîne tronquée
        if ($this->_cutatspace) {
        	while (strlen($this->_string) > $this->_length) {
            	$cutPos = strrpos($this->_string, ' ', -1);
                if ($cutPos === false) {
                	// Si il ne reste pas d'espaces, la chaîne entière est tronquée
                	return '';
				}
                $this->_string = trim(substr($this->_string, 0, $cutPos));
			}
		} else {
        	$this->_string = trim(substr($this->_string, 0, $this->_length));
		}

		return $this->_string . $this->_postfix;
	}

    public function __toString() 
    {
    	return $this->render();
	}
}