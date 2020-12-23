<?php

/**
 * Gestion de l'apparence d'un site
 * @author yannick
 *
 */
class Ccsd_Website_Style
{
	/**
	 * Types d'orientation du menu du site
	 */
	const ORIENTATION_V = 'vertical'; //SUPP
	const ORIENTATION_H = 'horizontal'; //SUPP
	
	const MENU_LIST		=	'list';
	const MENU_TABS		=	'tabs';
	const MENU_ACCORDION=	'accordion';
	const MENU_SLIDER	=	'slider';
	
	
	/**
	 * Types de personnalisation
	 */
	const TYPE_TPL	=	'template';
	const TYPE_FORM	=	'simple';
	const TYPE_CSS	=	'css';
	
	/**
	 * Formulaire de configuration du style du site
	 * @var string
	 */
	const FORM_INI = __DIR__ . '/Form/configs/style.ini';
	
	/**
	 * Nom du template du fichier CSS 
	 * @var string
	 */
	const CSS_FILENAME =	'style.css';
	
	/**
	 * Table de stockage des
	 * @var unknown_type
	 */
	const TABLE = 'WEBSITE_STYLES';
	
	/**
	 * Nom du champ correspondant à l'identifiant d'un site
	 * @var string
	 */
	protected $_fieldSID = 'SID';
	
	/**
	 * Identifiant du site
	 * @var int
	 */
	protected $_sid = 0;
	
	/**
	 * Formulaire de personnalisation des styles
	 * @var Ccsd_Form
	 */
	protected $_form = null;
	
	/**
	 * Lien vers le template CSS
	 * @var string
	 */
	protected $_templateFile = '';
	
	/**
	 * Liste des styles définis pour le site
	 * @var array
	 */
	protected $_tags = array();

	/**
	 * Répertoire d'enregistrement du fichier CSS
	 * @var string
	 */
	protected $_dirname = '';
	
	/**
	 * Base URL pour l'accès aux ressources publiques
	 * @var string
	 */
	protected $_publicUrl = '';
	
	/**
	 * Base URL pour l'accès au répertoire des templates prédéfinis
	 * @var string
	 */
	protected $_tplUrl = '';
	
	public function __construct($sid, $dirname = '', $publicUrl = '')
	{
		$this->_sid = $sid;
		$this->_dirname = $dirname;
		$this->_publicUrl = $publicUrl;
		$this->_templateFile = __DIR__ . '/Style/' . self::CSS_FILENAME;
		$this->initForm();
	}
	
	/**
	 * Récupération du formulaire de personnalisation des styles
	 * @return Ccsd_Form
	 */
	public function getForm()
	{
		if ($this->_form == null) {
			$this->initForm();
		}
		return $this->_form;
	}
	
	public function initForm()
	{
		$config = new Zend_Config_Ini(self::FORM_INI);
		$this->_form = new Ccsd_Form();
		$this->_form->setConfig($config->form);
        $this->_form->setActions(true)->createSubmitButton();
	}
	
	/**
	 * Initialisation des styles du site
	 * @param array $params
	 */
	public function setStyles($params)
	{
		foreach ($params as $key => $value) {
			if (in_array($key, array('module', 'action', 'controller', 'MAX_FILE_SIZE', 'save'))) {
				continue;
			}
			$this->_tags[$key] = $value;
		}
		//Cas particulier de l'image de fond
		if (isset($this->_tags['bg_img']) && ! isset($this->_tags['bg_img_file']) ) {
			if ($this->_tags['bg_img']) {
				//Conservation de l'image de fond
				$this->_tags['bg_img_file'] = $this->getStyle('bg_img_file');
			} 
		}
	}
	
	/**
	 * Chargement des styles stockés en base
	 */
	public function load()
	{
		//echo __FILE__ . '<br>' . __DIR__ . '<br>';exit;
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$query = $db->select()
		->from(self::TABLE)
		->where($this->_fieldSID . ' = ?', (int)$this->_sid);
			
		foreach ($db->fetchAll($query) as $row) {
			$this->_tags[$row['SETTING']] = $row['VALUE'];
		}
	}
	
	/**
	 * Récupération des différents styles
	 */
	public function getStyles()
	{
		if (count($this->_tags) == 0) {
			$this->load();
		}
		return $this->_tags;
	}
		
	/**
	 * Récupération d'un style en particulier
	 * @param string $setting
	 * @return string
	 */
	public function getStyle($setting)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()
		->from(self::TABLE, 'VALUE')
		->where('SETTING = ?', $setting)
		->where($this->_fieldSID . ' = ?', $this->_sid);
			
		return $db->fetchOne($sql);
	}
	
	/**
	 * Récupération de l'orientation du menu du site
	 * @return string
	 */
	public function getNavigationOrientation()
	{
		if (count($this->_tags) == 0) {
			$this->load();
		}
		if (!isset($this->_tags['navigation']) || ($this->_tags['navigation'] != self::MENU_LIST && $this->_tags['navigation'] != self::MENU_TABS && $this->_tags['navigation'] != self::MENU_SLIDER && $this->_tags['navigation'] != self::MENU_ACCORDION)) {
			$this->_tags['navigation'] = self::MENU_TABS;
		}
		return $this->_tags['navigation'];
	}
	
	public function displayBreadCrumbs()
	{
		if (count($this->_tags) == 0) {
			$this->load();
		}
		return isset($this->_tags['breadcrumbs']) && $this->_tags['breadcrumbs'] == 'yes';
	}
	
	/**
	 * Récupération de la largeur du site (fluide, fixe)
	 * @return string
	 */
	public function getContainerClass()
	{
		if (count($this->_tags) == 0) {
			$this->load();
		}
		if (isset($this->_tags['container_width']) && $this->_tags['container_width'] == 'fluid') {
			return '-fluid';
		}
		return '';
	}
	
	/**
	 * Population du formulaire avec les styles du site
	 */
	public function populate()
	{
		$data = $this->getStyles();
		//Récupération de la feuille de style CSS
		$data['css'] = $this->getCss();
		$this->_form->populate($data);
	}
		
	/**
	 * Enregistrement des styles
	 * @param array $params
	 */
	public function save($params = array())
	{
		if (count($params) > 0) {
			$this->setStyles($params);
		}
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		//1- Suppression des anciennes valeurs
		$db->delete(self::TABLE, $this->_fieldSID . ' = ' . $this->_sid);
		//2- Enregistrement des nouvelles valeurs
		foreach ($this->_tags as $tag => $value) {
			if ($tag == 'css' || $value == '') {
				continue;
			}
			$db->insert(self::TABLE, array($this->_fieldSID => $this->_sid, 'SETTING' => $tag, 'VALUE' => $value));
			if ($this->getForm()->getElement($tag) && ($this->getForm()->getElement($tag)->getType() == 'Zend_Form_Element_File' || $this->getForm()->getElement($tag)->getType() == 'Ccsd_Form_Element_File') && is_string($this->getForm()->getElement($tag)->getFileName())) {
				//Enregistrement d'un fichier
				rename($this->getForm()->getElement($tag)->getFileName(), $this->_dirname . $value);
			}
		}
		$this->saveCss();
	}
	
	/**
	 * Création du fichier CSS
	 * Enregistrement de la nouvelle feuille de style dans le répertoire $_dirname
	 */
	public function saveCss()
	{
		if (isset($this->_tags['type']) && $this->_tags['type'] == self::TYPE_CSS) {
			//Edition direct de la feuille de styles
			$data = isset($this->_tags['css']) ? $this->_tags['css'] : '';
		} else {
			//Edition à partir du formulaire
			$this->_templateFile = __DIR__ . '/Style/' . self::CSS_FILENAME;
			$data = file_get_contents($this->_templateFile);
			foreach ($this->_tags as $tag => $value) {
				if ($tag == 'bg_img_file') {
                    $value = $this->_publicUrl . $value;
                };
                if (($tag == 'bg_img') && ($value == '0')) {
                    // On supprime la ligne complete de background-image
                    $data = preg_replace('/background-image:.*/', '', $data);
				}
				$data = str_replace('%' . $tag . '%', $value, $data);
			}
		}
		
		if ($this->_dirname != '') {
			if (! is_dir($this->_dirname)) {
				mkdir($this->_dirname, 0777, true);
			}
			
			file_put_contents($this->_dirname . self::CSS_FILENAME, $data);		
		}
	}
	
	/**
	 * Récupération de la feuille de styles pour un site
	 */
	public function getcss()
	{
		if (is_file($this->_dirname . self::CSS_FILENAME)) {
			$content = file_get_contents($this->_dirname . self::CSS_FILENAME);
		} else {
			$this->_templateFile = __DIR__ . '/Style/' . self::CSS_FILENAME;
			$content = file_get_contents($this->_templateFile);
		}
		return $content;
	}
	
	/**
	 * Retourne le lien vers le fichier CSS du site
	 */
	public function getCssFile()
	{
		if (count($this->_tags) == 0) {
			$this->load();
		}
		$cssFile = false;
		if (isset($this->_tags['type'])) {
			if ($this->_tags['type'] == self::TYPE_TPL && isset($this->_tags['template'])) {
				$cssFile = $this->_tplUrl . $this->_tags['template'] . '.css';
			} else if (is_file($this->_dirname . 'style.css')) {
				$cssFile = $this->_publicUrl . 'style.css?' . filemtime($this->_dirname . 'style.css');
			}
		}
		return $cssFile;	
	}
}