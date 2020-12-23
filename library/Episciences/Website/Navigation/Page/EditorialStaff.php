<?php

/**
 * Class Episciences_Website_Navigation_Page_EditorialStaff
 */
class Episciences_Website_Navigation_Page_EditorialStaff extends Episciences_Website_Navigation_Page
{
    const PARAM_DISPLAY_PHOTOS = 'displayPhotos';
    /**
     * @const int
     */
    const DEFAULT_DISPLAY_PHOTOS = 1;
    /**
     * @var string
     */
    protected $_controller = 'review';
    /**
     * @var string
     */
    protected $_action = 'staff';

    /**
     * @var int
     */
    protected $_displayPhotos = self::DEFAULT_DISPLAY_PHOTOS;

    /**
     * @return $this|void|null
     */
    public function load()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from('WEBSITE_NAVIGATION', 'PARAMS')->where('SID = ?', RVID)->where('TYPE_PAGE = ?', __CLASS__);
        $settings = $db->fetchOne($sql);

        if ($settings) {
            $settings = unserialize($settings, ['allowed_classes' => false]);
            $this->setDisplayPhotos($settings[self::PARAM_DISPLAY_PHOTOS]);
            return $this;
        }
        return null;
    }

    /**
     * @param int $displayPhotos
     */
    public function setDisplayPhotos($displayPhotos = self::DEFAULT_DISPLAY_PHOTOS)
    {
        $this->_displayPhotos = (int)$displayPhotos;
    }

    /**
     * @param array $options
     */
    public function setOptions($options = [])
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods, true)) {
                $this->$method ($value);
            }
        }

        parent::setOptions($options);
    }

    /**
     * @param unknown_type $pageidx
     * @return Ccsd_Form|Zend_Form|null
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public function getForm($pageidx)
    {
        parent::getForm($pageidx);
        $this->_form->addElement('select', self::PARAM_DISPLAY_PHOTOS, [
            'label' => "Photos des membres",
            'multioptions' => [1 => 'Afficher', 0 => 'Masquer'],
            'value' => $this->isDisplayPhotos(),
            'belongsTo' => 'pages_' . $pageidx
        ]);

        return $this->_form;
    }

    /**
     * @return int
     */
    public function isDisplayPhotos(): int
    {
        if ($this->_displayPhotos === false) {
            $this->_displayPhotos = self::DEFAULT_DISPLAY_PHOTOS;
        }
        return $this->_displayPhotos;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array[self::PARAM_DISPLAY_PHOTOS] = $this->isDisplayPhotos();
        return $array;
    }

    /**
     * @return string
     */
    public function getSuppParams(): string
    {
        return serialize([
            self::PARAM_DISPLAY_PHOTOS => $this->isDisplayPhotos()
        ]);
    }

}