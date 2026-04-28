<?php

/**
 * @category   Ccsd
 * @package    Ccsd_Form_Validate_BeginsWith
 */
interface Ccsd_Form_Validate_BeginsWith_AdapterInterface
{
	/**
	 * Set value parameter
	 * @param mixed $value
	 */
	public function setValue ($value);
	
	/**
	 * Get start parameter
	 */
	public function getStart ();
	
	/**
	 * Set start parameter
	 * @param string $start
	 */
	public function setStart ($start);
	
	/**
	 * Get all parameter
	 */
	public function getAll ();
	
	/**
	 * Set all parameter
	 * @param boolean $all
	 */
	public function setAll ($all);
}
