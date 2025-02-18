<?php

class Episciences_Website_Header extends Ccsd_Website_Header
{

    /**
     * @var mixed|string
     */
    protected string $_langDir;

    public function __construct()
	{

		$this->_fieldSID = 'RVID';
		$this->_sid = RVID;

        parent::__construct($this->_sid );

		$this->_publicDir = REVIEW_PATH . 'public/';
		$this->_publicUrl = REVIEW_URL;
		$this->_langDir = REVIEW_PATH . 'languages/';
		$this->_layoutDir = REVIEW_PATH . 'layout/';
        try {
            $this->_languages = Zend_Registry::get('languages');
        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage());
        }
    }
}