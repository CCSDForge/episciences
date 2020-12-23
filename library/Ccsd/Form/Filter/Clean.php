<?php

class Ccsd_Form_Filter_Clean implements Zend_Filter_Interface
{
	/**
	 * Returns the result of filtering $value
	 *
	 * @param  mixed $value
	 * @throws Zend_Filter_Exception If filtering $value is impossible
	 * @return mixed
	 */
	public function filter($value)
	{
		if (is_string ($value))
			return Ccsd_Tools_String::stripCtrlChars($value, '', false);
		else if (is_array($value)) {
			foreach ($value as $i => $v) {
				$value[$i] = Ccsd_Tools_String::stripCtrlChars($v, '', false);
			}
			return $value;
		}
		return $value;
	}
}