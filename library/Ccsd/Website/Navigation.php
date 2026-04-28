<?php

/**
 * Classe Website permettant de gérer le conteu d'un site web
 * @author Yannick Barborini
 *
 */
abstract class Ccsd_Website_Navigation
{

    /**
     * Informations sur la DB
     * @var Zend_Db_Table_Abstract
     */
    protected $_db = null;
    /**
     * Nom de la table utilisée
     * @var string
     */
    protected $_table = '';
    /**
     * Clé primaire de la table
     * @var string
     */
    protected $_primary = '';

    /**
     * Liste des types de pagez disponibles
     * @var array
     */
    protected $_pageTypes = array();

    /**
     * Liste des pages du site
     * @var Ccsd_Website_Navigation_Page[]
     */
    protected $_pages = array();

    /**
     * Ordre des pages
     * @var array
     */
    protected $_order = array();

    /**
     * Langues disponibles du site
     * @var string[]
     */
    protected $_languages = array();

    /**
     * Loader de classe
     * @var array
     */
    protected $_loaders = array();

    /**
     * Indice pour l'ajout de nouvelles pages
     * @var int
     */
    protected $_idx = 0;

    /**
     * Constructeur
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->setOptions($options);
    }

    /**
     * Fonction permettant d'initialiser l'objt Website
     * @param array $options
     */
    abstract public function setOptions($options = array());

    /**
     * Enregistrement du menu d'un site
     * Dépend de l'environnement. Doit être redéfini dans les applications
     */
    abstract public function save();

    /**
     * Récupération de la liste des types de pages disponibles
     * @param bool $reload
     * @return array
     * @throws ReflectionException
     * @throws Zend_Loader_Exception
     */
    public function getPageTypes($reload = false)
    {
        if ($reload || count($this->_pageTypes) == 0) {
            $this->_pageTypes = array();
            $reflect = new ReflectionClass(get_class($this));
            foreach ($reflect->getConstants() as $const => $value) {
                if (str_starts_with($const, 'PAGE_')) {
                    $this->_pageTypes[$value] = $this->getPageClass($value);
                }
            }
        }
        return $this->_pageTypes;
    }

    /**
     * Retourne le nom de la classe d'une page
     * @param string $page
     * @return string
     * @throws Zend_Loader_Exception
     */
    public function getPageClass($page)
    {
        $class = get_class($this) . '_' . 'Page' . '_' . ucfirst($page);

        if (!$this->getPluginLoader('page', get_class($this))->load($page, false)) {
            $class = get_parent_class($this) . '_' . 'Page' . '_' . ucfirst($page);
            if (!$this->getPluginLoader('page', get_parent_class($this))->load($page, false)) {
                $class = get_parent_class($this) . '_' . 'Page';
            }
        }
        return $class;
    }

    /**
     * Fonction permettant de récupérer le contenu d'un site
     * Dépend de l'environnement. Doit être redéfini dans les applications
     */
    abstract public function load();

    /**
     * Retourne le répertoire des classes à charger
     * @param string $folder répertoire à charger
     * @param string $className nom de la classe courante pour définir le chemin vers le répertoire à charger
     * @return Zend_Loader_PluginLoader
     */
    public function getPluginLoader($folder, $className)
    {
        $prefixSegment = $className . '_' . ucfirst($folder);
        if (!isset($this->_loaders[$prefixSegment])) {
            $pathSegment = str_replace('_', '/', $prefixSegment);

            require_once 'Zend/Loader/PluginLoader.php';
            $this->_loaders[$prefixSegment] = new Zend_Loader_PluginLoader(
                array($prefixSegment . '_' => $pathSegment . '/')
            );
        }
        return $this->_loaders[$prefixSegment];
    }

    /**
     * Récupération du contenu d'un site (liste des pages)
     * @return array
     */
    public function getPages()
    {
        return $this->_pages;
    }

    /**
     * Retourne l'ordre des pages dans la navigation du site
     * @return array
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * Retourne la page d'indicd $idx
     * @param int $idx
     * @return Ccsd_Website_Navigation_Page|bool
     */
    public function getPage($idx)
    {
        if (isset($this->_pages[$idx])) {
            return $this->_pages[$idx];
        }
        return false;
    }

    /**
     * Change l'ordre des pages dans un site
     * @param array $order nouvel ordre des pages
     * @return void
     */
    public function changeOrder(array $order)
    {
        /*
         * exemple de tableau order (retourné par jquery nestedSortable)
         * 0	=>	root
         * 1	=>	root
         * 2	=>	1
         * 3	=> 	1
         * 4	=>	root
         *
         * */

        $this->_order = array();
        foreach ($order as $pageid => $value) {
            if ($value == 'root') {
                $this->_order[$pageid] = array();
            } else {
                if (isset($this->_order[$value])) {
                    $this->_order[$value][$pageid] = array();
                } else {
                    foreach ($this->_order as $i => $elem) {
                        if (is_array($elem) && isset($this->_order[$i][$value])) {
                            $this->_order[$i][$value][$pageid] = array();
                        }
                    }
                }
            }
        }
    }

    /**
     * Ajoute une nouvelle page à un site
     * @param mixed $page
     * @return integer
     * @throws Zend_Loader_Exception
     */
    public function addPage($page)
    {
        if (is_string($page)) {
            $class = $this->getPageClass($page);
            $page = new $class(array('languages' => $this->_languages));
        }

        if ($page instanceof Ccsd_Website_Navigation_Page) {
            $id = $this->_idx; //nouvel indice de la page
            $this->_idx++;
            $this->_pages[$id] = $page;
            $this->_order[$id] = array();
            return $id;
        } else {
            return false;
        }
    }

    /**
     * Retourne le nombre de pages du menu
     * @return number
     */
    public function getPagesCount()
    {
        return count($this->_pages);
    }

    /**
     * Modification d'une page du menu
     * @param int $id
     * @param array $options
     */
    public function setPage($id, $options)
    {
        if (isset($this->_pages[$id])) {
            $this->_pages[$id]->setOptions(array_merge($options, array('languages' => $this->_languages)));
        }
    }

    /**
     * Suppression d'une page du site
     */
    public function deletePage(int $pageIdx, string $pageCodeToRemove = null): void
    {

        if ($pageCodeToRemove) {
            $this->removePageFromDatabase($pageCodeToRemove);
        }

        //Suppression de la page
        unset($this->_pages[$pageIdx]);

        //Supression de la page dans le tableau d'ordre des pages
        foreach ($this->_order as $pageid => $spages) {
            if ($pageid == $pageIdx) {
                unset($this->_order[$pageid]);
                break;
            }
            if (count($spages) > 0) {
                foreach ($spages as $i => $spageid) {
                    if ($spageid == $pageIdx) {
                        unset($this->_order[$pageid][$i]);
                        break;
                    }
                }
            }
        }
    }

    /**
     * @param string $pageCodeToRemove
     * @return void
     */
    private function removePageFromDatabase(string $pageCodeToRemove): void
    {
        $modele_regex = "/^page-/";
        $pageCodeToRemove = preg_replace($modele_regex, "", $pageCodeToRemove);
        try {
            if (Episciences_Website_Navigation_Page_Predefined::isPredefinedPage($pageCodeToRemove)) {
                Episciences_Page_Manager::delete($pageCodeToRemove, RVCODE);
            }
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
    }
}