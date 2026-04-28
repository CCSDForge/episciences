<?php

class Episciences_Form_Validate_CheckReqLang extends Zend_Validate_Abstract
{
	/**
	 * Error codes
	 * @const string
	 */
	const NO_LANGUAGES = 'noLanguages';
	const NOT_IN_LANGUAGES = 'notInLanguages';
	
	/**
	 * Error messages
	 * @var array
	 */
	protected $_messageTemplates = array(
			self::NO_LANGUAGES => "Dans les langues de la revue, vous devez choisir au moins une langue.",
			self::NOT_IN_LANGUAGES => "Les langues choisies doivent faire partie de la liste des langues disponibles de la revue.",
	);
	
	
	protected $_languages;
	
	
	/*
	public function getMessages()
	{
		return array();
	}
	*/
		
	public function isValid($value, $context = null)
	{
		$available = $this->_languages->getValue();
		
		// On vérifie qu'il existe des langues disponibles 
		if (!is_array($available) || empty($available)) {
			$this->_error(self::NO_LANGUAGES);
			return false;
		}
		
		// On vérifie que la langue requise choisie a également été choisie dans les langues de l'interface
		if (!in_array($value, $available)) {
			$this->_error(self::NOT_IN_LANGUAGES, $value);
			return false;
		} 
		
		return true;
		
	}
	
	public function __construct(Zend_Form_Element $languages)
	{
		$this->_languages = $languages;
	}
	
}