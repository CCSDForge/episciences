<?php
/**
 * Configuration générale d'un site
 * @author yannick
 */
class Ccsd_Website_Common
{
	
	protected $_table = 'WEBSITE_SETTINGS';
	protected $_sidField = 'SID';
	protected $_sid = 0;
	protected $_db = null;
	protected $_languages = array();
	
	protected $_settings = array();

    /**
     * @deprecated : utiliser Hal_Site_Settings
     * Ccsd_Website_Common constructor.
     * @param $sid
     * @param $options
     */
	public function __construct($sid, $options)
	{
		$this->_sid = $sid;
		foreach ($options as $option => $value) {
			$this->{'_' . $option} = $value;
		}
		$this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
	}
	
	/**
	 * Chargement des parametres du site
	 */
	public function load()
	{
		$sql = $this->_db->select()
			->from($this->_table, array('SETTING', 'VALUE'))
			->where($this->_sidField . ' = ?', $this->_sid);
		$this->_settings = $this->_db->fetchPairs($sql);
		if (isset($this->_settings['languages'])) {
			$this->_settings['languages'] = unserialize($this->_settings['languages']);
		}
	}
	
	/**
	 * Enregistrement des parametres du site
     * @param array $values
	 */
	public function save($values)
	{
		//1- Suppression des anciennes valeurs (sauf PIWIKID)
		$this->_db->delete($this->_table, array($this->_sidField . ' = ' . $this->_sid, "SETTING != 'PIWIKID'"));
		//2- Enregistrement des nouvelles valeurs
		foreach ($values as $setting => $value) {
			if ($setting == 'save') {
				continue;
			} else if ($setting == 'languages') {
				$value = serialize($value);
			}
			$this->_db->insert($this->_table, array($this->_sidField => $this->_sid, 'SETTING' => $setting, 'VALUE' => $value));
		}
	}
	
	/**
	 * Récupération du formulaire
     * @param bool $load
	 * @return Ccsd_Form
	 */
	public function getForm($load = true)
	{
		$form = new Ccsd_Form();
		$form->setAttrib('class', 'form-horizontal');
		$options = array();
		foreach ($this->_languages as $lang) {
			$options[$lang] = $lang;
		}
		
		$form->addElement('multiselect', 'languages', array(
		        'label'		=> "Langues de l'interface",
		        'class'		=> "form-control",
		        'multioptions'	=> $options,
		        'multiple'	=> true,
		        'required'	=>	true
		));

        $form->setActions(true)->createSubmitButton();

		if ($load) {
			$this->load();
			$form->populate($this->_settings);
		}
		
		return $form;
	}
	
	/**
	 * Retourne les langues de l'interface de la revue
     * @return string[]
	 */
	public function getLanguages()
	{
		$languages = array();
		$sql = $this->_db->select()->from($this->_table, 'VALUE')->where($this->_sidField . ' = ?', (int)$this->_sid)->where('SETTING = ?', "languages");
		$str = $this->_db->fetchOne($sql);
		if ($str) {
			$languages = unserialize($str);
		}
		return $languages;
	}

	/**
     * Retourne le PIWIKID d'un site
     * @return string
     */
    public function getPiwikid()
    {
        $sql = $this->_db->select()->from($this->_table, 'VALUE')->where($this->_sidField . ' = ?', (int)$this->_sid)->where('SETTING = ?', "PIWIKID");
        $str = $this->_db->fetchOne($sql);
        if ($str) {
            return $str;
        }
        return null;
    }
}