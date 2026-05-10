<?php

class Ccsd_View_Helper_Xslt extends Zend_View_Helper_Abstract
{

    public function __construct(mixed ...$args)
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        if (get_parent_class($this) !== false && method_exists(get_parent_class($this), '__construct')) {
            parent::__construct(...$args);
        }
    }

    public function xslt($src, $template, array $params = array())
    {
        try
        {
      
            $xml = new DOMDocument();
            
            if (is_file ($src)) {
                $xml->load($src);
            } else {
                $xml->loadXML($src);
            }

            $xsl = new DOMDocument();
            
            $xsl->load($template);

            $proc = new XSLTProcessor();
            $proc->registerPHPFunctions();
            
            foreach ($params as $key=>$value)
            {
                $proc->setParameter('', $key, $value);
            }

            $proc->importStyleSheet($xsl);
            $return = $proc->transformToXML($xml);
            return $return;
        }
        catch (Exception $e)
        {
            $return = 'Erreur !';
        }
    }
}
