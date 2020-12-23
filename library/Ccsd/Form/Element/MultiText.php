<?php

/**
 * Class Ccsd_Form_Element_MultiText
 */
abstract class Ccsd_Form_Element_MultiText extends Zend_Form_Element_Multi implements Ccsd_Form_Interface_Javascript
{
    use Ccsd_Form_Trait_ImplementFunctionJS;
    public $pathDir = __DIR__ ;
    public $relPublicDirPath = "../../../public"; 
	
    const DISPLAY_ADVANCED = 'advanced';
    const DISPLAY_SIMPLE   = 'simple';

    protected $_display = self::DISPLAY_SIMPLE;
    protected $_conditionValidators = true;
    protected $_grouperrors = array ();
    protected $_isClone = false;
    protected $_length = 15;
    protected $_split = false;
    protected $_tinymce = false;
    protected $_isArray = true;
    protected $_unprocessedValues = array ();
    protected $_tiny = false;

    /**
     * @throws Zend_Form_Exception
     */
    public function init ()
    {
        $this->addPrefixPath('Ccsd_Form_Decorator_', 'Ccsd/Form/Decorator/', Zend_Form::DECORATOR);
        $this->addPrefixPath('Ccsd_Form_Validate', 'Ccsd/Form/Validate', 'validate');

        if (!isset ($this->_display)) {
            $this->_display = self::DISPLAY_SIMPLE;
        }

        parent::init();
    }

    /**
     * @return Ccsd_Form_Element_MultiText $this
     */
    public function getElement ()
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getPrefix ()
    {
        $prefix = "multi/";

        if (strpos (get_class ($this), "Ccsd_Form_Element_MultiTextArea") !== FALSE) {
            $prefix .= "area/";
        } else {
            $prefix .= "text/";
        }

        $prefix .=  $this->getDisplay() . "/";

        if (method_exists($this, 'getLanguages')) {
            $prefix .= "lang/";
        }

        if (method_exists($this, 'isPluriValues') && $this->isPluriValues()) {
            $prefix .= "keyword/";
        }

        if (method_exists($this, 'getSplit') && $this->getSplit()) {
            $prefix .= 'split.';
        }

        return $prefix;
    }

    /**
     * Load default decorators
     *
     * @return Zend_Form_Element
     * @throws Zend_Form_Exception
     */
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            $this->addDecorator('Group')
                ->addDecorator('GroupErrors', array ('placement' => Zend_Form_Decorator_Abstract::PREPEND))
                ->addDecorator('Description', array ('tag' => 'span', 'class' => 'help-block'))
                ->addDecorator('HtmlTag', array('tag' => 'div', 'class'  => "col-md-9"))
                ->addDecorator('Label', array('tag' => 'label', 'class' => "col-md-3 control-label"));
        }
        return $this;
    }

    /**
     * @param mixed $value
     * @return Zend_Form_Element
     */
    public function setValue ($value)
    {
        if ($this->_split) {
            $value = (new Ccsd_Form_Filter_Keyword())->filter($value);
        }
        if ($value == null) {
            $value = array();
        }
        if (!is_array($value)) {
            $value = array($value);
        }
        return parent::setValue(array_filter($value));
    }

    /**
     * @return string
     */
    public function getDisplay ()
    {
        if ($this->_display) {
            return $this->_display;
        } else {
            // Default value: normaly, allways set unless ...
            return self::DISPLAY_SIMPLE;
        }
    }

    /**
     * @param $display
     * @return $this
     */
    public function setDisplay ($display)
    {
        if (in_array ($display, array (self::DISPLAY_SIMPLE, self::DISPLAY_ADVANCED))) {
            $this->_display = $display;
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getSplit ()
    {
        return $this->_split;
    }

    /**
     * @param bool $split
     * @return $this
     */
    public function setSplit ($split = false)
    {
        $this->_split = $split;
        return $this;
    }

    /**
     * @return bool
     */
    public function getTinymce ()
    {
        return $this->_tinymce;
    }

    /**
     * @param bool $tinymce
     * @return $this
     */
    public function setTinymce ($tinymce = false)
    {
        if (!($this instanceof Ccsd_Form_Element_MultiTextArea || $this instanceof Ccsd_Form_Element_MultiTextAreaLang))
            $tinymce = false;
        $this->_tinymce = $tinymce;
        return $this;
    }

    /**
     * @return int
     */
    public function getLength ()
    {
        return $this->_length;
    }

    /**
     * @param $length
     * @return $this
     */
    public function setLength ($length)
    {
        $this->_length = $length;
        return $this;
    }

    /**
     * @param bool $b
     * @return $this
     */
    public function setClone ($b = false)
    {
        $this->_isClone = $b;
        return $this;
    }

    /**
     * @return bool
     */
    public function isClone ()
    {
        return $this->_isClone;
    }

    /**
     * @param $messages
     */
    public function setMessages ($messages)
    {
        $this->_messages = $messages;
    }

    /**
     * @return array
     */
    public function getUnprocessedValues()
    {
        return $this->_unprocessedValues;
    }

    /**
     * @param bool $b
     * @return $this
     * @throws Exception
     */
    public function setTiny ($b = false)
    {
        if (!$this instanceof Ccsd_Form_Element_MultiTextArea && !$this instanceof Ccsd_Form_Element_MultiTextAreaLang)
            throw new Exception ("Invalid type object");

        $this->_tiny = $b;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTiny ()
    {
        return $this->_tiny;
    }

    /**
     * @param Zend_Form_Decorator_Abstract $decorator
     * @param string $content
     * @return string
     * @throws Zend_Form_Decorator_Exception
     */
    public function renderValues ($decorator, $content = '')
    {
        $this->_unprocessedValues = $this->_value;

        if (is_array($this->_value) && !empty($this->_value)) {
            foreach ($this->_value as $i => $value) {
                $this->_value = $value;
                $clone = clone $this;
                $clone->setClone(false);
                $decorator->indice = $i;
                $decorator->setElement($clone);
                $content = $decorator->render($content);
                $this->setJavascript($clone->getJavascript());
                unset ($clone);
            }
        }

        $this->_value = "";
        $clone = clone $this;
        $clone->setClone(true);
        $decorator->setElement($clone);
        $content = $decorator->render($content);
        $this->setJavascript($clone->getJavascript());
        $this->_value = $this->_unprocessedValues;

        return $content;
    }

    /**
     * @param Zend_View_Interface|null $view
     * @return string
     * @throws Zend_Form_Decorator_Exception
     */
    public function render(Zend_View_Interface $view = null)
    {
        if ($this->_isPartialRendering) {
            return '';
        }

        if (null !== $view) {
            $this->setView($view);
        }

        $content = '';
        /** @var Zend_Form_Decorator_Abstract $decorator */
        foreach ($this->getDecorators() as $decorator) {
            if ($decorator instanceof Ccsd_Form_Decorator_Group) {
                $this->_unprocessedValues = $this->_value;
                $content = $this->renderValues($decorator, $content);
            } else {
                $decorator->setElement($this);
                $content = $decorator->render($content);
            }
        }

        return $content;
    }

    /**
     *
     */
    public function initValidators ()
    {

    }

    /**
     * @param array $errors
     * @param bool $groupValidate
     */
    public function errors ($errors = array(), $groupValidate = false)
    {
        $errorname = "_";
        if ($groupValidate) {
            $errorname .= "group";
        }
        $errorname .= "errors";

        foreach ($errors as $i => $error) {
            if (array_key_exists ($i, $this->$errorname)) {
                if (is_array ($error)) {
                    $this->{$errorname}[$i] = array_merge ($this->{$errorname}[$i], $error);
                } else {
                    $this->{$errorname}[$i] = array_merge ($this->{$errorname}[$i], array($error));
                }
            } else {
                if ($groupValidate) {
                    $error = array ($error);
                }
                $this->{$errorname}[$i] = $error;
            }
        }
    }

    /**
     * @param Zend_Validate_Interface $validator
     * @param array $value
     * @param $messages
     * @param $errors
     * @param $result
     * @param null $context
     */
    public function execValidators (&$validator, $value = array (), &$messages, &$errors, &$result, $context = null)
    {
        foreach ((array)$value as $i => $v) {
            if (!$validator->isValid($v, $context)) {
                $result = false;

                if ($this->_hasErrorMessages()) {
                    $messages = $this->_getErrorMessages();
                    $errors   = $messages;
                } else {
                    if (!array_key_exists ($i, $messages)) {
                        $messages[$i] = array ();
                    }

                    $messages[$i] = array_merge($messages[$i], $validator->getMessages());
                    $errors   = $errors + array ($i => $validator->getErrors());
                }
            }
        }
    }

    /**
     * @param array $value
     * @return array
     */
    public function prepareValues ($value = array ())
    {
        $v = array_shift ($value);

        if ($v) {
            array_unshift ($value, $v);
        } else {
            $this->hasShiftValue = true;
        }

        $this->_value = $value;

        return $value;
    }

    /**
     * @param $validator
     */
    public function setConditionValidators ($validator)
    {

    }

    /**
     * @return array
     */
    public function getGroupErrors()
    {
        return $this->_grouperrors;
    }

    /**
     * @param $errors
     * @return $this
     */
    public function setGroupErrors($errors )
    {
        $this->_grouperrors = array($errors);
        $this->_messages = array_flip (array_combine($errors, $errors));
        $this->markAsError();
        return $this;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return (!empty($this->_messages) || $this->_isError || !empty($this->_grouperrors));
    }

    /**
     * @param string $value
     * @param null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        $this->setValue($value);
        $value = $this->getValue();

        $isArray = $this->isArray();

        if ($isArray && is_array($value)) {
            $value = $this->prepareValues($value);
        }

        if ((('' === $value) || (null === $value))
            && !$this->isRequired()
            && $this->getAllowEmpty()
        ) {
            return true;
        }

        if (Zend_Validate_Abstract::hasDefaultTranslator() && !Zend_Form::hasDefaultTranslator()) {
            $translator = Zend_Validate_Abstract::getDefaultTranslator();
            if ($this->hasTranslator()) {
                $translator = $this->getTranslator();
            }
        } else {
            $translator = $this->getTranslator();
        }

        if ($this->isRequired() && $this->autoInsertNotEmptyValidator() && !$this->getValidator('NotEmpty'))
        {
            if (empty ($value)) {
                array_push ($this->_grouperrors, array (Zend_Validate_NotEmpty::IS_EMPTY));

                $this->_messages[Zend_Validate_NotEmpty::IS_EMPTY] = $translator->translate ((new Zend_Validate_NotEmpty())->getMessageTemplates()[Zend_Validate_NotEmpty::IS_EMPTY]);
                $this->_errors = array_keys($this->_messages);

                return false;
            }

            $validators = $this->getValidators();
            $notEmpty   = array('validator' => 'NotEmpty', 'breakChainOnFailure' => false);
            array_unshift($validators, $notEmpty);
            $this->setValidators($validators);
        }

        $this->initValidators();

        $this->_messages = array();
        $this->_errors   = array();
        $result          = true;
        /** @var Zend_Validate_Interface  $validator */
        foreach ($this->getValidators() as $key => $validator) {
            if (method_exists($validator, 'setTranslator')) {
                if (method_exists($validator, 'hasTranslator')) {
                    if (!$validator->hasTranslator()) {
                        $validator->setTranslator($translator);
                    }
                } else {
                    $validator->setTranslator($translator);
                }
            }

            if (method_exists($validator, 'setDisableTranslator')) {
                $validator->setDisableTranslator($this->translatorIsDisabled());
            }

            $this->setConditionValidators ($validator);

            if ($isArray && $this->_conditionValidators && is_array($value)) {
                $messages   = array();
                $errors   = array();

                if (empty($value)) {
                    if ($this->isRequired()
                        || (!$this->isRequired() && !$this->getAllowEmpty())
                    ) {
                        $value = '';
                    }
                }

                if (!empty($value)) {
                    $this->execValidators ($validator, $value, $messages, $errors, $result, $context);
                }

                if ($result) {
                    continue;
                }
            } elseif ($validator->isValid($value, $context)) {
                continue;
            } else {
                $result = false;
                if ($this->_hasErrorMessages()) {
                    $messages = $this->_getErrorMessages();
                    $errors   = $messages;
                } else {
                    $messages = $validator->getMessages();
                    $errors   = array_keys($messages);
                }
            }

            $result = false;

            // À refaire proprement
            foreach ($messages as $messkey => $message) {
                if (is_numeric($messkey)) {
                    $this->_messages = array_merge($this->_messages, $message);
                    $this->_conditionValidators = false;
                } else {
                    $this->_messages = array_merge($this->_messages, $messages);
                }
            }

            $this->errors ($errors, !$this->_conditionValidators);

            if ($this->zfBreakChainOnFailure) {
                break;
            }
        }

        // If element manually flagged as invalid, return false
        if ($this->_isErrorForced) {
            return false;
        }

        return $result;
    }
}
