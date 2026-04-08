<?php

/**
 * Class Ccsd_Cache : Gestion de l'enregistrement des fichiers de cache
 *
 * @deprecated Use Symfony\Component\Cache\Adapter\FilesystemAdapter (PSR-6) instead.
 *             All internal usages have been migrated. This class will be removed in a future release.
 */
class Ccsd_Cache
{

    /**
     *
     * @var string Répertoire de stockage des fichiers de cache
     */
    protected static $_cachePath;

    /**
     *
     * Création ou modification d'un fichier de cache @param string $name nom du
     * fichier @param string $content contenu du fichier @param string $cachePath
     * chemin vers le repertoire de stockage @return boolean réussite
     * de l'enregistrement
     */
    public static function save ($name = null, $content = '', $cachePath = '')
    {
        if ($cachePath != '') {
            self::setCachePath($cachePath);
        }
        if (null !== $name && '' !== $content) {
            $path = self::getCachePath();
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
            $suffixe = uniqid("-");
            $tmpFilename = $path . '/' . $name . $suffixe;
            $filename    = $path . '/' . $name;
            @unlink($filename);
            // Don't want to write into an existing file, other process can read it during writing.
            // So we write in a temporary file and when finished, rename the file..
            $res = (false !== file_put_contents($tmpFilename, $content, LOCK_EX));
            if ($res) {
                $res &= rename($tmpFilename, $filename);
            }
            return $res;
        }
        return false;
    }

    /**
     * @param string $cachePath
     * @return void
     */
    public static function setCachePath ($cachePath)
    {
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        static::$_cachePath = $cachePath;
    }

    /**
     * @return string
     */
    public static function getCachePath ()
    {
        return static::$_cachePath;
    }
    /**
     * Retourne le contenu du fichier de cache @param string $name nom du
     * fichier @param string $cachePath chemin vers le repertoire de stockage
     * @return string contenu du fichier
     */
    public static function get ($name = null, $cachePath = '')
    {
        if ($cachePath != '') {
            self::setCachePath($cachePath);
        }
        return @file_get_contents(self::getCachePath() . '/' . $name);
    }

    /**
     * Teste l'existence d'un fichier dans le cache @param string $name nom du
     * fichier @param int $duration duree de vie du cache (en secondes) @param string
     * $cachePath chemin vers le repertoire de stockage @return boolean
     */
    public static function exist ($name, $duration = 0, $cachePath = '')
    {
        if ($cachePath !== '') {
            self::setCachePath($cachePath);
        }

        $filename = self::getCachePath() . '/' . $name;
        // TODO: ceci n'est pas unitaire, dans les logs, filesize met un message de non existence
        // => le fichier a ete efface entre le is_readable et le filesize!

        if (! is_readable($filename) || filesize($filename) === 0) {
            return false;
        }
        return ($duration === 0) || (time() - filemtime($filename) < $duration);
    }

    /**
     * supprime le fichier de cache
     * @param string $name nom du fichier
     * @paramstring $cachePath chemin vers le repertoire de stockage
     * @return boolean
     */
    public static function delete ($name = null, $cachePath = '', $strict=false)
    {
        if ($cachePath !== '') {
            self::setCachePath($cachePath);
        }
        if ($name === null) {
            // don't do anything else (beware: can be use to set cachepath ???
            return false;
        }
        if (file_exists(self::getCachePath() . '/' . $name)) {
            return @unlink(self::getCachePath() . '/' . $name);
        }
        // File don't exists
        if ($strict === true) {
            // Don't try to globalize!!!
            return false;
        }
        // suppression de tous les fichiers commençant pas $name
        $res = true;
        foreach (glob(self::getCachePath() . '/' . $name . '*') as $file) {
            $res &= @unlink($file);
        }
        return $res;
    }

    /**
     * @param string $fileName
     * @param bool $useLanguage
     * @param string $suffix
     * @return string
     */
    static function makeCacheFileName ($fileName = '', $useLanguage = false, $suffix = null)
    {
        if ($fileName === '') {
            $request = \Zend_Controller_Front::getInstance()->getRequest();
            $fileName = $request->getControllerName();
            $fileName .= ucfirst($request->getActionName());

            if ($suffix != null) {
                $fileName.= $suffix;
            }

            if ($useLanguage) {
                try {
                    $fileName .= '_' . Zend_Registry::get('lang');
                } catch (Zend_Exception $e) {
                     $fileName .= '_und';
                }
            } else {
                $fileName .= '_und';
            }
            return Ccsd_Tools::cleanFileName($fileName) . '.txt';
        }
        return Ccsd_Tools::cleanFileName($fileName);
    }
}
