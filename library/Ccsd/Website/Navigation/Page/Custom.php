<?php

/**
 * Page personnalisable
 * @author Yannick Barborini
 *
 */
class Ccsd_Website_Navigation_Page_Custom extends Ccsd_Website_Navigation_Page
{
    protected $_id  =   0;
      
    /**
     * Répertoire des fichiers des données
     * @var string
     */
    protected $_dirPath         =   '';
    /**
     * préfixe du fichier
     * @var string
     */
    protected $_filePrefix      =   'page';
    /**
     * Extension du fichier
     * @var string
     */
    protected $_fileExtension   =   'html';
    
    /**
     * Contenu de la page
     * @var unknown_type
     */
    protected $_content         =   array();
    
    protected $_multiple = true;
    /**
     * Constructeur
     * @param array $options
     */
    public function __construct($options = array())
    {
        parent::__construct($options);
        if (isset($options['id'])) {
            $this->_id = (int) $options['id'];
        }
    }
    
    /**
     * Chargement du contenu de la page
     */
    public function loadContent()
    {
        foreach ($this->_languages as $lang) {
            $file = $this->getFileName($lang);
            if (is_file($file)) {
                $this->_content[$lang] = file_get_contents($file);
            } 
        }
    }
    
    /**
     * Récupération du contenu de la page
     * @param string $lang
     * @return 
     */
    public function getContent($lang = '')
    {
        if (count($this->_content) == 0) {
            $this->loadContent();
        }
        
        if ($lang === '') {
            return $this->_content;
        } else if (isset($this->_content[$lang])) {
            return $this->_content[$lang];
        } else {
            return '';
        }
    }
    
    /**
     * Initialisation du contenu d'une page
     * @param string | array $content
     * @param string $lang
     */
    public function setContent($content, $lang = '')
    {
        if ($lang === '') {
            $this->_content = $content;
        } else {
            $this->_content[$lang] = $content;
        }
    }
    
    /**
     * Enregistrement de la page
     */
    public function saveContent()
    {
        foreach ($this->_languages as $lang) {
            $file = $this->getFileName($lang);
            file_put_contents($file, $this->_content[$lang]);
        }
    }

    /**
     * Récupération du nom du fichier stockant le contenu de la page
     * @param string $lang
     * @return string
     */
    public function getFileName($lang)
    {
        return $this->_dirPath . '/' . $this->_filePrefix . '_' . $this->_id . '.' . $lang . '.' . $this->_fileExtension;
    }
    
    
    
} 