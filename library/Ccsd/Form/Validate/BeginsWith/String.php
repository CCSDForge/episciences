<?php

/**
 * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract
 */
//require_once 'Ccsd/Form/Validate/BeginsWith/AdapterAbstract.php';

/**
 * @category   Ccsd
 * @package    Ccsd_Form_Validate_BeginsWith
 */
class Ccsd_Form_Validate_BeginsWith_String 
	extends Ccsd_Form_Validate_BeginsWith_AdapterAbstract
{
	private $pass = false;
	
	/**
	 * Set value parameter
	 * @param string $value
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::setValue()
	 */
    public function __construct()
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

    }

	public function setValue ($value)
	{
		if (!is_string($value)) {
            throw new Ccsd_Form_Validate_BeginsWith_Exception;
        }
	
		return parent::_setValue($value);
	}
	
	public function _value ()
	{
		if (!$this->pass) {
			$this->pass = true;
			return $this->_value;
		}
		
		return false;
	}
}