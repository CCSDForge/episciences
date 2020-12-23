<?php

//Messages d'erreurs
define('MSG_ERROR', 'danger');
define('MSG_WARNING', 'warning');
define('MSG_SUCCESS', 'success');
define('MSG_INFO', 'info');

class Ccsd_View_Helper_DisplayFlashMessages extends Zend_View_Helper_Abstract
{
	public function DisplayFlashMessages($namespace = '', $autoclose=true)
	{
		$result = array();
	    $flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

	    if ($namespace == '') {
	    	$namespace = array(MSG_ERROR, MSG_SUCCESS, MSG_WARNING, MSG_INFO);
	    } else {
	    	$namespace = array($namespace);
	    }

	    foreach ($namespace as $ns) {
		    $resultNamespace = array();
	    	if ($flashMessenger->setNamespace($ns)->hasMessages() ||
				$flashMessenger->setNamespace($ns)->hasCurrentMessages()) {

			    if ($ns == MSG_ERROR || $ns == MSG_SUCCESS || $ns == MSG_WARNING || $ns == MSG_INFO) {
			        $class = 'alert-' . $ns;
			    } else {
			        $class = 'alert-' . MSG_INFO;
			    }

			    $resultNamespace[] = '<div class="alert '.$class.' fade">';
			    $resultNamespace[] = '<button type="button" class="close" data-dismiss="alert">&times;</button>';

			    $messages = array_merge($flashMessenger->getCurrentMessages(), $flashMessenger->getMessages());

				foreach ($messages as $message) {
					if (is_array($message) && !array_key_exists('plural', $message)) {
						$resultNamespace[] = "<div>" . call_user_func_array(array(&$this->view, 'translate'), $message) . "</div>";
					} else {
						$resultNamespace[] = "<div>" . $this->view->translate($message) . "</div>";
					}
			    }

			    $resultNamespace[] = '</div>';
			    $result[] = implode(PHP_EOL, $resultNamespace);

			    $flashMessenger->clearCurrentMessages();
			}
	    }

	    if ($result) {

	    	$result[] = '<script language="Javascript">';
	    	$result[] = '$(function() {';
	    	$result[] = 'if ($(".alert").length) {';
    		$result[] = 	'$(".alert").addClass("in");';
	    	if ($autoclose) {
	    		$result[] = 'setTimeout(function() {$(".alert:not(.alert-fixed)").alert("close");},10000);';
	    	}$result[] = '}';
	    	$result[] = '});';
    		$result[] = '</script>';
	    }

	    $message = implode(PHP_EOL, $result);

	    return $message;

    }
}
