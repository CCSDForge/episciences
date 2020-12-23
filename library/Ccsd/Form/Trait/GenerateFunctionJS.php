<?php

/**
 * Trait Ccsd_Form_Trait_GenerateFunctionJS
 *
 * Trait pour générer les fonctions javascript dans les décorateurs d'éléments
 * utilisé dans Ccsd_Form_Decorator_*
 *
 * Déclarée par __call
 * @method buildJS(string $path, array $option)
 *
 */
trait Ccsd_Form_Trait_GenerateFunctionJS {
    
    protected $_prefix;

    /**
     * Remplace les %%XXXi%% du code javascript par les variable $this->xxxi correspondante de l'objet
     * Si la variable n'existe pas, la chaîne %%XXX%% n'est pas modifiée.
     * @param $sJS
     * @return string
     */

    private function replaceJsParams($sJS) {
        $sJS = preg_replace_callback ("/%%([^%]+)%%/", function($matches) {
            $fieldNameToReplace = strtolower($matches[1]);
            if (isset ($this->{$fieldNameToReplace})) {
                $fieldVal = $this->{$fieldNameToReplace};
                if (is_string($fieldVal)
                    || is_bool($fieldVal)
                    || is_int($fieldVal)
                ) {
                    return $this->{strtolower($matches[1])};
                }
            }
            return "%%" . $matches[1] . "%%";
        }, $sJS);
        return $sJS;
    }
    /**
     * @param $prefix
     * @param $s
     * @return bool|false|string|string[]|null
     *
     * find good javascript template file and substitute parameters
     *
     */
    private function generate ($prefix, $s)
    {
        $prefixPath = $this->getElement()->pathDir . "/" .  $this->getElement()->relPublicDirPath;

        $sJSFile = realpath( $prefixPath . '/js/form/decorator/' . $prefix . "$s.js" );

        if (!file_exists($sJSFile)) {
            return false;
        }
        
        $sJS = file_get_contents($sJSFile);

        return  $this->replaceJsParams($sJS);
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool|Zend_Form_Decorator_Abstract
     * @throws Exception
     */
    public function __call ($name , $arguments)
    {
    	$element = $this->getElement();

    	if (!$element instanceof Ccsd_Form_Interface_Javascript) {
        	throw new Exception("This element is not a valid Ccsd_Form_Interface_Javascript object");
        }

        if ($element instanceof Ccsd_Form_Element_MultiText) {
            $this->length = $element->getLength();
        }

        if ('buildJS' == $name) {
            foreach ($arguments[1] as $i => $t) {
                foreach ($t as $action) {
                    $sJS = $this->generate($arguments[0], $action);
         
                    if ($sJS) {
                        $a = $element->{"add$i"} ($sJS);
                        if (!in_array ($i, array('documentReady', 'var'))) {
                        	$this->$action = $a;
                        }
                    }
                }
                foreach ($t as $action) {
                    $sJS = $element->getJavascript($i, $this->$action);
                    
                    if ($sJS && !is_array($sJS)) {
                        $sJS = $this->replaceJsParams($sJS);
                        $element->setJavascript($sJS, $i, $this->$action);
                    }
                }
            }
            return $element;
        }
        
        return false;
    }

    /**
     * Pour les getters externes: on rends le nom du template %%XXX%% si le paramètre n'est pas encore setter!
     * Bon, tricky et se serait bien de dire ou on utilise le truc!
     * @param $name
     * @return string
     *
     * Note: devrait retourner qq chose ou faire un throw AccessViolation / AccessDeny... pour dire qu'on
     * a pas a accéder a une private property...
     */
    public function __get ($name)   
    {
        if (substr ($name, 0,1) == '_' && !isset ($this->$name)) {
            return "%%" . strtoupper(substr($name, 1)) . "%%";
        }

    }

 }