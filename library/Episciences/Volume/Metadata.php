<?php

class Episciences_Volume_Metadata
{
    const TRANSLATION_FILE = 'volumes.php';
    protected $_db = null;
    private $_id;
    private $_vid;
    private $_file;
    private $_tmpfile;
    private $_deletelist;
    private $_position;

    private ?array $_title;
    private ?array $_content;
    private ?string $date_creation = null;
    private string $date_updated;

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
            $key = Episciences_Tools::convertToCamelCase($key, '_', true);
        //ucfirst is redundant
            $method = 'set' . $key;
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @param null $langs
     * @throws Zend_Exception
     * load metadata translations
     * @deprecated: titles and content are now loaded from 'T_VOLUME_METADATAS' table
     */
    public function loadTranslations($langs = null)
    {
        if (!$langs) {
            $langs = Episciences_Tools::getLanguages();
        }

        Episciences_Tools::loadTranslations(REVIEW_LANG_PATH, self::TRANSLATION_FILE);
        $this->loadTitles($langs);
        $this->loadContents($langs);
    }

    /**
     * Deprecated: titles are now loaded from 'T_VOLUME_METADATAS' table
     * Charge les traductions du titre de la metadata
     * @param $langs
     * @return void
     * @throws Zend_Exception
     */
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

    /**
     * Deprecated: contents are now loaded from 'T_VOLUME_METADATAS' table
     * Renvoie le contenu de la metadata dans la langue voulue
     * @param $langs
     * @return void
     * @throws Zend_Exception
     */

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

    /**
     * Returns the title of the metadata in the specified language
     * @param $lang
     * @return mixed|null
     */
    public function getTitle($lang = null)
    {

        $titles = $this->getTitles();

        if (!$lang) {
            try {
                $lang = Zend_Registry::get('lang');
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
            }
        }

        return $titles[$lang] ?? null;
    }


    public function setTitle(?array $title = null): self
    {
        $this->_title = $title;
        return $this;
    }

    /**
     * @param $lang
     * @return string|null
     * @throws Zend_Exception
     */
    public function getContent($lang = null): ?string
    {
        $contents = $this->getContents();

        if (!$lang) {
            $lang = Zend_Registry::get('lang');
        }

        return $contents[$lang] ?? null;
    }

    /**
     * @param array|null $content
     * @return $this
     */
    public function setContent(?array $content = null): self
    {
        $this->_content = $content;
        return $this;
    }

    public function isPDF(): bool
    {
        $type = $this->getFileType();
        return is_array($type) && isset($type[1]) && $type[1] === 'pdf';
    }

    /**
     * @return false|string[]|null
     */

    public function getFileType()
    {
        if (!$this->hasFile()) {
            return null;
        }

        $mime = Episciences_Tools::getMimeType($this->getFilePath());
        list($fileinfo) = explode(' ', $mime);

        if (mb_substr($fileinfo, -1, 1) === ';') {
            $fileinfo = mb_substr($fileinfo, 0, -1);
        }

        return explode('/', $fileinfo);
    }

    /**
     * Renvoie 1 si la métadonnée a un fichier
     * @return int
     */

    public function hasFile(): int
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

    public function isPicture(): bool
    {
        $type = $this->getFileType();
        return (is_array($type) && $type[0] === 'image');
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
        try {
            $values = [
                'titles' => json_encode($this->getTitles(), JSON_THROW_ON_ERROR),
                'CONTENT' => json_encode($this->getContents(), JSON_THROW_ON_ERROR), // descriptions
                'FILE' => ($this->hasFile()) ? $this->getFile() : null, 'POSITION' => $this->getPosition(),
                'date_creation' => $this->getDateCreation()
            ];
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
        }
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

    /**
     * Renvoie true si la métadonnée a une description
     * @return bool
     */

    public function hasContent(): bool
    {
        $contents = $this->getContents();

        foreach ($contents as $value) {
            if ($value) {
                return true;
            }
        }
        return false;
    }

    public function getContents(): ?array
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

    public function getTitles(): ?array
    {
        return $this->_title;
    }

    public function getDateCreation(): ?string
    {
        return $this->date_creation;
    }

    public function setDateCreation(string $date_creation = null): self
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    public function getDateUpdated(): string
    {
        return $this->date_updated;
    }

    public function setDateUpdated(string $date_updated = new Zend_Db_Expr('NOW()')): self
    {
        $this->date_updated = $date_updated;
        return $this;
    }

}
