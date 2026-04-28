<?php

class Ccsd_View_Helper_Xslt extends Zend_View_Helper_Abstract
{

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
