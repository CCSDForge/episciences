<?php

class Episciences_Paper_Log {
	
	private $_logid;
	private $_paperid;
	private $_docid;
	private $_uid;
	private $_rvid;
	private $_action;
	private $_detail;
	private $_date;
	
	public function __construct (array $options = null)
    {
    	if (is_array($options)) {
    		$this->setOptions($options);
    	}
    }
    
    public function setOptions ($options = [])
    {
    	$methods = get_class_methods($this);
    	foreach ($options as $key => $value) {
    		$key = strtolower($key);
    		$method = 'set' . ucfirst($key);
    		if (in_array($method, $methods)) {
    			$this->$method($value);
    		}
    	}
    	return $this;
    }
    
    public function toArray()
    {
    	$result = [
    			'logid'		=>	$this->getLogid(),
    			'paperid'	=>	$this->getPaperid(),
    			'docid'		=>	$this->getDocid(),
    			'uid'		=>	$this->getUid(),
    			'rvid'		=>	$this->getRvid(),
    			'action'	=>	$this->getAction(),
    			'detail'	=>	$this->getDetail(),
    			'date'		=>	$this->getDate()
    	];
    	return $result;
    }
		
	function load($id) {
		
		if (!is_numeric($id)) {
			return false;
		}
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(T_LOGS)->where('LOGID = ?', $id);
		$data = $db->fetchRow($sql);
		
		if (!$data) {
			return false;
		}
		
		$this->setOptions($data);
		return $this;
	}
	
	public function save() 
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$data = array(
				'PAPERID' 	=> $this->getPaperid(),
				'DOCID'		=> $this->getDocid(),
				'UID' 		=> $this->getUid(),
				'RVID' 		=> $this->getRvid(),
				'ACTION'	=> $this->getAction(),
				'DETAIL' 	=> (!Episciences_Tools::isJson($this->getDetail())) ? Zend_Json::encode($this->getDetail()) : $this->getDetail(),
				'DATE' 		=> ($this->getDate()) ? $this->getDate() : new Zend_DB_Expr('NOW()')
		);
			
		if (!$db->insert(T_LOGS, $data)) {
			return false;
		}
		
		return true;
	}
	
	
	
	public function getLogid()
	{
		return $this->_logid;	
	}
	
	public function getPaperid()
	{
		return $this->_paperid;
	}
	
	public function getDocid()
	{
		return $this->_docid;
	}
	
	public function getUid()
	{
		return $this->_uid;
	}
	
	public function getRvid()
	{
		return $this->_rvid;
	}
	
	public function getAction()
	{
		return $this->_action;
	}
	
	public function getDetail()
	{
		return (Episciences_Tools::isJson($this->_detail)) ? Zend_Json::decode($this->_detail) : $this->_detail;
		//return $this->_detail;
	}
	
	public function getDate()
	{
		return $this->_date;
	}
	
	
	public function setLogid($logid)
	{
		$this->_logid = $logid;
		return $this;
	}
	
	public function setPaperid($paperid)
	{
		$this->_paperid = $paperid;
		return $this;
	}
	
	public function setDocid($docid)
	{
		$this->_docid = $docid;
		return $this;
	}
	
	public function setUid($uid)
	{
		$this->_uid= $uid;
		return $this;
	}
	
	public function setRvid($rvid)
	{
		$this->_rvid = $rvid;
		return $this;
	}
	
	public function setAction($action)
	{
		$this->_action = $action;
		return $this;
	}
	
	public function setDetail($detail)
	{
		$this->_detail = (Episciences_Tools::isJson($detail)) ? Zend_Json::decode($detail) : $detail;
		return $this;
	}
	
	public function setDate($date)
	{
		$this->_date = $date;
		return $this;
	}
}