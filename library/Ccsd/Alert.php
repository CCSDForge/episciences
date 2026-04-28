<?php

/**
 * Ebauche de classe permettant gérer des alertes dans le codes (exceptions trappés, ...)
 * Ces alertes pourront être envoyées par mail ou autre
 * @author yannick
 *
 */
class Ccsd_Alert
{
	const WEBSITE = "website";
	
	
	static public function add($category, $msg)
	{
		echo $msg;
		//Envoi du mail...
		
	}
	
}