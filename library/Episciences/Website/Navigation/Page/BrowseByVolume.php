<?php

class Episciences_Website_Navigation_Page_BrowseByVolume extends Episciences_Website_Navigation_Page
{
    public const SETTING_DISPLAY_EMPTY_VOLUMES = 'displayEmptyVolumes';
    public const DEFAULT_DISPLAY_EMPTY_VOLUMES = 0;

    public const DISPLAY_EMPTY_VOLUMES = 1;
    public const HIDE_EMPTY_VOLUMES = 0;

    protected $_controller = 'browse';
    protected $_action = 'volumes';

    protected $_nbResults;
    /**
     * @var int Display empty volumes in the volume list 0 || 1
     */
    protected int $displayEmptyVolumes = self::DEFAULT_DISPLAY_EMPTY_VOLUMES;

    public function setOptions($options = [])
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
        parent::load();

        $settings = Episciences_Website_Navigation_NavigationManager::fetchByClassName(__CLASS__);

        if ($settings) {
            $settings = unserialize($settings, ['allowed_classes' => false]);
            $this->setNbResults($settings['nbResults']);
            if (empty($settings[self::SETTING_DISPLAY_EMPTY_VOLUMES])) {
                $settings[self::SETTING_DISPLAY_EMPTY_VOLUMES] = self::DEFAULT_DISPLAY_EMPTY_VOLUMES;
            }

            $this->setDisplayEmptyVolumes((int)$settings[self::SETTING_DISPLAY_EMPTY_VOLUMES]);

            return $this;
        }

        return null;
    }

    /**
     * @param int $displayEmptyVolumes
     */
    public function setDisplayEmptyVolumes(int $displayEmptyVolumes = 0): void
    {
        if ($displayEmptyVolumes !== self::HIDE_EMPTY_VOLUMES && $displayEmptyVolumes !== self::DISPLAY_EMPTY_VOLUMES) {
            $this->displayEmptyVolumes = self::DEFAULT_DISPLAY_EMPTY_VOLUMES;
        } else {
            $this->displayEmptyVolumes = $displayEmptyVolumes;
        }
    }

    public function getForm($pageidx)
    {
        parent::getForm($pageidx);

        $this->_form->addElement('select', 'nbResults', [
            'label' => "Nombre de résultats par page",
            'multioptions' => ['5' => '5', '10' => '10', '15' => '15', '20' => '20', '25' => '25'],
            'value' => $this->getNbResults(),
            'belongsTo' => 'pages_' . $pageidx
        ]);
        $this->_form->addElement('select', self::SETTING_DISPLAY_EMPTY_VOLUMES, [
            'label' => "Afficher les volumes vides",
            'multioptions' => [self::DISPLAY_EMPTY_VOLUMES => 'Afficher', self::HIDE_EMPTY_VOLUMES => 'Masquer'],
            'value' => $this->isDisplayEmptyVolumes(),
            'belongsTo' => 'pages_' . $pageidx
        ]);

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

    /**
     * @return int
     */
    public function isDisplayEmptyVolumes(): int
    {
        return $this->displayEmptyVolumes;
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['nbResults'] = $this->getNbResults();
        $array[self::SETTING_DISPLAY_EMPTY_VOLUMES] = $this->isDisplayEmptyVolumes();
        return $array;
    }

    public function getSuppParams()
    {
        return serialize([
            'nbResults' => $this->getNbResults(),
            self::SETTING_DISPLAY_EMPTY_VOLUMES => $this->isDisplayEmptyVolumes()
        ]);
    }

}
