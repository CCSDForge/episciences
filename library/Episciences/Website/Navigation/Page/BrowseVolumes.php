<?php

abstract class Episciences_Website_Navigation_Page_BrowseVolumes extends Episciences_Website_Navigation_Page
{
    protected $_controller = 'browse';
    protected $_action;
    protected $_nbResults;

    public function setOptions($options = array())
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method ($value);
            }
        }

        parent::setOptions($options);
    }

    public function load()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from('WEBSITE_NAVIGATION', 'PARAMS')->where('SID = ?', RVID)->where('TYPE_PAGE = ?', __CLASS__);
        $settings = $db->fetchOne($sql);
        if ($settings) {
            $settings = unserialize($settings, ['allowed_classes' => false]);
            $this->setNbResults($settings['nbResults']);
            return $this;
        }

        return null;
    }

    public function getForm($pageidx)
    {
        parent::getForm($pageidx);

        $this->_form->addElement('select', 'nbResults', array(
            'label' => "Nombre de résultats par page",
            'multioptions' => array('5' => '5', '10' => '10', '15' => '15', '20' => '20', '25' => '25'),
            'value' => $this->getNbResults(),
            'belongsTo' => 'pages_' . $pageidx
        ));

        return $this->_form;
    }

    public function getNbResults()
    {
        return $this->_nbResults;
    }

    public function setNbResults($nbResults)
    {
        $this->_nbResults = (is_numeric($nbResults) && $nbResults > 0) ? $nbResults : 10;
        return $this;
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['nbResults'] = $this->getNbResults();
        return $array;
    }

    public function getSuppParams()
    {
        return serialize(array(
            'nbResults' => $this->getNbResults()
        ));
    }

} 
