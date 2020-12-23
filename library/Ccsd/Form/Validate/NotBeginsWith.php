<?php

/**
 * @category   Ccsd
 * @package    Ccsd_Form_Validate
 */
class Ccsd_Form_Validate_NotBeginsWith extends Ccsd_Form_Validate_BeginsWith
{
    const INVALID_DATA    = 'BeginsWithInvalidData';

    protected $_messageTemplates = array(
        self::INVALID        => "Invalid type given. %type% expected",
    	self::INVALID_START  => "Starting : invalid type given. %type% expected",
        self::INVALID_DATA   => "You can not enter a value included in '%start%'",
    );

    /**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $_messageVariables = array(
        'begin' 	=> '_begin',
    	'type'		=> '_type',
    	'start'		=> '_start',
    );
    
    /**
     * Generates the standard validator object
     *
     * @param  string|Zend_Config|
     *         Ccsd_Form_Validate_BeginsWithAdapter $adapter BeginsWith adapter to use
     * @return void
     * @throws Zend_Validate_Exception
     */
    public function __construct($adapter)
    {
        if ($adapter instanceof Zend_Config) {
            $adapter = $adapter->toArray();
        }

        $options  = null;
        $start    = null;
        $all      = null;
        if (is_array($adapter)) {
            if (array_key_exists('options', $adapter)) {
                $options = $adapter['options'];
            }
            
            if (array_key_exists('start', $adapter)) {
            	$start = $adapter['start'];
            }

            if (array_key_exists('adapter', $adapter)) {
                $adapter = $adapter['adapter'];
            } else {
                require_once 'Zend/Validate/Exception.php';
                throw new Zend_Validate_Exception("Missing option 'adapter'");
            }
        }

        $this->setAdapter($adapter, $options);
        if ($start !== null) {
        	$this->setStart($start);
        }
    }

    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::getStart()
     * @return string|array
     */
    public function getStart ()
    {
    	return $this->getAdapter()->getStart();
    }
    
    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::setStart()
     * @param string|array $start
     * @return Ccsd_Form_Validate_BeginsWith
     */
    public function setStart ($start)
    {
    	$this->getAdapter()->setStart($start);
        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $value contains a valid value
     *
     * @param  string $value
     * @return boolean
     */
    public function isValid($value)
    {   
        $adapter 		= $this->getAdapter();
        $is_starting 	= true;
        
        $start 			= $adapter->getStart ();
        if (!is_array ($start)) {
        	$start = array ($start);
        }
        
        $this->_begin   = $start;
        $this->_type 	= $adapter->getType();

        try {
        	$adapter->setValue ($value);
        } catch (Ccsd_Form_Validate_BeginsWith_Exception $e) {
        	$this->_error(self::INVALID);
        	return false;
        }

        foreach ($start as $s) {
        	if (!is_string ($s)) {
        		$this->_error(self::INVALID_START);
        		return false;
        	}
        }
        
       	while (($value = $adapter->_value()) !== FALSE) {
       		if (!is_string ($value)) {
       			$this->_error(self::INVALID);
       			return false;
       		}

       		$is_starting = !in_array($value, $start);
       	}

       	if (!$is_starting) {
       		$this->_start = implode("," , $start);
       		$this->_error(self::INVALID_DATA);
       		return false;
       	}

       	return true;
    }
}