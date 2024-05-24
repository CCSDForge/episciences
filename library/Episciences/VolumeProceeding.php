<?php

class Episciences_VolumeProceeding
{
    private $_vid;
    private $_settings = [];

    private $_value = '';



    /**
     * Episciences_Volume constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods, true)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * @param $setting
     * @return bool|mixed
     */
    public function getSetting($setting)
    {
        $settings = $this->getSettings();

        if (array_key_exists($setting, $settings)) {
            return $settings[$setting];
        }

        return false;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->_settings;
    }

    /**
     * @param $settings
     * @return $this
     */
    public function setSettings($settings): self
    {
        $this->_settings = $settings;
        return $this;
    }

    /**
     * @param $setting
     * @param $value
     * @return $this
     */
    public function setSetting($setting, $value): self
    {
        $this->_settings[$setting] = (string)$value;
        return $this;
    }

    /**
     * @param $setting
     * @param $value
     * @param int $vid
     * @param bool $update
     */
    public function saveVolumeProceeding($setting, $value, int $vid, bool $update = false)
    {
        try {
            if (!$update) {
                $this->_db->insert(T_VOLUME_PROCEEDING, ['SETTING' => $setting, 'VALUE' => $value, 'VID' => $vid]);
            } else {
                $sql = $this->_db->quoteInto('INSERT INTO ' . T_VOLUME_PROCEEDING . ' (SETTING, VALUE, VID) VALUES (?) 
                ON DUPLICATE KEY UPDATE VALUE = VALUES(VALUE)', ['SETTING' => $setting, 'VALUE' => $value, 'VID' => $vid]);
                $this->_db->query($sql);
            }
        } catch (Zend_Db_Adapter_Exception $exception) {
            trigger_error(sprintf($exception->getMessage(), E_USER_WARNING));
        }
    }

    public function saveVolumeArrayProceeding(array $settings, int $vid, bool $update = false)
    {
        foreach ($settings as $setting => $value) {
            $this->saveVolumeProceeding($setting, $value, $vid, $update);
        }
    }

}
