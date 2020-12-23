<?php
/**
 * Gestion du bandeau d'un site
 * @author yannick
 *
 */
class Ccsd_Website_Header
{
	/**
	 * Table permettant de stocker une version de l'en-tête
	 * @var string
	 */
	const TABLE = 'WEBSITE_HEADER';
	
	/**
	 * Type de logo (image)
	 * @var unknown_type
	 */
	const LOGO_IMG = 'img';
	
	/**
	 * Définition des champs du formulaire de création d'un logo
	 * @var string
	 */
	const FORM_INI = 'Ccsd/Website/Form/configs/header-logo.ini';
	
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
	 * Formulaire de création d'un logo
	 * @var unknown_type
	 */
	protected $_form = null;
		
	/**
	 * Répertoire public (stockage des images, css, ...)
	 * @var string
	 */
	protected $_publicDir = '';
	
	/**
	 * Base URL de l'espace publique
	 * @var string
	 */
	protected $_publicUrl = '';
	
	/**
	 * Repertoire de stockage du layout
	 * @var String
	 */
	protected $_layoutDir = '';
	
	/**
	 * Tableau des logos de l'en-tête du site
	 * @var array
	 */
	public $_logos = array();
	
	/**
	 * Langues de l'interface
	 * @var array
	 */
	protected $_languages = array();
	
	/**
	 * Constructeur
	 * @param int $sid identifiant du site
	 * @param string $publicDir Répertoire de stockage public
	 * @param string $publicUrl Base url du répertoire public
	 * @param string $layoutDir Répertoire pour créer le fichier de cache
	 */
	public function __construct($sid, $publicDir = '', $publicUrl = '', $layoutDir = '', $langDir = '')
	{
		$this->_sid = $sid;
		$this->_publicDir = $publicDir;
		$this->_publicUrl = $publicUrl;
		$this->_layoutDir = $layoutDir;
		$this->_langDir = $langDir;
	}
	
	/**
	 * Récupération en base des données pour un site
	 */
	public function load()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$query = $db->select()
		->from(self::TABLE)
		->where($this->_fieldSID . ' = ?', $this->_sid)
		->order('LOGOID ASC');
		
		$this->_logos = array();
		foreach ($db->fetchAll($query) as $i => $row) {
			foreach ($row as $key => $value) {
				$key = strtolower($key);
				if ($key == 'text') {
					$value = unserialize($value);
				} 
				$this->_logos['logo_' . $i][$key] = $value;
			}
		}
	}
	
	/**
	 * Récupération du formulaire d'ajout d'un log
	 * @return Ccsd_Form
	 */
	public function getForms($load = true)
	{
		if ($load) {
			$this->load();
		}
		$forms = array();
		$config = new Zend_Config_Ini(self::FORM_INI);
		foreach ($this->getLogos() as $i => $logo) {
			$forms[$i] = $this->getLogoForm($i, $config);
			$data = array_merge($logo, array('img_tmp' => isset($logo['img']) ? $logo['img'] : ''));
			$forms[$i]->populate($data);
		}
		return $forms;
	}
	
	/**
	 * Retourne le formulaire d'ajout d'un logo
	 * @return Ccsd_Form
	 */
	public function getLogoForm($id, $config = null)
	{
		if ($config == null) {
			$config = new Zend_Config_Ini(self::FORM_INI);
		}
		$form = new Ccsd_Form();
		$form->setConfig($config->form);
		foreach ($form->getElements() as $element) {
			$form->getElement($element->getName())->setOptions(array('belongsTo' => $id));
		}
		$form->removeDecorator('Form');
		$form->getElement('text')->setLanguages($this->_languages);
		
		return $form;
	}
	
	/**
	 * Retourne le tableau des logos de l'en-tête
	 * @return array
	 */
	public function getLogos()
	{
		if (count($this->_logos) == 0) {
			$this->load();
		}
		return $this->_logos;
	}
	
	/**
	 * Initialisation de l'objet en focntion de la soumission d'un formulaire
	 * @param array $params liste des nouveaux logos d'un site
	 * @param array $files tableau d'images (si logo de type image)
	 */
	public function setHeader($params, $files)
	{
		foreach ($params as $logoid => $logo) {
			if (is_array($logo)) {
				foreach ($logo as $key => $value) {
					if (!isset($this->_logos[$logoid])) {
						$this->_logos[$logoid] = array();
					}
					if ($key == 'img_tmp') {
						$key = 'img';
					} else if ($key == 'text' && is_array($value)) {
						foreach ($value as $k => $v) {
							if (trim($v) == '') {
								unset($value[$k]);
							}
						}
					}	
					$this->_logos[$logoid][$key] = $value;
				}
			}
		}

		foreach ($this->_logos as $logoid => $logo) {
			if (isset($logo['type']) && $logo['type'] == self::LOGO_IMG) {
				//Logo image
				if (isset($files[$logoid]['name']['img']) && $files[$logoid]['name']['img'] != '') {
					$this->_logos[$logoid]['img'] = Ccsd_File::renameFile($files[$logoid]['name']['img'], $this->_publicDir);
				}				
			}
		}
		
	}
	
	public function isValid($params, $files)
	{
		$errors = array();
		foreach ($params as $logoid => $logo) {
			if (is_array($logo)) {
				if (isset($logo['type'])) {
					if ($logo['type'] == self::LOGO_IMG) {
						//Logo de type image
						if (!($logo['img_tmp'] != '' ||  (isset($files[$logoid]['name']['img']) && $files[$logoid]['name']['img'] != ''))) {
							echo $logoid;
							$errors[$logoid] = 'img';
						}
					} else {
						//Logo de type texte
						$complete = true;
						foreach ($this->_languages as $lang) {
							if (! isset($logo['text'][$lang]) || $logo['text'][$lang] == '') {
								$complete = false;
							}
						}
						if (! $complete) {
							$errors[$logoid] = 'text';
						}
					}		
				}
			}
		}
		return count($errors) == 0 ? true : $errors;
	}
	
	/**
	 * Enregistrement du nouvel en-tête
	 * @param array $params
	 * @param array $files
	 */
	public function save ($params, $files)
	{
		if (count($params) > 0) {
			$this->setHeader($params, $files);
		}
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		//1- Suppression des anciennes valeurs
		$db->delete(self::TABLE, $this->_fieldSID . ' = ' . $this->_sid);
		//2- Enregistrement des nouvelles valeurs
		foreach ($this->_logos as $logoid => $logo) {
			$data = array($this->_fieldSID => $this->_sid);
			foreach ($logo as $k => $v) {
				if ($k == 'text') {
					$v = serialize($v);
				}
				$data[strtoupper($k)] = $v;
			}
			$db->insert(self::TABLE, $data);
			
			if (!is_dir($this->_publicDir)) {
                mkdir($this->_publicDir, 0777, true);
            }
            if (isset($logo['img']) && isset($files[$logoid]['tmp_name']['img']) && is_file($files[$logoid]['tmp_name']['img'])) {
				rename($files[$logoid]['tmp_name']['img'], $this->_publicDir . $logo['img']);
			}	
		}
		$this->createHeader();
	}

    public function setLanguages($languages)
    {
        $this->_languages = $languages;
    }
	
	/**
	 * Création du fichier en-tête en HTML
	 */
	public function createHeader()
	{
		foreach ($this->_languages as $lang) {
			$content  = "<table width=\"100%\" cellpadding=\"0\" style=\"position:relative;\">\n<tr>\n";
			foreach ($this->_logos as $logo) {
				$content .= "<td align=\"" . $logo['align'] . "\">";
				if ($logo['type'] == self::LOGO_IMG) {
					if ($logo['img_href'] != "") {
						$content .= "<a href=\"" . $logo['img_href'] . "\" border=\"0\" target=\"_blank\">";
					}
                    if (substr($logo['img'], 0, 4) != "/img") {
                        $logo['img'] = $this->_publicUrl . $logo['img'];
                    }

                    $content .= "<img src=\"" . $logo['img'] . "\"";
					if ($logo['img_width'] != "") {
						$content .= " width=\"" . $logo['img_width'] . "\"";
					}
					if ($logo['img_height'] != "") {
						$content .= " height=\"" . $logo['img_height'] . "\"";
					}
					if ($logo['img_alt'] != "") {
						$content .= " alt=\"" . $logo['img_alt'] . "\"";
					}
					$content .= " />";
					if ($logo['img_href'] != "") {
						$content .= "</a>";
					}
				} else {
					$content .= "<span";
					if ($logo['text_class'] != "") {
						$content .= " class=\"" . $logo['text_class'] . "\"";
					}
					if ($logo['text_style'] != "") {
						$content .= " style=\"" . $logo['text_style'] . "\"";
					}
					$content .= ">" . (isset($logo['text'][$lang]) ? $logo['text'][$lang] : '') . "</span>";
				}
				$content .= "</td>\n";
			}
			$content .= "</tr>\n</table>\n";
			
			if (! is_dir($this->_layoutDir)) {
				mkdir($this->_layoutDir, 0777, true);
			}
			
			file_put_contents($this->_layoutDir . 'header.' . $lang . '.html', $content);			
		}
		
	}
}