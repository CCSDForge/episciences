<?php

class Ccsd_Form_Validate_RequiredLang extends Zend_Validate_Abstract
{   
    const REQUIRED_LANG   = 'required_lang';
    const TOO_SHORT       = 'too_short';

    /**
     * @var array
     */
    protected $_messageTemplates = array(
        self::REQUIRED_LANG  => "Valeur(s) manquante(s) pour les langues suivantes : '%value%'",
        self::TOO_SHORT      => "Vous devez renseigner au moins %min% langues différentes",
    );

    /**
     * @var array
     */
    protected $_messageVariables = [
        'min' => '_min',
        'langs' => '_langs'
    ];

    /**
     * Required langs
     *
     * @var array
     */
    protected $_langs;

    /**
     * Minimum required value
     *
     * @var integer
     */
    protected $_min;

    /**
     * Sets validator options
     *
     * @param  integer|array|Zend_Config $options
     * @return void
     */
    public function __construct($options = [])
    {
        if ($options instanceof Zend_Config) {
            $options = $options->toArray();
        } else if (!is_array($options)) {
            $options = func_get_args();
            $options = array_shift($options);
        }

        if (!array_key_exists('min', $options)) {
            if (!is_array($this->_langs)) {
                $options['min'] = 0;
            } else {
                $options['min'] = count($this->_langs);
            }
        }

        $this->setMin($options['min']);

        if (!array_key_exists('langs', $options)) {

            if (!array_key_exists('populate', $options)) {
                /**
                 * @see Zend_Validate_Exception
                 */
                require_once 'Zend/Validate/Exception.php';
                throw new Zend_Validate_Exception("Au moins une langues est requise pour ce validateur, 0 donné");
            }

            $this->setPopulate($options['populate']);

        } else {
            $this->setLangs($options['langs']);
        }
    }

    /**
     * Fixe l'option populate
     *
     * @param  array $options
     * @throws Zend_Validate_Exception
     * @return Ccsd_Form_Validate_RequiredLang Provides a fluent interface
     */
    public function setPopulate($options = [])
    {
        if (!(isset($options['class']) && isset ($options['method']))) {
            /**
             * @see Zend_Validate_Exception
             */
            require_once 'Zend/Validate/Exception.php';
            throw new Zend_Validate_Exception("Impossible d'accéder aux informations nécessaires pour définir les langues requises");
        }


        if (!class_exists($options['class'])) {
            /**
             * @see Zend_Validate_Exception
             */
            require_once 'Zend/Validate/Exception.php';
            throw new Zend_Form_Exception(sprintf('Class not found', $options['class']));
        }


        try {

            $reflectionMethod = new ReflectionMethod($options['class'], $options['method']);

            $pass = [];

            foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                if (isset($options['args']) && !array_key_exists($reflectionParameter->name, $options['args'])) {
                    /**
                     * @see Zend_Validate_Exception
                     */
                    require_once 'Zend/Validate/Exception.php';
                    throw new Zend_Form_Exception(sprintf('Paramètre requis', $reflectionParameter->name));
                } else {
                    $pass[] = $options['args'][$reflectionParameter->name];
                }
            }

            if (empty ($pass)) {
                $this->setLangs($reflectionMethod->invoke(null));
            } else {
                $this->setLangs($reflectionMethod->invokeArgs($reflectionMethod, $pass));
            }

        } catch (Exception $e) {
            /**
             * @see Zend_Validate_Exception
             */
            require_once 'Zend/Validate/Exception.php';
            throw new Zend_Form_Exception("Impossible d'accéder aux informations nécessaires pour définir les langues requises");
        }
    }

    /**
     * Retourne l'option langs
     *
     * @return array
     */
    public function getLangs()
    {
        return $this->_langs;
    }

    /**
     * Fixe l'optionlangs
     *
     * @param  mixed $langs
     * @throws Zend_Validate_Exception
     * @return Ccsd_Form_Validate_RequiredLang Provides a fluent interface
     */
    public function setLangs($langs)
    {
        if (!is_array($langs)) {
            $langs = [$langs];
        }

        $this->_langs = $langs;
        return $this;
    }

    /**
     * Retourne l'option min
     *
     * @return integer
     */
    public function getMin()
    {
        return $this->_min;
    }

    /**
     * Fixe l'option min
     *
     * @param  integer $min
     * @throws Zend_Validate_Exception
     * @return Ccsd_Form_Validate_RequiredLang Provides a fluent interface
     */
    public function setMin($min)
    {
        if (!is_array($this->_langs)) {
            $nbOfLangs = 0;
        } else {
            $nbOfLangs = count($this->_langs);
        }

        if ($min < $nbOfLangs) {
            /**
             * @see Zend_Validate_Exception
             */
            require_once 'Zend/Validate/Exception.php';
            throw new Zend_Validate_Exception("Le minimum de réponse doit être au moins équivalent aux nombres de langues requises, mais minimum ($min) <"
                . $nbOfLangs . " (nombres de langues requises)");
        }

        $this->_min = max(0, (integer)$min);
        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Retourne TRUE si et seulement si parmi les valeurs toutes les langues requises sont renseignés et si
     * le nombre de valeurs renseignés est égale ou supérieur à l'option min
     *
     * @param  mixed $value
     * @return boolean
     */
    public function isValid($value)
    {
        $diff = array_diff($this->_langs, array_keys($value));

        if (!empty ($diff)) {
            $this->_error(self::REQUIRED_LANG, implode(", ", array_map(function ($v) {
                return Ccsd_Form::getDefaultTranslator()->translate("lang_$v");
            }, $diff)));
            return false;
        }

        if ($this->_min > count($this->_langs) && count(array_keys($value)) < $this->_min) {
            $this->_error(self::TOO_SHORT);
            return false;
        }

        return true;
    }
}