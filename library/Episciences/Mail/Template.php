<?php

class Episciences_Mail_Template
{
    protected $_id;
    protected $_parentId;
    /* @var integer journal id */
    protected $_rvid;
    protected $_rvcode;
    protected $_key;
    protected $_type;

    protected $_translations;
    protected $_body;
    protected $_name;
    protected $_subject;

    protected $_locale;
    protected $_defaultLanguage = 'en';
    protected $_tags;
    protected bool $_isAutomatic = false;

    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->_tags;
    }

    /**
     * @param array $tags
     * @return Episciences_Mail_Template
     */
    public function setTags(array $tags): \Episciences_Mail_Template
    {

        if($this->_isAutomatic){
            foreach (Episciences_Mail_Tags::SENDER_TAGS as $tag){

                $key = array_search($tag, $tags, true);

                if ($key !== false) {
                    unset($tags[$key]);
                }
            }
        }

        $this->_tags = $tags;
        sort($this->_tags, SORT_STRING);
        return $this;
    }

    /**
     * fetch the template translations folder path (custom or default)
     * @param string|null $rvCode
     * @return string
     */
    public function getTranslationsFolder(string $rvCode = null)
    {
        return $this->isCustom() ?
            $this->getReviewTranslationsFolder($rvCode) :
            $this->getDefaultTranslationsFolder();
    }

    public function getDefaultTranslationsFolder()
    {
        return APPLICATION_PATH . '/languages/';
    }

    /**
     * @param string|null $rvCode
     * @return string
     */
    public function getReviewTranslationsFolder(string $rvCode = null)
    {

        if (!$rvCode && !Ccsd_Tools::isFromCli()) {
            $path = REVIEW_PATH;
        } else {
            $path = realpath(APPLICATION_PATH . '/../data') . '/' . $rvCode . '/';
        }

        return $path . 'languages/';
    }

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
        if (!$this->getLocale()) {
            $this->setLocale($this->_defaultLanguage);
        }
    }

    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    public function setLocale($locale): \Episciences_Mail_Template
    {
        $availableLanguages = Episciences_Tools::getLanguages();

        if (array_key_exists($locale, $availableLanguages)) {
            // La langue choisie est disponible
            $this->_locale = $locale;
        } // Si elle n'est pas dispo, on regarde si la langue par défaut est dispo
        elseif (array_key_exists($this->_defaultLanguage, $availableLanguages)) {
            $this->_locale = $this->_defaultLanguage;
        } // Sinon, on prend la première langue dispo dans l'appli
        else {
            reset($availableLanguages);
            $this->_locale = key($availableLanguages);
        }
        return $this;
    }

    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * @return bool
     */
    public function isAutomatic(): bool
    {
        return $this->_isAutomatic;
    }

    /**
     * @param bool $isAutomatic
     */
    public function setIsAutomatic(bool $isAutomatic): void
    {
        $this->_isAutomatic = $isAutomatic;
    }

    /**
     * fetch a template from a given id, and populate it from database
     * @param int $id
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function find(int $id): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()->from(T_MAIL_TEMPLATES)->where('ID = ? ', $id);
        $template = $select->query()->fetch();

        if ($template) {
            $this->populate($template);
            return true;
        }

        return false;
    }


    /**
     * fetch a template from a given key, and populate it from database
     * @param string $key
     * @return bool
     * @throws Zend_Exception
     */
    public function findByKey(string $key): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$this->getRvcode()) {
            throw new Zend_Exception("Template could not be found because rvcode is missing");
        }

        // fetch custom template from database (if found)
        $sql = $db->select()->from(T_MAIL_TEMPLATES)->where('`KEY` = ? ', 'custom_' . $key)->where('RVCODE = ?', $this->getRvcode());
        $template = $db->fetchRow($sql);

        if ($template) {
            $this->populate($template);
            return true;
        }

        // fetch default template from database (if found)
        $sql = $db->select()->from(T_MAIL_TEMPLATES)->where('`KEY` = ? ', $key);
        $template = $db->fetchRow($sql);
        if ($template) {
            $this->populate($template);
            return true;
        }

        return false;
    }

    /**
     * populate Template object from a given array of data
     * @param array $data
     * @return bool
     */
    private function populate(array $data): bool
    {
        if ($data) {
            $this->setId($data['ID']);
            $this->setParentId($data['PARENTID']);
            $this->setRvid($data['RVID']);
            $this->setRvcode($data['RVCODE']);
            $this->setKey($data['KEY']);
            $this->setType($data['TYPE']);
            $this->setIsAutomatic(in_array($this->getKey(), Episciences_Mail_TemplatesManager::AUTOMATIC_TEMPLATES, true));
            $this->setTags(Episciences_Mail_TemplatesManager::getAvailableTagsByKey($data['KEY']));
            // $this->loadTranslations();
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        $fields = [
            'id',
            'parentId',
            'rvcode',
            'key',
            'type'
        ];
        foreach ($fields as $key) {
            $method = 'get' . ucfirst($key);
            if (method_exists($this, $method)) {
                $result[$key] = $this->$method();
            }
        }
        $result['subject'] = $this->getSubjectTranslations();
        $result['name'] = $this->getNameTranslations();
        $result['body'] = $this->getBodyTranslations();

        return $result;
    }

    /**
     * save custom template to database
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): bool
    {
        $result = true;
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$this->getParentid()) {
            // Nouveau template personnalisé
            $this->setParentid($this->getId());
            $key = 'custom_' . $this->getKey();
            $values = [
                'PARENTID' => $this->getParentid(),
                'RVID' => $this->getRvid(),
                'RVCODE' => $this->getRvcode(),
                'KEY' => $key,
                'TYPE' => $this->getType()
            ];

            if (!$db->insert(T_MAIL_TEMPLATES, $values)) {
                return false;
            }
        } else {
            // Modification d'un template personnalisé
            $key = $this->getKey();
        }

        // Ecriture des traductions ********************************

        // Récupération du fichier de traduction
        $translations = Episciences_Tools::getOtherTranslations($this->getTranslationsFolder(), Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME, '#^' . $key . '#');

        // Traductions du nom du template
        $name = $this->getNameTranslations();

        if (empty($name)) {
            $result = false;
            trigger_error('TEMPLATE::SAVE_GET_NAME_TRANSLATIONS_IS_EMPTY');
        }

        foreach ($name as $lang => $translation) {
            $translations[$lang][$key . Episciences_Mail_TemplatesManager::SUFFIX_TPL_NAME] = $translation;
        }

        // Traductions du sujet du template
        $subject = $this->getSubjectTranslations();

        if (empty($subject)) {
            $result = false;
            trigger_error('TEMPLATE::SAVE_GET_SUBJECT_TRANSLATIONS_IS_EMPTY');
        }

        foreach ($subject as $lang => $translation) {
            $translations[$lang][$key . Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT ] = $translation;
        }

        // Mise à jour du fichier de traduction
        if (Episciences_Tools::writeTranslations($translations, $this->getTranslationsFolder(), Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME) < 1) {
            trigger_error('UPDATING_THE_TRANSLATION_FILE_TOTAL_BYTES_WRITTEN_IS_EMPTY');
        }

        // Création du template dans ses différentes langues
        $body = $this->getBodyTranslations();

        if (empty($body)) {
            $result = false;
            trigger_error('TEMPLATE::SAVE_GET_BODY_TRANSLATIONS_IS_EMPTY');
        }

        foreach ($body as $lang => $translation) {
            $path = $this->getTranslationsFolder() . $lang . '/emails/';

            if (!is_dir($path) && !mkdir($path)) {
                trigger_error('Directory "%s" was not created', $path);

            } else {

                $filePutContent = file_put_contents($path . $key . '.phtml', $translation);

                if (!$filePutContent) {
                    trigger_error('TEMPLATE::SAVE_WRITE_DATA_TO_FILE_IS_EMPTY');
                }

                $result = $filePutContent;
            }

        }

        return $result;

    }

    // Suppression d'un template
    public function delete(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $id = $this->getId();
        $key = $this->getKey();

        // Supprimer en base
        if ($db->delete(T_MAIL_TEMPLATES, 'ID = ' . $id) < 1){
            return false;
        }

        // Supprimer les fichiers de traduction
        $translations = Episciences_Tools::getOtherTranslations($this->getTranslationsFolder(), Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME, '#^' . $key . '#');
        Episciences_Tools::writeTranslations($translations, $this->getTranslationsFolder(), Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME);

        // Supprimer le template
        $langFolders = scandir($this->getTranslationsFolder());
        foreach ($langFolders as $folder) {
            $filepath = $this->getTranslationsFolder() . $folder . '/emails/' . $key . '.phtml';
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        return true;
    }

    /**
     * Charge les traductions du template (body, name et subject)
     * @param null $langs
     * @param string|null $rvCode
     * @throws Zend_Exception
     */
    public function loadTranslations($langs = null, string $rvCode = null): void
    {
        if (!$langs) {
            $langs = Episciences_Tools::getLanguages();
        }

        Episciences_Tools::loadTranslations($this->getTranslationsFolder($rvCode), Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME);

        $this->loadName($langs);
        $this->loadSubject($langs);
        $this->loadBody($rvCode);
    }

    public function getTranslations()
    {
        return $this->_translations;
    }

    /**
     * Charge le corps du template dans les différentes langues trouvées
     * @param string|null $rvCode
     * @return array
     */
    public function loadBody(string $rvCode = null): array
    {
        $path = $this->getTranslationsFolder($rvCode);
        $exclusions = ['.', '..', '.svn'];
        $result = [];

        if (is_dir($path)) {

            $files = scandir($path);
            foreach ($files as $file) {
                $filepath = $path . $file . '/emails/' . $this->getKey() . '.phtml';
                if (!in_array($file, $exclusions, true) && file_exists($filepath)) {
                    $result[$file] = file_get_contents($filepath);
                }
            }
        }

        if (!empty($result)) {
            $this->setBody($result);
        }

        return $result;
    }

    /**
     * Charge le nom template dans les différentes langues trouvées
     * @param $langs
     * @return array
     * @throws Zend_Exception
     */
    public function loadName($langs): array
    {
        $name = [];
        $translator = Zend_Registry::get('Zend_Translate');
        foreach ($langs as $code => $lang) {
            if ($translator->isTranslated($this->getKey() . Episciences_Mail_TemplatesManager::SUFFIX_TPL_NAME, false, $code)) {
                $name[$code] = $translator->translate($this->getKey() . Episciences_Mail_TemplatesManager::SUFFIX_TPL_NAME, $code);
            }
        }
        if (!empty($name)) {
            $this->setName($name);
        }
        return $name;
    }

    /**
     * Charge le sujet du template dans les différentes langues trouvées
     * @param $langs
     * @return array
     * @throws Zend_Exception
     */
    public function loadSubject($langs): array
    {
        $subject = [];
        $translator = Zend_Registry::get('Zend_Translate');
        foreach ($langs as $code => $lang) {
            // Subject
            if ($translator->isTranslated($this->getKey() . Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT , false, $code)) {
                $subject[$code] = $translator->translate($this->getKey() . Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT , $code);
            }
        }
        if (!empty($subject)) {
            $this->setSubject($subject);
        }

        return $subject;
    }

    /**
     * return true if template has a custom version, false otherwise
     * @return bool
     */
    public function isCustom(): bool
    {
        return ((bool)$this->getParentid());
    }

    // Getters ***************************************************

    /**
     * @return int
     */
    public function getRvid(): int
    {
        // TODO: getter side effect — setRvid() mutates object state; callers may
        // depend on this lazy-init behaviour, so refactoring requires wider analysis.
        if (!$this->_rvid && defined('RVID')) {
            $this->setRvid(RVID);
        }

        return $this->_rvid;
    }


    /**
     * fetch template path
     * @param null $locale
     * @param string|null $rvVCode
     * @return string
     */
    public function getPath($locale = null, string $rvVCode = null): string
    {
        if (!$locale) {
            $locale = $this->getLocale();
        }
        return $this->getTranslationsFolder($rvVCode) . $locale . '/emails';
    }

    /**
     * Renvoie le body dans la langue voulue, ou la langue par défaut
     * @param $lang
     * @return mixed|null
     */
    public function getBody($lang = null)
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }
        if (is_array($this->_body) && array_key_exists($lang, $this->_body)) {
            return $this->_body[$lang];
        }

        return null;
    }

    /**
     * Renvoie toutes les traductions du body
     * @return mixed
     */
    public function getBodyTranslations()
    {
        return $this->_body;
    }

    /**
     *  Renvoie le nom du template dans la langue voulue, ou la langue par défaut
     * @param $lang
     * @return mixed|null
     */
    public function getName($lang = null)
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }
        if (is_array($this->_name) && array_key_exists($lang, $this->_name)) {
            return $this->_name[$lang];
        }

        return null;
    }

    /**
     * Renvoie toutes les traductions du nom du template
     * @return mixed
     */
    public function getNameTranslations()
    {
        return $this->_name;
    }

    /**
     *  Renvoie le sujet dans la langue voulue ou la langue par défaut
     * @param $lang
     * @return mixed|null
     * @throws Zend_Exception
     */
    public function getSubject($lang = null)
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }

        if (is_array($this->_subject) && array_key_exists($lang, $this->_subject) && array_key_exists($lang, Episciences_Tools::getLanguages())) {
            return $this->_subject[$lang];
        }

        return null;
    }

    /**
     *  Renvoie toutes les traductions du sujet du mail
     * @return mixed
     */
    public function getSubjectTranslations()
    {
        return $this->_subject;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getParentid()
    {
        return $this->_parentId;
    }

    public function getRvcode()
    {
        // TODO: getter side effect — setRvcode() mutates object state; callers may
        // depend on this lazy-init behaviour, so refactoring requires wider analysis.
        if (!$this->_rvcode && defined('RVCODE')) {
            $this->setRvcode(RVCODE);
        }
        return $this->_rvcode;
    }

    public function getKey()
    {
        return $this->_key;
    }

    public function getType()
    {
        return $this->_type;
    }

    // SETTERS ***************************************************

    public function setBody($body): \Episciences_Mail_Template
    {
        $this->_body = $body;
        return $this;
    }

    public function setName($name): \Episciences_Mail_Template
    {
        $this->_name = $name;
        return $this;
    }

    public function setSubject($subject): \Episciences_Mail_Template
    {
        $this->_subject = $subject;
        return $this;
    }

    public function setId($id): \Episciences_Mail_Template
    {
        $this->_id = $id;
        return $this;
    }

    public function setParentid($parentId): \Episciences_Mail_Template
    {
        $this->_parentId = $parentId;
        return $this;
    }

    public function setRvcode($rvcode): \Episciences_Mail_Template
    {
        $this->_rvcode = $rvcode;
        return $this;
    }

    public function setKey($key): \Episciences_Mail_Template
    {
        $this->_key = $key;
        return $this;
    }

    public function setType($type): \Episciences_Mail_Template
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * @param int $rvid
     */
    public function setRvid($rvid): \Episciences_Mail_Template
    {
        $this->_rvid = (int)$rvid;
        return $this;
    }

    /**
     * get available tags list description
     * @return string
     */
    public function getAvailableTagsListDescription(): string
    {
        return implode('; ', $this->getTags());
    }

}
