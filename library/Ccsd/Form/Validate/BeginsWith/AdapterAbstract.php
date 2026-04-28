<?php

/**
 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface
 */
// require_once 'Ccsd/Form/Validate/BeginsWith/AdapterInterface.php';

/**
 * @category   Ccsd
 * @package    Ccsd_Form_Validate_BeginsWith
 */
abstract class Ccsd_Form_Validate_BeginsWith_AdapterAbstract
	implements Ccsd_Form_Validate_BeginsWith_AdapterInterface
{
	
	/**
	 * Value parameter
	 * @var mixed
	 */
	protected $_value;	

	/**
	 * Starting parameter
	 * @var string
	 */
	protected $_start;
	
	/**
	 * Type's adapter parameter
	 * @var mixed
	 */
	protected $_type;
		
	/**
	 * All parameter
	 * @var boolean
	 */
	protected $_all;
	
	/**
	 * Get value parameter
	 * @return mixed
	 */
	public function getValue ()
	{
		return $this->_value;
	}
	
	/**
     * Set value parameter
     * @param mixed $value
     * @return Ccsd_Form_Validate_BeginsWith_AdapterAbstract
     */
	public function setValue ($value)
	{
		return call_user_func(array($this, 'setValue'), $value);
	}
	
	/**
	 * Set value parameter
	 * @param mixed $value
	 * @return Ccsd_Form_Validate_BeginsWith_AdapterAbstract
	 */
	protected function _setValue ($value)
	{
		$this->_value = $value;
		return $this;
	}
	
	/**
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface::getStart()
	 * @return string
	 */
	public function getStart ()
	{
		return $this->_start;
	}
	
	/**
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface::setStart()
	 * @return Ccsd_Form_Validate_BeginsWith_AdapterAbstract
	 */
	public function setStart ($start)
	{
		$this->_start = $start;
		return $this;
	}
	
	/**
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface::getAll()
	 * @return boolean
	 */
	public function getAll ()
	{
		return $this->_all;
	}
	
	/**
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface::setAll()
	 * @return Ccsd_Form_Validate_BeginsWith_AdapterAbstract
	 */
	public function setAll ($all)
	{
		$this->_all = $all;
		return $this;
	}
	
	/**
	 * Get type's adapter parameter
	 * @return mixed
	 */
	public function getType ()
	{
		return $this->_type;
	}
	
	/**
	 * Set type's adapter parameter
	 * @return Ccsd_Form_Validate_BeginsWith_AdapterAbstract
	 */
	public function setType ($type)
	{
		$this->_type = $type;
		return $this;
	}

	/**
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface::_value()
	 */
	abstract public function _value ();
	
	/**
	 * Operation logique &&
	 * @param boolean $a
	 * @param boolean $b
	 * @return boolean
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface::_operatorAND()
	 */
	public function _operatorAND ($a, $b)
	{
		return $a && $b;
	}
	
	/**
	 * Operation logique ||
	 * @param boolean $a
	 * @param boolean $b
	 * @return boolean
	 * @see Ccsd_Form_Validate_BeginsWith_AdapterInterface::_operatorOR()
	 */
	public function _operatorOR ($a, $b)
	{
		return $a || $b;
	}
}