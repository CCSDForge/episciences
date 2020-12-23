<?php

class Ccsd_Form_Filter_Decimal implements Zend_Filter_Interface {
	
	public function filter($value)
	{
        $regexp = "/^([NSEW])?\s*([-+])?(\d{1,3})°\s*(\d{1,2})('|′)\s*(\d{1,2}(\.\d{1,10})?)(''|″|\")\s*([NSEW])?$/";
        if (preg_match($regexp, trim($value), $matches)) {
            $value = ($matches['6'] / 3600) + ($matches['4'] / 60) + $matches['3'];
            if ($matches['1'] == 'S' || $matches['1'] == 'W') {
                $value = - $value;
            } else if ($matches['1'] == '' && $matches['2'] == '-') {
                $value = - $value;
            } else if (isset($matches['9']) && ($matches['9'] == 'S' || $matches['9'] == 'W')) {
                $value = - $value;
            }
        } else {
            $value = str_replace (',', '.', $value);
        }
        return $value;
	}
}