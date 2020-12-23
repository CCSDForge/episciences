<?php
class Ccsd_SimpleXMLElement extends SimpleXMLElement
{
	public function addChild($name, $value = null, $ns = null) {
		return parent::addChild($name, Ccsd_Tools_String::xmlSafe($value), $ns);
	}
	public function addAttribute($name, $value = null, $ns = null) {
		return parent::addAttribute($name, Ccsd_Tools_String::xmlSafe($value), $ns);
	}
}