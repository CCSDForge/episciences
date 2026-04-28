<?php

class Episciences_Form_Decorator_Html extends Zend_Form_Decorator_Abstract
{
	public function render($content) {
		$placement = $this->getPlacement();
		switch ($placement) {
			case self::APPEND:
				return $content . $this->_options['html'];
				break;
			case self::PREPEND:
				return $this->_options['html'] . $content;
				break;
		}
	}
}