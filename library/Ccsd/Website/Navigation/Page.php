<?php

/**
 * Page générale d'un site
 * @author Yannick Barborini
 *
 */
abstract class Ccsd_Website_Navigation_Page
{
    /**
     * Identifiant de la page
     * @var int
     */
    protected $_pageId = 0;

    /**
     * Identifiant de la page parente (0 par défaut)
     * @var int
     */
    protected $_pageParentId = 0;

    /**
     * Tableau des langues dispo de la page
     * @var array
     */
    protected $_languages = [];

    /**
     * Tableau des labels de la page
     * @var array
     */
    protected $_labels = [];

    /**
     * Page présente plusieurs fois pour un site
     * @var boolean
     */
    protected $_multiple = false;

    /**
     * controller
     * @var string
     */
    protected $_controller = '';

    /**
     * Action
     * @var string
     */
    protected $_action = '';

    /**
     * Formulaire de paramétrage de la page
     * @var Zend_Form
     */
    protected $_form = null;

    /**
     * Initialisation de la page
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->setOptions($options);
        if (PHP_SAPI != 'cli') {
            $this->_form = new Ccsd_Form();
        }
    }

    /**
     * Définition des options de la page
     * @param array $options
     */
    public function setOptions($options = [])
    {
        foreach ($options as $option => $value) {
            $option = strtolower($option);
            switch ($option) {
                case 'languages':
                    $this->_languages = $value;
                    break;
                case 'pageid'   :
                    $this->_pageId = (int)$value;
                    break;
                case 'labels'   :
                    $this->setLabels($value);
                    break;
                case 'parentid' :
                    $this->setPageParentId($value);
                    break;
            }
        }
    }

    /**
     * Initialisation du label de la page
     * @param string $label
     * @param string $lang
     */
    public function setLabel($label, $lang)
    {
        $this->_labels[$lang] = $label;
    }

    /**
     * Retourne le nom de la classe de la page
     * @return string
     */
    public function getPageClassLabel()
    {
        return $this->getPageClass();
    }

    /**
     * Retourne la classe de la page
     * @return string
     */
    public function getPageClass()
    {
        return get_class($this);
    }

    /**
     * récupération de l'id de la page parente
     * @return int
     */
    public function getPageParentId()
    {
        return $this->_pageParentId;
    }

    /**
     * Initialisation de l'id de la page parente
     * @param string $pageParentId
     */
    public function setPageParentId($pageParentId)
    {
        $this->_pageParentId = $pageParentId;
    }

    /**
     * Retourne les données supplémentaires de la page
     * @return string
     */
    public function getSuppParams()
    {
        return '';
    }

    /**
     * Indique si une page est multiple pour un site
     */
    public function isMultiple()
    {
        return $this->_multiple;
    }

    public function load()
    {
    }

    /**
     * Transforme l'objet page en tableau associatif
     * @return array
     */
    public function toArray()
    {
        $array = [];
        $array['label'] = $this->getLabelKey();
        $array['controller'] = $this->getController();
        $array['action'] = $this->getAction();
        $array['resource'] = $this->getResource();
        return $array;
    }

    /**
     * Retourne la clé de traduction du label de la page
     */
    public function getLabelKey()
    {
        return 'menu-label-' . $this->getPageId();
    }

    /**
     * récupération de l'id de la page
     * @return int
     */
    public function getPageId()
    {
        return $this->_pageId;
    }

    /**
     * Initialisation de l'id de la page
     * @param string $pageParentId
     */
    public function setPageId($pageId)
    {
        $this->_pageId = $pageId;
    }

    /**
     * Retourne le controller associé à la page
     */
    public function getController()
    {
        return $this->_controller;
    }

    /**
     * Retourne l'action associée à la page
     * @return string
     */
    public function getAction()
    {
        return $this->_action;
    }

    /**
     * Retourne la ressource associée à la page
     * @return string
     */
    public function getResource()
    {
        return ($this->getController() != '' ? ($this->getController() . '-') : '') . $this->getAction();
    }

    /**
     * Récupération du formulaire pour éditer une page
     * @param unknown_type $pageidx
     * @return Ccsd_Form|Zend_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public function getForm($pageidx)
    {
        $this->initForm();
        if (!$this->_form->getElement('pageid')) {
            $this->_form->addElement('hidden', 'pageid', ['value' => $pageidx, 'belongsTo' => 'pages_' . $pageidx]);
        }
        if (!$this->_form->getElement('type')) {
            $this->_form->addElement('hidden', 'type', ['label' => 'Type de la page', 'value' => $this->getPageClass(), 'belongsTo' => 'pages_' . $pageidx]);
        }
        if (!$this->_form->getElement('labels')) {
            $populate = [];
            foreach ($this->getLanguages() as $lang) {
                $populate[$lang] = $lang;
            }
            $this->_form->addElement('multiTextSimpleLang', 'labels', [
                'label' => 'Titre de la page',
                'required' => true,
                'value' => $this->getLabels(),
                'populate' => $populate,
                'class' => 'inputlangmulti',
                //'length' => 0,
                'belongsTo' => 'pages_' . $pageidx,
                'validators' => [new Ccsd_Form_Validate_RequiredLang(['langs' => $this->getLanguages()])]
            ]);
        }
        return $this->_form;
    }

    public function initForm()
    {
        unset($this->_form);
        $this->_form = new Ccsd_Form();
        $this->_form->setAttrib('class', 'form');
    }

    /**
     * Récupération de la liste des langues de la page
     * @return array
     */
    public function getLanguages()
    {
        return $this->_languages;
    }

    /**
     * Récupération des labels de la page
     * @return array
     */
    public function getLabels()
    {
        $res = [];
        foreach ($this->getLanguages() as $lang) {
            $res[$lang] = $this->getLabel($lang);
        }
        return $res;
    }

    /**
     * Initialisation des labels
     * @param $labels
     */
    public function setLabels($labels)
    {
        if (is_string($labels)) {
            foreach ($this->getLanguages() as $lang) {
                $this->setLabel($labels, $lang);
            }
        } else {
            //Réinitialisation
            $this->_labels = [];
            foreach ($labels as $lang => $label) {
                if ($label != '') {
                    $this->setLabel($label, $lang);
                }
            }
        }
    }

    /**
     * Retourne le label dans la langue demandée
     * @param string $lang
     * @return mixed|string
     */
    public function getLabel($lang)
    {
        return isset($this->_labels[$lang]) ? $this->_labels[$lang] : '';
    }

}