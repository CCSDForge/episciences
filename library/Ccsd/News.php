<?php

/**
 * Gestiond des actualités 
 * @author yannick
 *
 */
class Ccsd_News
{
	
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
	 * Fichier de configuration du formulaire
	 * @var string
	 */
	const FORM_INI = 'Ccsd/News/Form/news.ini';
	
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
				->from($this->_table, array('*', new Zend_Db_Expr('CONCAT_WS("", "title_", ' .$this->_primary. ') as TITLE'), new Zend_Db_Expr('CONCAT_WS("", "content_", ' .$this->_primary. ') as CONTENT')))
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
	 * Récupération d'une actualité à partir de son identifiant
	 * @param int $newsid
	 * @return array
	 */
	public function getNews($newsid)
	{
		$data = array();
		$news = $this->getListNews(false, $newsid);
		if ($news) {
			$reader = new Ccsd_Lang_Reader('news', $this->_dirLangFiles, $this->_languages, true);
			foreach ($news as $key => $value) {
				$key = strtolower($key);
				if ($key == 'title' || $key == 'content') {
					$data[$key] = $reader->get($value);
				} else {
					$data[$key] = $value;
				}
				
			}
		}
		return $data;
	}
	
	public static function getLanguages(){}
	
	/**
	 * Enregistrement d"une actu
	 * @param array $news
	 */
	public function save($news)
	{
		$bind = array(
			$this->_sidField	=>	$this->_sid,		
		);
		
		$id = 0;
		foreach ($news as $key => $value) {
			if ($key == 'newsid') {
				$id = $value;
			} else if ($key != 'title' && $key != 'content' && $key != 'date') {
				$bind[strtoupper($key)] = $value;
			}
		}
		
		if ($id == 0) {
			//Insertion
			$this->_db->insert($this->_table, $bind);
			$id = $this->_db->lastInsertId($this->_table);
		} else {
			//Modification
			if (isset($news['date']) && $news['date']) {
				//Maj de la date
				$bind['DATE_POST'] = new Zend_Db_Expr('NOW()');
			}
			$this->_db->update($this->_table, $bind, $this->_primary . ' = ' . $id);
		}
		//Modification des fichiers de trzductions
		$lang = array(
			'title_' . $id		=>	$news['title'],
			'content_' . $id	=>	$news['content']
		);
		$writer = new Ccsd_Lang_Writer($lang);
		$writer->add($this->_dirLangFiles, 'news');	
	}
	
	/**
	 * Suppression d'une actualité
	 * @param unknown_type $newsid
	 */
	public function delete($newsid)
	{
		$this->_db->delete($this->_table, $this->_primary . ' = ' . $newsid);
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
}