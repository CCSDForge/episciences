<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 05/07/17
 * Time: 11:16
 */

/**
 * @deprecated Use Ccsd_View_Helper_DisplayFlashMessages instead
 *
 */
class Ccsd_FlashMessenger {

    /**
     * @var Ccsd_FlashMessengerItem[]
     */
    protected $messages = array();

    /**
     * Ccsd_FlashMessenger constructor.
     * @param string $severity
     * @param string $msg
     */
    public function __construct($severity, $msg) {
        $this -> addMessage($severity, $msg);
    }

    /**
     * @param Zend_Controller_Action_Helper_FlashMessenger $zendFlashMessengerObj
     */
    public function toSessionFlashMessenger($zendFlashMessengerObj) {
        foreach ($this -> messages as $item) {
            $zendFlashMessengerObj -> setNamespace($item -> getSeverity()) -> addMessage($item->getMessage());
        }
    }

    /**
     * @return string
     */
    public function __toString() {
        $s = '';
        foreach ($this -> messages as $item) {
            $s .= $item -> __toString();
        }
        return $s;
    }

    /**
     * @param string $severity
     * @param string $msg
     */
    public function addMessage($severity, $msg) {
        $this -> messages[] = new Ccsd_FlashMessengerItem($severity, $msg);
    }
}

class Ccsd_FlashMessengerItem {
    protected $severity = null;
    protected $message  = null;

    /**
     * Ccsd_FlashMessengerItem constructor.
     * @param string $severity
     * @param string $msg
     */
    public function __construct($severity, $msg) {
        $this -> severity = $severity;
        $this -> message = $msg;
    }

    /**
     * @return null|string
     */
    public function getSeverity() {
        return $this -> severity;
    }

    /**
     * @return null|string
     */
    public function getMessage() {
        return $this -> message;
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this -> getSeverity() . " : " . $this->getMessage(). "\n";
    }
}