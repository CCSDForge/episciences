<?php

class Episciences_View_Helper_Translate extends Zend_View_Helper_Translate
{
    /**
     * @param null $messageid
     * @return mixed|string|Zend_View_Helper_Translate
     * @throws ReflectionException
     */
    public function translate($messageid = null)
    {
    	$args = func_get_args();
    	
    	$reflector = new ReflectionClass(get_class($this));
    	$parent = $reflector->getParentClass();
    	$method = $parent->getMethod('translate');
    	$result = $method->invokeArgs($this, $args);
    	
    	if (is_array($result)) {
    		return $result[0];
    	}

        return $result;
    }
}
