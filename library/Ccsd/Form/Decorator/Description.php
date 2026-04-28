<?php

class Ccsd_Form_Decorator_Description extends Zend_Form_Decorator_Description
{
	protected $_placement = 'PREPEND';
	
	/**
	 * Whether or not to escape the description
	 * @var bool
	 */
	protected $_escape = false;
}