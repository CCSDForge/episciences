<?php

class Episciences_Form_Validate_CheckEstimatedDelivery extends Zend_Validate_Abstract
{
	/**
	 * Error codes
	 * @const string
	 */
	const INVALID_ESTIMATION = 'invalid_estimation';
	const ESTIMATION_EXCEEDS_DEADLINE = 'estimation_exceeds_deadline';

	/**
	 * Error messages
	 * @var array
	 */
	protected $_messageTemplates = array(
			self::INVALID_ESTIMATION 			=> "Vous devez saisir une estimation de temps valide.",
			self::ESTIMATION_EXCEEDS_DEADLINE 	=> "Votre délai estimé de relecture dépasse le délai fixé par le comité éditorial."
	);

	protected $_rating_deadline;
	protected $_estimated_delivery;


	/*
	 public function getMessages()
	 {
	return array();
	}
	*/

	public function isValid($value)
	{
		$this->_setValue($value);
		
		if (!is_numeric($value)) {
			$this->_error(self::INVALID_ESTIMATION);
			return false;
		}
		
		$request = $this->getRequest();
		$value = trim($value) . ' ' . $request->getPost('unit');
		if (strtotime($value) > strtotime($this->_rating_deadline)) {
			$this->_error(self::ESTIMATION_EXCEEDS_DEADLINE);
			return false;
		}

		return true;
	}

	public function __construct($rating_deadline)
	{
		$this->_rating_deadline = $rating_deadline;
	}

}