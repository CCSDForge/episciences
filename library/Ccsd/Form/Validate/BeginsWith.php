<?php

/**
 * @see Zend_Validate_Abstract
 */
require_once 'Zend/Validate/Abstract.php';

/**
 * @see Zend_Loader
 */
require_once 'Zend/Loader.php';

/**
 * @category   Ccsd
 * @package    Ccsd_Form_Validate
 */
class Ccsd_Form_Validate_BeginsWith extends Zend_Validate_Abstract
{
    const INVALID        = 'BeginsWithInvalid';
    const INVALID_START  = 'BeginsWithInvalidStart';
    const INVALID_ALL    = 'BeginsWithInvalidAll';
    const INVALID_ONE    = 'BeginsWithInvalidOne';

    protected $_messageTemplates = array(
        self::INVALID        => "Invalid type given. %type% expected",
    	self::INVALID_START  => "Starting : invalid type given. %type% expected",
        self::INVALID_ALL    => "Each value need to start with '%begin%' string. Missing",
        self::INVALID_ONE    => "Only one value need to start with %start% string. Missing",
    );

    /**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $_messageVariables = array(
        'begin' 	=> '_begin',
    	'type'		=> '_type'
    );
    
    /**
     * BeginsWith adapter
     *
     * @var Ccsd_Form_Validate_BeginsWithAdapter
     */
    protected $_adapter;
    
    /**
     * Adapter type parameter
     * @var string
     */
    protected $_type;
    
    /**
     * Begin parameter
     * @var mixed
     */
    protected $_begin;

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
                        
            if (array_key_exists('all', $adapter)) {
            	$all = $adapter['all'];
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
        if ($all !== null) {
        	$this->setAll($all);
        }
    }

    /**
     * Returns the set adapter
     *
     * @return Ccsd_Form_Validate_BeginsWithAdapter
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Sets a new BeginsWith adapter
     *
     * @param  string|Ccsd_Form_Validate_BeginsWithAdapter $adapter BeginsWith adapter to use
     * @param  array  $options Options for this adapter
     * @return void
     * @throws Zend_Validate_Exception
     */
    public function setAdapter($adapter, $options = null)
    {
    	
        $adapter = ucfirst(strtolower($adapter));
        require_once 'Zend/Loader.php';
        if (Zend_Loader::isReadable('Ccsd/Form/Validate/BeginsWith/' . $adapter. '.php')) {
            $class = 'Ccsd_Form_Validate_BeginsWith_' . $adapter;
        }

        if (!class_exists($class)) {
            Zend_Loader::loadClass($class);
        }

        $this->_adapter = new $class($options);
        if (!$this->_adapter instanceof Ccsd_Form_Validate_BeginsWith_AdapterInterface) {
            require_once 'Zend/Validate/Exception.php';
            throw new Zend_Validate_Exception(
                "Adapter " . $adapter . " does not implement Ccsd_Form_Validate_BeginsWith_AdapterInterface"
            );
        }

        $this->setType ($adapter);
        
        return $this;
    }

    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::getStart()
     * @return string
     */
    public function getStart ()
    {
    	return $this->getAdapter()->getStart();
    }
    
    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::setStart()
     * @param string $start
     * @return Ccsd_Form_Validate_BeginsWith
     */
    public function setStart ($start)
    {
    	$this->getAdapter()->setStart($start);
        return $this;
    }
    
    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::getAll()
     * @return boolean
     */
    public function getAll ()
    {
    	return $this->getAdapter()->getAll();
    }
    
    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::setAll()
     * @param boolean $all
     * @return Ccsd_Form_Validate_BeginsWith
     */
    public function setAll ($all)
    {
    	$this->getAdapter()->setAll($all);
        return $this;
    }
    
    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::getType()
     * @return string
     */
    public function getType ()
    {
    	return $this->getAdapter()->getType();
    }
    
    /**
     * @see Ccsd_Form_Validate_BeginsWith_AdapterAbstract::setType()
     * @param string $type
     * @return Ccsd_Form_Validate_BeginsWith
     */
    public function setType ($type)
    {
    	$this->getAdapter()->setType($type);
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
        $this->_begin   = $start = $adapter->getStart ();
        $all   			= $adapter->getAll();
        $this->_type 	= $adapter->getType();

        try {
        	$adapter->setValue ($value);
        } catch (Ccsd_Form_Validate_BeginsWith_Exception $e) {
        	$this->_error(self::INVALID);
        	return false;
        }

        if (!is_string ($start)) {
        	$this->_error(self::INVALID_START);
        	return false;
        }

       	while (($value = $adapter->_value()) !== FALSE) {
       		if (!is_string ($value)) {
       			$this->_error(self::INVALID);
       			return false;
       		}
       		       		
       		$is_starting = $adapter->{"_operator" . ($all ? "AND" : "OR")}($is_starting, strpos ($value, $start) === 0);
       	}
       	
       	if (!$is_starting) {
       		$this->_error($all ? self::INVALID_ALL : self::INVALID_ONE);
       		return false;
       	}

       	return true;
    }
}