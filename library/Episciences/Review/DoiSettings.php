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

    const DOI_FORMAT_PAPER_VOLUME_INT = '%V_INT%';
    const DOI_FORMAT_PAPER_VOLUME_INT_REPLACEMENT_CHAR = '%V_INT[-]%';

    const DOI_FORMAT_PAPER_VOLUME_BIB_REF = '%V_BIB_REF%';

    const DOI_FORMAT_PAPER_SECTION_INT = '%S_INT%';
    const DOI_FORMAT_PAPER_SECTION_INT_REPLACEMENT_CHAR = '%S_INT[-]%';

    const DOI_FORMAT_PAPER_VOLUME_ORDER = '%VP%';
    const DOI_FORMAT_PAPER_ID = '%P%';
    const DOI_FORMAT_PAPER_YEAR = '%Y%';
    const DOI_FORMAT_PAPER_MONTH = '%M%';


    const DOI_ASSIGN_MODE_AUTO = 'automatic';
    const DOI_ASSIGN_MODE_MANUAL = 'manual';

    const DOI_DEFAULT_ASSIGN_MODE = self::DOI_ASSIGN_MODE_AUTO;

    const SETTING_DOI_PREFIX = 'doiPrefix';
    const SETTING_DOI_FORMAT = 'doiFormat';
    const SETTING_DOI_REGISTRATION_AGENCY = 'doiRegistrationAgency';
    const SETTING_DOI_ASSIGN_MODE = 'doiAssignMode';

    const SETTING_DOI_DEFAULT_REGISTRATION_AGENCY = 'crossref';
    const SETTING_DOI_DEFAULT_PREFIX = ''; // test prefix
    /**
     * DOI default format
     */
    const SETTING_DOI_DEFAULT_DOI_FORMAT =
        self::DOI_FORMAT_REVIEW_CODE
        . '-'
        . self::DOI_FORMAT_PAPER_ID;

    /**
     * @var array
     */
    protected static $_doiSettings = [
        self::SETTING_DOI_PREFIX,
        self::SETTING_DOI_FORMAT,
        self::SETTING_DOI_REGISTRATION_AGENCY,
        self::SETTING_DOI_ASSIGN_MODE];


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


    protected $_autoAssignDoi = self::SETTING_DOI_ASSIGN_MODE;


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

        // return an empty DOI if there's no prefix
        if ($this->getDoiPrefix() == '') {
            return '';
        }


        $volume = '';
        $volumeInt = '';
        $sectionInt = '';
        $paperPosition = '';
        $section = '';
        $refBibVolume = '';

        if ($paper->getVid()) {
            /* @var $oVolume Episciences_Volume */
            $oVolume = Episciences_VolumesManager::find($paper->getVid());
            if ($oVolume) {
                $volume = $oVolume->getName('en', true);
                $paperPosition = $paper->getPosition()+1; // Paper position starts at 0 ; +1 for the human version
                $refBibVolume = $oVolume->getBib_reference();
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

        $doiFormat = $this->getDoiFormat();
        $doi = $doiFormat;

        if ($volume !== '') {
            $hasVolumeReplacementChar = preg_match("/.*(%V_INT\[(.)\]%).*/", $doiFormat, $matchesVolumeReplacementChar);
            if ($hasVolumeReplacementChar) {
                $replacementChar = $matchesVolumeReplacementChar[2];
                $volumeIntWithChar = self::keepOnlyIntegersInTag($volume, $replacementChar);
                $doi = preg_replace("/%V_INT\[.\]%/", $volumeIntWithChar, $doiFormat);
            } else {
                $volumeInt = self::keepOnlyIntegersInTag($volume);
            }
        }

        if ($section !== '') {
            $hasSectionReplacementChar = preg_match("/(.*)(%S_INT\[(.)\]%)/", $doiFormat, $matchesSectionReplacementChar);
            if ($hasSectionReplacementChar) {
                $replacementChar = $matchesSectionReplacementChar[2];
                $sectionIntWithChar = self::keepOnlyIntegersInTag($section, $replacementChar);
                $doi = preg_replace("/%S_INT\[.\]%/", $sectionIntWithChar, $doiFormat);
            } else {
                $sectionInt = self::keepOnlyIntegersInTag($section);
            }
        }


        $template[self::DOI_FORMAT_REVIEW_CODE] = RVCODE;
        $template[self::DOI_FORMAT_PAPER_VOLUME] = $volume;
        $template[self::DOI_FORMAT_PAPER_VOLUME_INT] = $volumeInt;
        $template[self::DOI_FORMAT_PAPER_VOLUME_BIB_REF] = $refBibVolume;
        $template[self::DOI_FORMAT_PAPER_VOLUME_ORDER] = $paperPosition;
        $template[self::DOI_FORMAT_PAPER_SECTION] = $section;
        $template[self::DOI_FORMAT_PAPER_SECTION_INT] = $sectionInt;
        $template[self::DOI_FORMAT_PAPER_ID] = $paper->getPaperid();
        $template[self::DOI_FORMAT_PAPER_YEAR] = $paper->getPublicationYear();
        $template[self::DOI_FORMAT_PAPER_MONTH] = $paper->getPublicationMonth();


        $search = array_keys($template);
        $replace = array_values($template);


        $doi = str_replace($search, $replace, $doi);
        $doi = str_replace(' ', '', $doi);
        $doi = str_replace('..', '.', $doi);
        $doi = str_replace('--', '-', $doi);

        // DOI spec: DOI is case insensitive
        return $this->getDoiPrefix() . '/' . strtolower($doi);


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
     * @param string $tag
     * @param string $charUsedToReplace
     * @return string|string[]|null
     */
    private static function keepOnlyIntegersInTag(string $tag, string $charUsedToReplace = '.')
    {
        $strToReturn = trim($tag);
        $strToReturn = preg_replace('/\D+/', $charUsedToReplace, $strToReturn);
        $strToReturn = trim($strToReturn, $charUsedToReplace);
        return trim($strToReturn, '.');
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

    /**
     * @return string
     */
    public function getAutoAssignDoi(): string
    {
        return $this->_autoAssignDoi;
    }

    /**
     * @param string $autoAssignDoi
     */
    public function setAutoAssignDoi(string $autoAssignDoi = self::DOI_ASSIGN_MODE_AUTO): void
    {
        $this->_autoAssignDoi = $autoAssignDoi;
    }


}