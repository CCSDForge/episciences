<?php

class Episciences_Website_Header extends Ccsd_Website_Header
{

	public function __construct()
	{
		$this->_fieldSID = 'RVID';
		$this->_sid = RVID;
		$this->_publicDir = REVIEW_PATH . 'public/';
		$this->_publicUrl = REVIEW_URL;
		$this->_langDir = REVIEW_PATH . 'languages/';
		$this->_layoutDir = REVIEW_PATH . 'layout/';
		$this->_languages = Zend_Registry::get('languages');
	}
}