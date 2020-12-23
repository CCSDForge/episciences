<?php

class Episciences_User_InvitationAnswer
{
	// CONSTANTES ******************************************************************
	// Réponses possibles
	const ANSWER_YES	=	'yes';
	const ANSWER_NO		=	'no';
	
	// Détails de la réponse
	const DETAIL_DELAY 	 =	'delay';
	const DETAIL_SUGGEST =	'reviewer_suggest';
	const DETAIL_COMMENT =	'comment';

	// ATTRIBUTS *******************************************************************

	/**
	 * Id de l'invitation
	 * @var int
	 */
	protected $_id;

	/**
	 * Réponse à l'invitation (yes, no)
	 * @var string(10)
	 */
	protected $_answer;
	
	/**
	 * Date de la réponse
	 * @var datetime
	 */
	protected $_answer_date;

	
	protected $_details = array();

	// METHODES ******************************************************************


	public function __construct(array $options = null)
	{
		if (is_array($options)) {
			$this->setOptions($options);
		}
	}

	public function setOptions(array $options)
	{
		$methods = get_class_methods($this);
		foreach ($options as $key => $value) {
			$method = 'set' . ucfirst(strtolower($key));
			if (in_array($method, $methods)) {
				$this->$method($value);
			}
		}
		return $this;
	}

	/**
	 * Enregistre l'invitation en BDD
	 * @return boolean
	 */
	public function save()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

		// Préparation des valeurs à insérer
		$values = array(
				'ID'			=>	$this->getId(),
				'ANSWER'		=>	$this->getAnswer(),
				'ANSWER_DATE'	=>	new Zend_Db_Expr('NOW()')
		);
		
		// Enregistrement de la réponse en base 
		if (!$db->insert(T_USER_INVITATION_ANSWER, $values))  {
			return false;
		}
		
		// Enregistrement des détails en base (USER_INVITATION_ANSWER_DETAILS)
		$details = $this->getDetails();

		if ($details) {
			
			$values = array();
			foreach ($details as $setting=>$value) {
				$setting = $db->quote($setting);
				$value = $db->quote($value);
				$values[] = '('.$this->getId().','.$setting.','.$value.')';
			}
        
			$sql = 'INSERT INTO '.T_USER_INVITATION_ANSWER_DETAIL.' (ID, NAME, VALUE) VALUES '.implode(',', $values);
	        $db->query($sql); 
		}
		
		return true;

	}


	// GETTERS *******************************************************************


	/**
	 * @return the $_id
	 */
	public function getId ()
	{
		return $this->_id;
	}
	
	/**
	 * @return the $_answer
	 */
	public function getAnswer ()
	{
		return $this->_answer;
	}
	
	/**
	 * @return the $_answer_date
	 */
	public function getAnswer_date ()
	{
		return $this->_answer_date;
	}
	
	/**
	 * @return the $_details
	 */
	public function getDetails ()
	{
		return $this->_details;
	}
	
	public function getDetail($name)
	{
		$details = $this->getDetails();
		
		if (array_key_exists($name, $details)) {
			return $details[$name];
		}
		
		return false;
	}


	// SETTERS ************************************************************************


	/**
	 * @param number $_id
	 */
	public function setId ($id)
	{
		$this->_id = $id;
	}
	
	/**
	 * @param string(10) $_answer
	 */
	public function setAnswer ($answer)
	{
		$this->_answer = $answer;
	}
	
	/**
	 * @param datetime $_answer_date
	 */
	public function setAnswer_date ($answer_date)
	{
		$this->_answer_date = $answer_date;
	}
	
	/**
	 * @param array $_details
	 */
	public function setDetails (array $details)
	{
		$this->_details = $details;
	}

	/**
	 * @param varchar $_name, varchar $_value
	 */
	public function setDetail ($name, $value)
	{
		$this->_details[$name] = $value;
	}




}