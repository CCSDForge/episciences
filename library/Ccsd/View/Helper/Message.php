<?php
/**
 * Helper pour des messages stylés bootstrap
 * @author rtournoy
 *
 */
class Ccsd_View_Helper_Message extends Ccsd_View_Helper_Abstract
{
    // Type de messages d'erreurs
    const MSG_ERROR   = 'danger';
    const MSG_WARNING = 'warning';
    const MSG_SUCCESS = 'success';
    const MSG_INFO    = 'info';
    /**
     * @var array
     */
    private $_output = array();
    /**
     * @var string
     */
    private $_messageType;
    /**
     * @var string
     */
    private $_message;
    /**
     * @var boolean
     */
    private $_autoclose;
    /**
     *
     * @param string $message
     * @param string $messageType
     * @param boolean $autoclose
     * @return string
     */
    public function message ($message, $messageType = '', $autoclose = false)
    {
        $this->setMessageType($messageType);
        $this->setMessage($message);
        $this->setAutoclose($autoclose);
        $this->setHtml();
        $this->addJavascript();
        return implode(PHP_EOL, $this->getOutput());
    }
    /**
     * ajoute le HTML a l'output interne
     * @return void
     */
    private function setHtml ()
    {
        $output[] = '<div class="alert ' . $this->getMessageType() . ' fade">';
        $output[] = '<button type="button" class="close" data-dismiss="alert">&times;</button>';
        $output[] = "<div>" . $this->view->translate($this->getMessage()) . "</div>";
        $output[] = '</div>';
        $this->setOutput($output);
    }
    /**
     * ajoute le javascript a l'output interne
     * @return void
     */
    private function addJavascript ()
    {
        $output = $this->getOutput();

        $output[] = '<script language="javascript">';
        $output[] = '$(function() {';
        $output[] = 'if ($(".alert").length) {';
        $output[] = '$(".alert").addClass("in");';
        if ($this->getAutoclose()) {
            $output[] = 'setTimeout(function() {$(".alert").alert("close");},10000);';
        }
        $output[] = '}';
        $output[] = '});';
        $output[] = '</script>';

        $this->setOutput($output);
    }
    /**
     *
     * @return string[] $_output
     */
    public function getOutput ()
    {
        return $this->_output;
    }
    /**
     * @param string[]: $_output
     * @return Ccsd_View_Helper_Message
     */
    public function setOutput ($_output)
    {
        $this->_output = $_output;
        return $this;
    }
    /**
     * @return string $_messageType
     */
    public function getMessageType ()
    {
        return $this->_messageType;
    }
    /**
     * @return string $_message
     */
    public function getMessage ()
    {
        return $this->_message;
    }
    /**
     * @param string $_messageType
     * @return Ccsd_View_Helper_Message
     */
    public function setMessageType ($_messageType)
    {
        switch ($_messageType) {
            case self::MSG_ERROR:
            case self::MSG_WARNING:
            case self::MSG_SUCCESS:
            case self::MSG_INFO:
                $class = 'alert-' . $_messageType;
                break;
            default:
                $class = 'alert-' . self::MSG_INFO;
                break;
        }

        $this->_messageType = $class;
        return $this;
    }
    /**
     * @param string $_message
     * @return Ccsd_View_Helper_Message
     */
    public function setMessage ($_message)
    {
        $this->_message = $_message;
        return $this;
    }
    /**
     * @return bool $_autoclose
     */
    public function getAutoclose ()
    {
        return $this->_autoclose;
    }
    /**
     * @param bool $_autoclose
     * @return Ccsd_View_Helper_Message
     */
    public function setAutoclose ($_autoclose)
    {
        $this->_autoclose = $_autoclose;
        return $this;
    }
}
