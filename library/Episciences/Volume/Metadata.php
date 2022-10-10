<?php

class Episciences_Volume_Metadata
{
    const TRANSLATION_PATH = REVIEW_LANG_PATH;
    const TRANSLATION_FILE = 'volumes.php';
    protected $_db = null;
    private $_id;
    private $_vid;
    private $_file;
    private $_tmpfile;
    private $_deletelist;
    private $_position;

    private $_title;
    private $_content;

    public function __construct(array $options = null)
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        $priority = ['vid', 'file', 'tmpfile'];

        $options = array_change_key_case($options, CASE_LOWER);
        $execution_list = [];

        // Gérer la priorité des instructions d'initialisation
        foreach ($priority as $key) {
            if (array_key_exists($key, $options)) {
                $execution_list[$key] = $options[$key];
                unset($options[$key]);
            }
        }

        // Rajouter à la liste les options qui ne sont pas prioritaires
        if (!empty($options)) {
            $execution_list += $options;
        }

        // Executer les setters
        foreach ($execution_list as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * load metadata translations
     * @param null $langs
     */
    public function loadTranslations($langs = null)
    {
        if (!$langs) {
            $langs = Episciences_Tools::getLanguages();
        }

        Episciences_Tools::loadTranslations(self::TRANSLATION_PATH, self::TRANSLATION_FILE);
        $this->loadTitles($langs);
        $this->loadContents($langs);
    }

    // Charge les traductions du titre de la metadata
    public function loadTitles($langs)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $title = [];
        foreach ($langs as $code => $lang) {
            if ($translator->isTranslated($this->getNameKey(), false, $code)) {
                $title[$code] = $translator->translate($this->getNameKey(), $code);
            }
        }
        $this->setTitle($title);
    }

    public function getNameKey()
    {
        return 'volume_' . $this->getVid() . '_md_' . $this->getId() . '_name';
    }

    public function getVid()
    {
        return $this->_vid;
    }

    public function setVid($vid)
    {
        $this->_vid = (int)$vid;
        return $this;
    }

    // Renvoie le nom de la metadata dans la langue voulue

    public function getId()
    {
        return $this->_id;
    }

    // Renvoie toutes les traductions du nom de la metadata

    public function setId($id)
    {
        $this->_id = $id;
        return $this;
    }

    // Renvoie le contenu de la metadata dans la langue voulue

    public function loadContents($langs)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $content = [];
        foreach ($langs as $code => $lang) {
            if ($translator->isTranslated($this->getContentKey(), false, $code)) {
                $content[$code] = $translator->translate($this->getContentKey(), $code);
            }
        }
        $this->setContent($content);
    }

    // Renvoie toutes les traductions du contenu de la metadata

    public function getContentKey()
    {
        return 'volume_' . $this->getVid() . '_md_' . $this->getId() . '_content';
    }

    // Renvoie true si la métadonnée a du contenu

    public function getTitle($lang = null)
    {
        if (!$lang) {
            $lang = Zend_Registry::get('lang');
        }
        if (is_array($this->_title) && array_key_exists($lang, $this->_title)) {
            return $this->_title[$lang];
        }

        return null;
    }

    // Renvoie true si la métadonnée a un fichier

    public function setTitle($title)
    {
        $this->_title = $title;
        return $this;
    }

    public function getContent($lang = null)
    {
        if (!$lang) {
            $lang = Zend_Registry::get('lang');
        }
        if (is_array($this->_content) && array_key_exists($lang, $this->_content)) {
            return $this->_content[$lang];
        }

        return null;
    }

    public function setContent($content)
    {
        $this->_content = $content;
        return $this;
    }

    public function isPDF()
    {
        $type = $this->getFileType();
        return (is_array($type) && $type[1] == 'pdf') ? 1 : 0;
    }

    public function getFileType()
    {
        if (!$this->hasFile()) {
            return null;
        }

        $mime = Episciences_Tools::getMimeType($this->getFilePath());
        list($fileinfo, $charset) = explode(' ', $mime);
        return explode('/', $fileinfo);
    }

    public function hasFile()
    {
        return ($this->getFile()) ? 1 : 0;
    }

    public function getFile()
    {
        return $this->_file;
    }

    public function setFile($file)
    {
        $this->_file = $file;
        return $this;
    }

    public function getFilePath()
    {
        return (!$this->hasFile()) ? null : REVIEW_PUBLIC_PATH . 'volumes/' . $this->getVid() . '/' . $this->getFile();
    }

    public function isPicture()
    {
        $type = $this->getFileType();
        return (is_array($type) && $type[0] == 'image') ? 1 : 0;
    }

    public function getFileUrl()
    {
        return (!$this->hasFile()) ? null : REVIEW_URL . 'volumes/' . $this->getVid() . '/' . $this->getFile();
    }

    /**
     * Save Volume metadata
     * @return bool
     */
    public function save()
    {
        if (!$this->getVid()) {
            return false;
        }

        // Enregistrement du fichier
        if ($this->getTmpfile()) {
            $file = $this->getTmpfile();

            $path = REVIEW_PUBLIC_PATH . 'volumes/' . $this->getVid() . '/';
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            // Supprimer l'ancien fichier
            if ($this->hasFile() && file_exists($path . $this->getFile())) {
                unlink($path . $this->getFile());
            }

            // Déplacer le fichier dans son dossier final
            $filename = Ccsd_Tools::getNewFileName($file['name'], $path);
            if (file_exists($file['tmp_name'])) {
                rename($file['tmp_name'], $path . $filename);
            }

            // On enregistre le nom du fichier
            $this->setFile($filename);
        }

        // Suppression des anciens fichiers
        if ($this->getDeletelist()) {
            foreach ($this->getDeletelist() as $item) {
                if ($item['type'] == 'tmp_file') {
                    if (file_exists($item['path'])) {
                        unlink($item['path']);
                    }
                } else {
                    $path = REVIEW_FILES_PATH . 'volumes/' . $this->getVid() . '/';
                    if (file_exists($path . $item['name'])) {
                        unlink($path . $item['name']);
                    }
                }
            }
        }

        // Enregistre la métadonnée
        $values = ['CONTENT' => $this->hasContent(), 'FILE' => ($this->hasFile()) ? $this->getFile() : null, 'POSITION' => $this->getPosition()];
        $values['VID'] = $this->getVid();


        if ($this->getId()) {
            try {
                $this->_db->update(T_VOLUME_METADATAS, $values, ['ID = ?' => $this->getId()]);
            } catch (Zend_Db_Adapter_Exception $exception) {
                error_log($exception->getMessage());
                return false;
            }
        } else {
            try {
                $resFromDb = $this->_db->insert(T_VOLUME_METADATAS, $values);
            } catch (Zend_Db_Adapter_Exception $exception) {
                error_log($exception->getMessage());
                return false;
            }
            if ($resFromDb < 1) {
                return false;
            }

            $this->setId($this->_db->lastInsertId());
        }


        // Préparation des données de traduction
        $path = self::TRANSLATION_PATH;
        $file = self::TRANSLATION_FILE;


        $translations = Episciences_Tools::getOtherTranslations($path, $file, '#volume_' . $this->getVid() . '_md_' . $this->getId() . '_#');

        // Nom de la métadonnée
        $key = $this->getNameKey();
        foreach ($this->getTitles() as $lang => $translated) {
            $translations[$lang][$key] = $translated;
        }

        // Contenu de la métadonnée
        $key = $this->getContentKey();
        foreach ($this->getContents() as $lang => $translated) {
            $translations[$lang][$key] = $translated;
        }

        // Enregistrement des traductions
        $resWriting = Episciences_Tools::writeTranslations($translations, $path, $file);

        if (!$resWriting) {
            return false;
        }

        return true;
    }

    public function getTmpfile()
    {
        return $this->_tmpfile;
    }

    public function setTmpfile($file)
    {
        $this->_tmpfile = $file;
        return $this;
    }

    public function getDeletelist()
    {
        return $this->_deletelist;
    }

    public function setDeletelist($file)
    {
        $this->_deletelist = $file;
        return $this;
    }

    public function hasContent()
    {
        $contents = $this->getContents();
        foreach ($contents as $value) {
            if ($value) {
                return 1;
            }
        }
        return 0;
    }

    public function getContents()
    {
        return $this->_content;
    }

    public function getPosition()
    {
        return $this->_position;
    }

    public function setPosition($position)
    {
        $this->_position = $position;
        return $this;
    }

    public function getTitles()
    {
        return $this->_title;
    }

}
