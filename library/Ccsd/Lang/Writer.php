<?php

/**
 * Classe permettant d'écrire des fichiers de langues (format php)
 * @author yannick
 *
 */
class Ccsd_Lang_Writer
{
	/**
	 * Tableau des traductions
	 * @var array
	 */
	protected $_data = array();
		
	/**
	 * Langues
	 * @var array
	 */
	protected $_languages = array();
		
	/**
	 * Répertoire de stockage des fichiers de traduction
	 * @var string
	 */
	protected $_dirPath = '';
	
	public function __construct($data)
	{
		if (count($data) > 0) {
			foreach ($data as $row) {
				foreach (array_keys($row) as $lang) {
					if (! in_array($lang, $this->_languages)) {
						$this->_languages[] = $lang;
					}
				}
			}
		}
		
		foreach ($data as $key => $row) {
			foreach ($row as $lang => $value) {
				$this->_data[$lang][$key] = $value;
			}
		}	
	}
	
	/**
	 * Ecriture des fichiers de traduction
	 * @param string $dirPath Répertoire de stockage des fichiers de langues
	 * @param string $prefixFilename Nom du fichier de langue
	 */
	public function write($dirPath = '', $prefixFilename = '')
	{
		foreach ($this->_languages as $lang) {
			if ($dirPath == '') {
				Zend_Debug::dump($this->createFileContent($this->_data[$lang]));
			} else {
				$this->writeFile($dirPath . $lang . '/', $prefixFilename . '.php', $lang);
			}
		}		
	}
	
	protected function writeFile ($dir, $filename, $lang)
	{
		if (! is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		file_put_contents($dir . $filename, $this->createFileContent($this->_data[$lang]));
	}
	
	
	/**
	 * Ajout de traductions à la suite dans le fichier
	 * @param string $dirPath Répertoire de stockage des fichiers de langues
	 * @param string $prefixFilename Nom du fichier de langue
	 */
	public function add($dirPath = '', $prefixFilename = '')
	{
		foreach ($this->_languages as $lang) {
			if (is_file($dirPath . $lang . '/' . $prefixFilename . '.php')) {
				//Le fichier existe on le modifie
				$this->_data[$lang] = array_merge($this->readFile($dirPath . $lang . '/' . $prefixFilename . '.php'), $this->_data[$lang]);	
			}
			$this->writeFile($dirPath . $lang . '/', $prefixFilename . '.php', $lang);
		}
	}
	
	protected function readFile ($file)
	{
		$array = array();
		if (is_file($file)) {
			$array = include $file;
		}
		return $array;
	}
	
	/**
	 * Création du fichier de langue
	 * @param array $data
	 * @return string
	 */
	protected function createFileContent($data)
	{
		$content = array();
		$content[] = '<?php ';		
		$content[] = 'return array(';
		foreach ($data as $key => $value) {
			$content[] = '"' . addslashes($key) .'" => "' . addcslashes($value, '"') . '",';
		}		
		$content[] = ');';
		return implode("\n", $content);		
	}
	
}