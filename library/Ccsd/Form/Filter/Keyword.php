<?php

class Ccsd_Form_Filter_Keyword implements Zend_Filter_Interface {

    /** @deprecated
     *  @var string */
	protected $_separator;
	/** @var  string */
	protected $_regexp;

    /**
     * Ccsd_Form_Filter_Keyword constructor.
     * @param string $separator // characters on split
     * @param string $regexp // regexp string containt the begin and end char
     */
	public function __construct ( $separator = ",;" , $regexp=null)
	{
	    if ($regexp == null) {
	        $this->_separator = $separator;
            $this->_regexp = "#" . implode("|", str_split($separator, 1)) . "#";
        } else {
	        $this->_regexp = $regexp;
        }
    }

    /**
     * @return string
     */
    public function getRegexp ()
	{
		return $this->_regexp;
	}

    /**
     * @param string $regexp
     * @return Ccsd_Form_Filter_Keyword
     */
	public function setRegexp ($regexp)
	{
		$this->_regexp = $regexp;
		return $this;
	}

    /** @deprecated  */
	public function getSeparator ()
	{
		return $this->_separator;
	}

	/** @deprecated
     * @param string
     * @return Ccsd_Form_Filter_Keyword
     */
	public function setSeparator ($separator)
	{
		$this->_separator = $separator;
		return $this;
	}

    /**
     * @param mixed $value
     * @return array|mixed
     */
	public function filter($value)
	{
		if (!$value) {
			return $value;
		}
		$singleVal=False;
		if (!is_array ($value)) {
		    $singleVal=true;
			$value = array ($value);
		}
        $filteredValues = array();

		foreach ($value as $k => $v) {
			if (!is_array ($v)) {
                $v = array($v);
            }
            foreach ($v as  $y) {
                foreach (preg_split($this->getRegexp(), $y) as $z) {
                    if (!array_key_exists ($k, $filteredValues)) {
                        $filteredValues[$k] = array ();
                    }
                    $filteredValues[$k][] = $z;
                }
            }

            if (isset ($filteredValues[$k]) && is_array ($filteredValues[$k])) {
                $filteredValues[$k] = array_unique($filteredValues[$k]);
            }
		}

		return $singleVal ? $filteredValues[0] : $filteredValues;
	}	

}