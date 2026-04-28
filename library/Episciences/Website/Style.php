<?php

class Episciences_Website_Style extends Ccsd_Website_Style
{

	public function __construct()
	{
		$this->_fieldSID = 'RVID';
		$this->_sid = RVID;
		$this->_dirname = REVIEW_PATH . 'public/';
		$this->_publicUrl = REVIEW_URL;
		$this->_tplUrl = '/css/templates/';
		$this->initForm();
	}
}