<?php

/**
 * Class Episciences_Review_Doi
 * DOI settings of a Journal|Review
 */
class Episciences_Review_DoiSettings
{

    const DOI_FORMAT_REVIEW_CODE = '%R%';
    const DOI_FORMAT_PAPER_VOLUME = '%V%';
    const DOI_FORMAT_PAPER_SECTION = '%S%';
    const DOI_FORMAT_PAPER_VOLUME_ORDER = '%VP%';
    const DOI_FORMAT_PAPER_ID = '%P%';
    const DOI_FORMAT_PAPER_YEAR = '%Y%';
    const DOI_FORMAT_PAPER_MONTH = '%M%';
    const SETTING_DOI_PREFIX = 'doiPrefix';
    const SETTING_DOI_FORMAT = 'doiFormat';
    const SETTING_DOI_REGISTRATION_AGENCY = 'doiRegistrationAgency';

    const SETTING_DOI_DEFAULT_REGISTRATION_AGENCY = 'crossref';
    const SETTING_DOI_DEFAULT_PREFIX = ''; // test prefix
    /**
     * DOI default format
     */
    const SETTING_DOI_DEFAULT_DOI_FORMAT =
        self::DOI_FORMAT_REVIEW_CODE
        . '-'
        . self::DOI_FORMAT_PAPER_VOLUME
        . '-'
        . self::DOI_FORMAT_PAPER_SECTION
        . '('
        . self::DOI_FORMAT_PAPER_ID
        . ')'
        . self::DOI_FORMAT_PAPER_YEAR;
    /**
     * @var array
     */
    protected static $_doiSettings = [self::SETTING_DOI_PREFIX,
        self::SETTING_DOI_FORMAT,
        self::SETTING_DOI_REGISTRATION_AGENCY];


    /**
     * @var string
     */
    protected $_doiFormat = self::SETTING_DOI_DEFAULT_DOI_FORMAT;
    /**
     * @var string
     */
    protected $_doiPrefix = self::SETTING_DOI_DEFAULT_PREFIX;
    /**
     * @var string
     */
    protected $_doiRegistrationAgency = self::SETTING_DOI_DEFAULT_REGISTRATION_AGENCY;


    public function __construct($options = [])
    {
        if (count($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): Episciences_Review_DoiSettings
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }
        return $this;
    }

    /**
     * @return array
     */
    public static function getDoiSettings(): array
    {
        return self::$_doiSettings;
    }

    /**
     * Episciences_Review_DoiSettings to Array
     * @return array
     */
    public function __toArray(): array
    {
        $doiAsArray = [];
        $classMethods = get_class_methods($this);
        foreach (self::$_doiSettings as $doiSettingName) {
            $method = 'get' . ucfirst($doiSettingName);
            if (in_array($method, $classMethods)) {
                $doiAsArray[$doiSettingName] = $this->$method();
            }
        }
        return $doiAsArray;

    }

    /**
     * @param Episciences_Paper $paper
     * @return string
     * @throws Zend_Exception
     */
    public function createDoiWithTemplate(Episciences_Paper $paper): string
    {
        $volume = '';
        $paperPosition = '';
        $section = '';

        if ($paper->getVid()) {
            /* @var $oVolume Episciences_Volume */
            $oVolume = Episciences_VolumesManager::find($paper->getVid());
            if ($oVolume) {
                $volume = $oVolume->getName('en', true);
                $paperPosition = $paper->getPosition();
            }
        }


        if ($paper->getSid()) {
            /* @var $oSection Episciences_Section */
            $oSection = Episciences_SectionsManager::find($paper->getSid());
            if ($oSection) {
                $section = $oSection->getName('en', true);
            } else {
                $section = '';
            }
        }

        $template['%%'] = '%';

        $template[self::DOI_FORMAT_REVIEW_CODE] = RVCODE;
        $template[self::DOI_FORMAT_PAPER_VOLUME] = $volume;
        $template[self::DOI_FORMAT_PAPER_VOLUME_ORDER] = $paperPosition;
        $template[self::DOI_FORMAT_PAPER_SECTION] = $section;
        $template[self::DOI_FORMAT_PAPER_ID] = $paper->getPaperid();
        $template[self::DOI_FORMAT_PAPER_YEAR] = $paper->getPublicationYear();
        $template[self::DOI_FORMAT_PAPER_MONTH] = $paper->getPublicationMonth();


        $search = array_keys($template);
        $replace = array_values($template);

        $doi = str_replace(' ',  '', $this->getDoiFormat());
        $doi = str_replace($search, $replace, $doi);
        $doi = str_replace(' ',  '', $doi);

        // DOI spec: DOI is case insensitive
        return $this->getDoiPrefix() . '/' . strtolower($doi);


    }

    /**
     * @return string
     */
    public function getDoiFormat(): string
    {
        return $this->_doiFormat;
    }

    /**
     * @param string $doiFormat
     */
    public function setDoiFormat(string $doiFormat)
    {
        $this->_doiFormat = $doiFormat;
    }

    /**
     * @return string
     */
    public function getDoiPrefix(): string
    {
        return $this->_doiPrefix;
    }

    /**
     * @param string $doiPrefix
     */
    public function setDoiPrefix(string $doiPrefix)
    {
        $this->_doiPrefix = $doiPrefix;
    }


    /**
     * @return string
     */
    public function getDoiRegistrationAgency(): string
    {
        return $this->_doiRegistrationAgency;
    }

    /**
     * @param string $doiRegistrationAgency
     */
    public function setDoiRegistrationAgency(string $doiRegistrationAgency)
    {
        $this->_doiRegistrationAgency = $doiRegistrationAgency;
    }


}