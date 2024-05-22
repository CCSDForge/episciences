<?php

/**
 * Gestiond des actualités
 * @author yannick
 *
 */
class Ccsd_News
{

    /**
     * Fichier de configuration du formulaire
     * @var string
     */
    const FORM_INI = 'Ccsd/News/Form/news.ini';
    public const PREFIX_FILENAME = 'news';
    /**
     * Connecteur base de données
     * @var Zend_Db_Table_Abstract
     */
    protected $_db = null;
    /**
     * Table
     * @var string
     */
    protected $_table = 'NEWS';
    /**
     * Clé primaire
     * @var string
     */
    protected $_primary = 'NEWSID';
    /**
     * Champ identifiant le site
     * @var string
     */
    protected $_sidField = 'SID';
    /**
     * Identifiant du site
     * @var int
     */
    protected $_sid = 0;
    /**
     * Formulaire d'ajout de news
     * @var unknown_type
     */
    protected $_form = null;
    /**
     * Langues disponibles de l'interface
     * @var array
     */
    protected $_languages = array();
    /**
     * Répertoire de sauvegarde des fichiers de langues
     * @var string
     */
    protected $_dirLangFiles = '';

    /**
     * Constructeur de l'objet
     * @param int $sid
     * @param string $dirLang
     * @param array $languages
     */
    public function __construct($sid, $dirLangFiles = '', $languages = array())
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->_sid = $sid;
        $this->_languages = $languages;
        $this->_dirLangFiles = $dirLangFiles;
    }

    public static function getLanguages()
    {
    }

    /**
     * Save a news
     * @param $news
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    public function save($news): void
    {
        $bind = array(
            $this->_sidField => $this->_sid,
        );

        $id = 0;
        $isInsert = true;

        foreach ($news as $key => $value) {
            switch ($key) {
                case 'newsid':
                    $id = $value;
                    break;
                case 'online':
                    $bind[mb_strtoupper($key)] = (int)$value;
                    break;
                case 'title':
                case 'content':
                    $news[$key] = is_array($value) ? array_map('strip_tags', $value) : strip_tags($value);
                    break;
                case 'link':
                    $filteredUrl = filter_var($value, FILTER_SANITIZE_URL);
                    if (filter_var($filteredUrl, FILTER_VALIDATE_URL)) {
                        $bind[mb_strtoupper($key)] = strip_tags($filteredUrl);
                    } else {
                        $bind[mb_strtoupper($key)] = '';
                    }
                    break;
                case 'date':
                    // Handle date if needed
                    break;
                default:
                    $bind[mb_strtoupper($key)] = $value;
                    break;
            }
        }
        $visibility = ($bind['ONLINE']) ? "public" : "private";
        if ($id === 0) {
            //Insertion
            $this->_db->insert($this->_table, $bind);
            $id = (int)$this->_db->lastInsertId($this->_table);
            $journalNewsInfo = $this->insertNewJournalNews($id, $bind, $news, $visibility);

        } else {

            $isInsert = false;
            //Editing
            if (isset($news['date']) && $news['date']) {
                //Maj de la date
                $bind['DATE_POST'] = new Zend_Db_Expr('NOW()');
            }
            $journalNewsExisting = Episciences_JournalNews::findByLegacyId($id);
            if ($journalNewsExisting !== null){
                $journalNewsExisting->setUid($bind['UID']);
                $journalNewsExisting->setTitle(json_encode($news['title'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                empty($news['content']) ? $journalNewsExisting->setContent(null) :
                    $journalNewsExisting->setContent(json_encode($news['content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                empty($news['content']) ? $journalNewsExisting->setLink(null) :
                    $journalNewsExisting->setLink(json_encode(['und' => $bind['LINK']],JSON_THROW_ON_ERROR));
                $journalNewsExisting->setVisibility(json_encode([$visibility], JSON_THROW_ON_ERROR));
                Episciences_JournalNews::update($journalNewsExisting);

            } else {
                $this->insertNewJournalNews($id, $bind, $news, $visibility);
            }
            $this->_db->update($this->_table, $bind, $this->_primary . ' = ' . $id);

        }
        //Editing translation files
        $lang = [
            'title_' . $id => $news['title']
        ];

        if (!empty($news['content'])) {
            $lang['content_' . $id] = $news['content'];
        }

        if (!$isInsert) {
            $this->updateTranslation($id, $lang);
        } else {
            $writer = new Ccsd_Lang_Writer($lang);
            $writer->add($this->_dirLangFiles, self::PREFIX_FILENAME);

        }
    }

    private function updateTranslation(int $newsId, array $data = []): bool
    {
        $translations = Episciences_Tools::getOtherTranslations($this->_dirLangFiles, self::PREFIX_FILENAME, '#_' . $newsId . '#');


        if (!empty($data)) {

            foreach ($data as $key => $currentTranslations) {

                if ($key !== 'title_' . $newsId && $key !== 'content_' . $newsId) {
                    continue;
                }

                foreach ($currentTranslations as $lang => $value) {
                    $translations[$lang][$key] = $value;

                }
            }
        }

        if (!Episciences_Tools::writeTranslations($translations, $this->_dirLangFiles, self::PREFIX_FILENAME . '.php')) {

            error_log('Translation update failed for news id = ' . $newsId);

            return false;
        }

        return true;

    }

    /**
     * Suppression d'une actualité
     * @param unknown_type $newsid
     */
    public function delete($newsid)
    {
        if ($this->_db->delete($this->_table, $this->_primary . ' = ' . $newsid)) {

            $this->updateTranslation($newsid);

        }
    }

    /**
     * Retourne le formulaire
     * @param int $newsid
     * @return Ccsd_Form
     */
    public function getForm($newsid = 0)
    {
        if ($this->_form == null) {
            $this->_form = new Ccsd_Form();
            $this->_form->setAttrib('class', 'form-horizontal');
            $config = new Zend_Config_Ini(self::FORM_INI);
            $this->_form->setActions(true);
            if ($newsid != 0) {
                $this->_form->setConfig($config->edit);
                $this->_form->createSubmitButton('Modifier');
            } else {
                $this->_form->setConfig($config->new);
                $this->_form->createSubmitButton();
            }
            $this->_form->getElement('title')->setLanguages($this->_languages);
            $this->_form->getElement('content')->setLanguages($this->_languages);

        }

        if ($newsid != 0) {
            $this->_form->populate($this->getNews($newsid));
        }
        return $this->_form;
    }

    /**
     * Récupération d'une actualité à partir de son identifiant
     * @param int $newsid
     * @return array
     */
    public function getNews($newsid)
    {
        $data = array();
        $news = $this->getListNews(false, $newsid);
        if ($news) {
            $reader = new Ccsd_Lang_Reader(self::PREFIX_FILENAME, $this->_dirLangFiles, $this->_languages, true);
            foreach ($news as $key => $value) {
                $key = mb_strtolower($key);
                if ($key == 'title' || $key == 'content') {
                    $data[$key] = $reader->get($value);
                } else {
                    $data[$key] = $value;
                }

            }
        }
        return $data;
    }

    /**
     * Récupération de la liste des actualités d'un site
     * @param boolean $online retourne uniquement les actus en ligne
     * @param int $newsid retourne uniquement une actu
     * @param int $limit retourne un certain nombre de news
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function getListNews($online = true, $newsid = 0, $limit = 0)
    {
        $sql = $this->_db->select()
            ->from($this->_table, array('*', new Zend_Db_Expr('CONCAT_WS("", "title_", ' . $this->_primary . ') as TITLE'), new Zend_Db_Expr('CONCAT_WS("", "content_", ' . $this->_primary . ') as CONTENT')))
            ->where($this->_sidField . ' = ?', $this->_sid)
            ->order('DATE_POST DESC');
        if ($online) {
            $sql->where('ONLINE = 1');
        }
        if ($limit > 0) {
            $sql->limit($limit);
        }

        if ($newsid != 0) {
            $sql->where($this->_primary . ' = ?', $newsid);
            return $this->_db->fetchRow($sql);
        } else {
            return $this->_db->fetchAll($sql);
        }
    }

    /**
     * @param mixed $id
     * @param array $bind
     * @param $news
     * @param string $visibility
     * @return array
     * @throws JsonException
     */
    public function insertNewJournalNews(mixed $id, array $bind, $news, string $visibility): void
    {
        $journalNewsInfo = [
            'legacy_id' => $id,
            'code' => Episciences_ReviewsManager::find($bind['RVID'])->getCode(),
            'uid' => $bind['UID'],
            'date_creation' => date("Y-m-d H:i:s"),
            'date_updated' => date("Y-m-d H:i:s"),
            'title' => json_encode($news['title'], JSON_THROW_ON_ERROR),
            'content' => empty($news['content']) ? new Zend_Db_Expr('NULL') : json_encode($news['content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'link' => empty($news['link']) ? new Zend_Db_Expr('NULL') : json_encode(['und' => $bind['LINK']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'visibility' => json_encode([$visibility], JSON_THROW_ON_ERROR)
        ];
        Episciences_JournalNews::insert($journalNewsInfo);
    }
}