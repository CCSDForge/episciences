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
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

		echo $msg;
		//Envoi du mail...
		
	}
	
}