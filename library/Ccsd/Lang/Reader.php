<?php

/**
 * Classe permettant de récupérer des traductions
 * @author yannick
 *
 */
class Ccsd_Lang_Reader
{

	protected $_data = array();
	

	protected $_languages = array();
		
	protected $_filename = '';
	
	protected $_dirPath = '';
	
	
	public function __construct($filename, $dirPath, $languages, $load = true)
	{
		$this->_filename = $filename;
		$this->_languages = $languages;
		$this->_dirPath = $dirPath;
		if ($load) {
			$this->load();
		}
	}
	
	public function load()
	{
		foreach ($this->_languages as $lang) {
			$filename = $this->_dirPath . $lang . '/' . $this->_filename . '.php';
			if (is_file($filename)) {
				foreach (include $filename as $key => $value) {
					$this->_data[$key][$lang] = $value;
				}
			}	
		}
	}
	
	public function get($key = '', $lang = '') 
	{
		if (in_array($lang, $this->_languages)) {
			//Retourne pour une langue donnée
			if ($key != '') {
				//On demande une clé
				return isset($this->_data[$key][$lang]) ? $this->_data[$key][$lang] : '';
			} else {
				//On retourne toutes les traductions pour une langue donnée
				$res = array();
				foreach ($this->_data as $key => $values) {
					$res[$key] = isset($values[$lang]) ? $values[$lang] : '';
				}
				return $res;
			}
		} else if ($lang == '') {
			//Retourne pour toutes les langues
			if ($key != '') {
				//On demande une clé
				return isset($this->_data[$key]) ? $this->_data[$key] : '';
			} else {
				//On retourne toutes les traductions 
				return $this->_data;
			}
			
		}
		return false;
	}
	
}