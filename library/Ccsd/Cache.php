<?php

/**
 * Class Ccsd_Cache : Gestion de l'enregistrement des fichiers de cache
 */
class Ccsd_Cache
{

    /**
     *
     * @var string répertoire de stockage des fichiers de cache
     */
    protected static $_cachePath;

    /*
     * Création ou modification d'un fichier de cache @param string $name nom du
     * fichier @param string $content contenu du fichier @param string
     * $cachePath chemin vers le repertoire de stockage @return boolean réussite
     * de l'enregistrement
     */
    static public function save ($name = null, $content = '', $cachePath = '')
    {
        if ($cachePath != '') {
            self::setCachePath($cachePath);
        }

        if (null !== $name && '' !== $content) {
            if (! is_dir(self::getCachePath())) {
                mkdir(self::getCachePath(), 0777, true);
            }
            return false !== file_put_contents(self::getCachePath() . '/' . $name, $content, LOCK_EX);
        }
        return false;
    }

    static public function setCachePath ($cachePath)
    {
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        static::$_cachePath = $cachePath;
    }

    static public function getCachePath ()
    {
        return static::$_cachePath;
    }
    /*
     * retourne le contenu du fichier de cache @param string $name nom du
     * fichier @param string $cachePath chemin vers le repertoire de stockage
     * @return string contenu du fichier
     */
    static public function get ($name = null, $cachePath = '')
    {
        if ($cachePath != '') {
            self::setCachePath($cachePath);
        }
        return @file_get_contents(self::getCachePath() . '/' . $name);
    }

    /*
     * teste l'existence d'un fichier dans le cache @param string $name nom du
     * fichier @param int $duration duree de vie du cache (en secondes) @param string
     * $cachePath chemin vers le repertoire de stockage @return boolean
     */
    static public function exist ($name, $duration = 0, $cachePath = '')
    {
        if ($cachePath != '') {
            self::setCachePath($cachePath);
        }

        $filename = self::getCachePath() . '/' . $name;

        if (! is_readable($filename) || filesize($filename) == 0) {
            return false;
        }
        return ($duration == 0) || (time() - filemtime($filename) < $duration);
    }

    /**
     * supprime le fichier de cache
     * @param string $name nom du fichier
     * @paramstring $cachePath chemin vers le repertoire de stockage
     * @return boolean
     */
    static public function delete ($name = null, $cachePath = '', $strict=false)
    {
        if ($cachePath != '') {
            self::setCachePath($cachePath);
        }
        if ($name == null) {
            // don't do anything else (beware: can be use to set cachepath ???
            return false;
        }
        if (file_exists(self::getCachePath() . '/' . $name)) {
            return @unlink(self::getCachePath() . '/' . $name);
        }
        // File don't exists
        if ($strict === true) {
            // Don't try to globilize!!!
            return false;
        }
        // supression de tous les fichiers commençant pas $name
        $res = true;
        foreach (glob(self::getCachePath() . '/' . $name . '*') as $file) {
            $res &= @unlink($file);
        }
        return $res;
    }

    static function makeCacheFileName ($fileName = '', $useLanguage = false, $suffix = null)
    {
        if ($fileName == '') {
            $fileName = Zend_Controller_Front::getInstance()->getRequest()->getControllerName();
            $fileName .= ucfirst(Zend_Controller_Front::getInstance()->getRequest()->getActionName());

            if ($suffix != null) {
                $fileName.= $suffix;
            }

            if ($useLanguage == true) {
                try {
                    $fileName .= '_' . Zend_Registry::get('lang');
                } catch (Zend_Exception $e) {
                     $fileName .= '_und';
                }
            } else {
                $fileName .= '_und';
            }
            return Ccsd_Tools::cleanFileName($fileName) . '.txt';
        } else {
            return Ccsd_Tools::cleanFileName($fileName);
        }
    }
}