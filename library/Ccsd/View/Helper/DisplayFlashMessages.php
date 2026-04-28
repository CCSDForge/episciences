<?php

class Ccsd_View_Helper_DisplayFlashMessages extends Zend_View_Helper_Abstract
{
    //Messages d'erreurs
    public const MSG_ERROR = 'danger';
    public const MSG_WARNING = 'warning';
    public const MSG_SUCCESS = 'success';
    public const MSG_INFO = 'info';

    public function DisplayFlashMessages(string $namespace = '', bool $autoclose = true): string
    {
        $result =  [];
        $flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

        if ($namespace == '') {
            $namespace = array(self::MSG_ERROR, self::MSG_SUCCESS, self::MSG_WARNING, self::MSG_INFO);
        } else {
            $namespace = array($namespace);
        }

        foreach ($namespace as $ns) {
            $resultNamespace = array();
            if ($flashMessenger->setNamespace($ns)->hasMessages() ||
                $flashMessenger->setNamespace($ns)->hasCurrentMessages()) {

                if ($ns == self::MSG_ERROR || $ns == self::MSG_SUCCESS || $ns == self::MSG_WARNING || $ns == self::MSG_INFO) {
                    $class = 'alert-' . $ns;
                } else {
                    $class = 'alert-' . self::MSG_INFO;
                }

                $resultNamespace[] = '<div class="alert ' . $class . ' fade">';
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
            $result[] = '$(".alert").addClass("in");';
            if ($autoclose) {
                $result[] = 'setTimeout(function() {$(".alert:not(.alert-fixed)").alert("close");},10000);';
            }
            $result[] = '}';
            $result[] = '});';
            $result[] = '</script>';
        }

        return implode(PHP_EOL, $result);

    }
}
