<?php

/**
 * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract
 */
//require_once 'Ccsd/Form/Validate/BeginsWith/AdapterAbstract.php';

/**
 * @category   Ccsd
 * @package    Ccsd_Form_Validate_BeginsWith
 */
class Ccsd_Form_Validate_BeginsWith_Multi 
	extends Ccsd_Form_Validate_BeginsWith_AdapterAbstract
		implements Iterator
{
	/**
	 * @param array $value
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::setValue()
	 */
	public function setValue ($value)
	{
		if (!is_array($value)) {
			/**
			 * @see Ccsd_Form_Validate_BeginsWith_Exception
			 */
			require_once "Ccsd/Form/Validate/BeginsWith/Exception.php";
            throw new Ccsd_Form_Validate_BeginsWith_Exception;
        }
	
        $value = new RecursiveIteratorIterator(new RecursiveArrayIterator($value), RecursiveIteratorIterator::LEAVES_ONLY);
		$value = iterator_to_array($value, false);

		return parent::_setValue($value);
	}
	
	public function current () 
	{
		if ($this->valid()) {
			return current ($this->_value);
		}
		return FALSE;
	}

	public function next () 
	{
		next($this->_value);
	}

	public function key () 
	{
		return key($this->_value);
	}

	public function valid () 
	{
		return isset($this->_value[$this->key()]);
	}

	public function rewind () 
	{
		rewind($this->_value);
	}
	
	public function _value ()
	{
		$value = $this->current();
		
		$this->next();
		
		return $value;
	}
}