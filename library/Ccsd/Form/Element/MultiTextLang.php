<?php

/**
 * Class Ccsd_Form_Element_MultiTextLang
 */
abstract class Ccsd_Form_Element_MultiTextLang extends Ccsd_Form_Element_MultiText
{
    use Ccsd_Form_Trait_Populate;

    protected $_languages;
    protected $_pluriValues = false;
    protected $_indice = "";
    protected $_stillChoice = true;
    /**
     * @var bool
     * @deprecated because unused so certainly badly treated...
     * @unused just set!
     */
    protected $hasShiftValue = false;

    const VALIDATOR_NOT_SAME = 'NotSame';

    public function init ()
    {
        parent::init();
        $this->build ();
        $this->_languages = $this->getData();
    }

    /**
     * @param mixed $value
     * @return Zend_Form_Element
     */
    public function setValue ($value)
    {
    	if ($this->_split)
    		$value = (new Ccsd_Form_Filter_Keyword())->filter($value);

    	if ($this->isPluriValues()) {
    		return parent::setValue(array_map(function ($v) { if (!is_array($v)) return $v; else return array_filter ($v); }, $value ? $value : array ()));
    	}
    	if ($value == null) {
            $value = array();
        }
        return parent::setValue(array_filter($value));
    }

    /**
     * @return string
     */
    public function getFullyQualifiedName() 
    {
        $name = $this->getName();
        
        if (null !== ($belongsTo = $this->getBelongsTo())) {
            $name = $belongsTo . '[' . $name . ']';
        }

        if ($this->isArray()) {
            $name .= '[' . $this->_indice . ']';
            
            if ($this->isPluriValues()) {
                $name .= '[]';
            }
        }

        return $name;
    }

    /**
     * @return bool
     */
    public function isPluriValues ()
    {
        return $this->_pluriValues;
    }

    /**
     * @param bool $plurivalues
     */
    public function setPluriValues ($plurivalues = false)
    {
    	$this->_pluriValues = $plurivalues;
    }

    /**
     * @param string $indice
     * @return $this
     */
    public function setIndice ($indice)
    {
        $this->_indice = $indice;
        return $this;
    }

    /**
     * @return string
     */
    public function getIndice ()
    {
        return $this->_indice;
    }

    /**
     * @param array $languages
     */
    public function setLanguages ($languages)
    {
        $this->_languages = array_combine ($languages, $languages);
        
        foreach ($this->_languages as $i => $libelle) {
            $this->_languages[$i] = Zend_Locale::getTranslation($libelle, 'language', $this->getTranslator()->getLocale());
        }
    }

    /**
     * @return mixed
     */
    public function getLanguages ()
    {
        return $this->_languages;
    }

    /**
     * @param string $val
     * @param string $lang
     * @param string $content
     * @param Ccsd_Form_Decorator_Group $decorator
     * @param bool $lastElement
     * @param string $subIndice
     * @return string
     * @throws Zend_Form_Exception
     */
    protected function createFilledElement($val, $lang, $content, &$decorator, $lastElement = false, $subIndice = "none")
    {
        $this->_value = $val;
        $clone = clone $this;
        $clone->setIndice($lang);
        $clone->setClone($lastElement);
        $clone->setAttrib('lang', $lang);

        if (!$clone->getSplit() && !$lastElement) {
            $clone->setAttrib('data-language', $lang);
        }

        if ($lastElement) {
            $clone->setStillChoice(($lang === 0 || $lang === '0') ? true : (bool)$lang);
        }

        $decorator->indice = $lang;

        if ("none" != $subIndice) {
            $decorator->subIndice = $subIndice;
        }

        $decorator->setElement($clone);
        $content = $decorator->render ($content);
        $this->setJavascript($clone->getJavascript());

        unset ($clone);

        return $content;
    }

    /**
     * @param Ccsd_Form_Decorator_Group $decorator
     * @param string $content
     * @return string
     * @throws Zend_Form_Exception
     */
    public function renderValues ($decorator, $content = '')
    {
    	$this->_unprocessedValues = $this->_value;
    	$key   = key($this->_languages);

    	$usedLangs = [];

    	// On ajoute les champs qui ont une valeur
    	if (is_array($this->_value) && !empty($this->_value)) {
	    	foreach ($this->_value as $lang => $value) {
	    		if (is_array ($value) && !empty($this->_value)) {
	    			foreach ($value as $i => $val) {
                        $content = $this->createFilledElement($val, $lang, $content, $decorator, false, $i);
	    			}	    			
	    		} else {
                    $content = $this->createFilledElement($value, $lang, $content, $decorator);
	    		}
                $usedLangs[] = $lang;
	    	}
	    	
	    	$key = $this->isPluriValues() ? key($this->_languages) : key(array_diff_key($this->_languages, $this->_unprocessedValues ? $this->_unprocessedValues : array()));
	    }

        // On récupère le nombre de langues obligatoires
        /** @var Ccsd_Form_Validate_RequiredLang $langValidator */
        $langValidator = $this->getValidator('RequiredLang');
        $nb = $langValidator ? $langValidator->getMin() : 1;

    	// Si le nombre de langues obligatoires -1 est rempli, on ne passe pas par cette boucle
        // Sinon, on ajoute $nb-1 champs vides (le dernier sera ajouté en dehors de la boucle)
	    for ($j = count($usedLangs) ; $j < $nb-1 ; $j++) {
            $content = $this->createFilledElement("", $key, $content, $decorator, false, $j);

            $usedLangs[] = $key;
            $unusedLangs = array_diff(array_keys($this->_languages), $usedLangs);

            $key = array_shift($unusedLangs);
        }

        // On ajoute toujours un champs vide quelque soit le cas de figure (ce champs vide n'a pas les mêmes éléments graphiques : il a le + qui permet d'ajouter d'autres champs après lui)
        // Soit il n'y a aucun champ rempli, ni obligatoire
        // Soit tous les champs obligatoires sont remplis
        // Soit on a ajouté $nb-1 champs vides obligatoires et on finit d'ajouter le dernier
        $content = $this->createFilledElement("", $key, $content, $decorator, true);
        $this->_value = $this->_unprocessedValues;


		return $content;
    }

    /**
     * @param bool $b
     * @return $this
     */
    public function setStillChoice ($b = true)
    {
    	$this->_stillChoice = $b;
    	return $this;
    }

    /**
     * @return bool
     */
    public function isStillChoice ()
    {
    	return $this->_stillChoice;
    }

    /**
     * @throws Zend_Loader_Exception
     */
    public function initValidators ()
    {
        if (!$this->isPluriValues() && !$this->getPluginLoader('validate')->isLoaded(self::VALIDATOR_NOT_SAME)) {
            $validators = $this->getValidators();
            $notSame   = array('validator' => 'NotSame', 'breakChainOnFailure' => false);
            array_unshift($validators, $notSame);
            $this->setValidators($validators);
        }
    }

    /**
     * @param Zend_Form_Element $validator  // Zend_Validate_Interface devrait suffire... Mais NON! Merci Zend
     * @param array $value
     * @param $messages
     * @param $errors
     * @param $result
     * @param null $context
     */
    public function execValidators (&$validator, $value = array (), &$messages, &$errors, &$result, $context = null)
    {
        foreach ((array)$value as $lang => $val) {
            if ($this->isPluriValues()) {
                if (is_array ($value) && !empty($value)) {
                    foreach ($val as $i => $v) {
                        if (!$validator->isValid($v, $context)) {
                            $result = false;

                            if ($this->_hasErrorMessages()) {
                                $messages = $this->_getErrorMessages();
                                $errors   = $messages;
                            } else {
                                if (!array_key_exists ($lang, $messages)) {
                                    $messages[$lang] = array ();
                                }
                                
                                if (!array_key_exists ($i, $messages[$lang])) {
                                    $messages[$lang][$i] = array ();
                                }

                                $messages[$lang][$i] = array_merge($messages[$lang][$i], $validator->getMessages());
                                
                                if (!array_key_exists ($lang, $errors)) {
                                    $errors[$lang] = array ();
                                }
                                
                                $errors[$lang] = $errors[$lang] + array ($i => $validator->getErrors());
                            }
                        }
                    }
                }
            } else if (!$validator->isValid($val, $context)) {
                $result = false;

                if ($this->_hasErrorMessages()) {
                    $messages = $this->_getErrorMessages();
                    $errors   = $messages;
                } else {
                    if (!array_key_exists ($lang, $messages)) {
                        $messages[$lang] = array ();
                    }
    
                    $messages[$lang] = array_merge($messages[$lang], $validator->getMessages());
                    $errors   = $errors + array ($lang => $validator->getErrors());
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
        if (!$this->isPluriValues()) {
            $v = array_slice ($value,0 ,1, true);
            
            foreach ($v as $lang => $val) {
                if (!$val) {
                    $this->hasShiftValue = true;
                    array_shift ($value);
                }
            }
        } else {
            $i = 0;
            foreach ($value as $lang => $val) {
                if ($i == 0 && is_array ($val) && !empty($val)) {
                    $v = array_slice ($val,0 ,1, true);

                    foreach ($v as $k => $vv) {
                        if (!$vv) {
                            $this->hasShiftValue = true;
                            array_shift ($value[$lang]);
                            
                            if (!$value[$lang]) {
                                unset ($value[$lang]);
                            }
                        }
                    }
                }
                
                $i++;
            }
        }

        $this->_value = $value;

        return $value;
    }

    /**
     * @param $validator
     */
    public function setConditionValidators ($validator)
    {
        $this->_conditionValidators = !$validator instanceof Ccsd_Form_Validate_NotSame && !$validator instanceof Ccsd_Form_Validate_RequiredLang;
    }

    /**
     * @return array
     */
    public function getGroupErrors()
    {
        return $this->getErrors();
    }
}