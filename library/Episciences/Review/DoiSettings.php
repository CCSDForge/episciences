<?php

/**
 * Class Episciences_Review_Doi
 * DOI settings of a Journal|Review
 */
class Episciences_Review_DoiSettings
{
    public const DOI_FORMAT_REVIEW_CODE = '%R%';
    public const DOI_FORMAT_PAPER_VOLUME = '%V%';
    public const DOI_FORMAT_PAPER_SECTION = '%S%';

    public const DOI_FORMAT_PAPER_VOLUME_INT = '%V_INT%';
    public const DOI_FORMAT_PAPER_VOLUME_INT_REPLACEMENT_CHAR = '%V_INT[-]%';

    public const DOI_FORMAT_PAPER_VOLUME_BIB_REF = '%V_BIB_REF%';

    public const DOI_FORMAT_PAPER_SECTION_INT = '%S_INT%';
    public const DOI_FORMAT_PAPER_SECTION_INT_REPLACEMENT_CHAR = '%S_INT[-]%';

    public const DOI_FORMAT_PAPER_VOLUME_ORDER = '%VP%';
    public const DOI_FORMAT_PAPER_ID = '%P%';
    public const DOI_FORMAT_PAPER_YEAR = '%Y%';
    public const DOI_FORMAT_PAPER_YEAR_SHORT = '%y%';
    public const DOI_FORMAT_PAPER_MONTH = '%M%';

    public const DOI_FORMAT_PAPER_AUTOINCREMENT = '%AUTOINCREMENT%';

    public const DOI_ASSIGN_MODE_AUTO = 'automatic';
    public const DOI_ASSIGN_MODE_MANUAL = 'manual';
    public const DOI_ASSIGN_MODE_DISABLED = 'disabled';


    public const SETTING_DOI_PREFIX = 'doiPrefix';
    public const SETTING_DOI_FORMAT = 'doiFormat';
    public const SETTING_DOI_REGISTRATION_AGENCY = 'doiRegistrationAgency';
    public const SETTING_DOI_ASSIGN_MODE = 'doiAssignMode';


    public const DOI_DEFAULT_ASSIGN_MODE = self::DOI_ASSIGN_MODE_AUTO;
    public const SETTING_DOI_DEFAULT_REGISTRATION_AGENCY = 'crossref';
    public const SETTING_DOI_DEFAULT_PREFIX = ''; // test prefix
    /**
     * DOI default format
     */
    public const SETTING_DOI_DEFAULT_DOI_FORMAT =
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

    /**
     * @var string
     */
    protected $_doiAssignMode = self::DOI_DEFAULT_ASSIGN_MODE;


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
    public function createDoiWithTemplate(Episciences_Paper $paper, string $rvCode = null): string
    {

        if (!$rvCode && !Ccsd_Tools::isFromCli()){
            $rvCode = RVCODE;
        }

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
                $volume = $oVolume->getName('en');
                $paperPosition = $paper->getPosition() + 1; // Paper position starts at 0 ; +1 for the human version
                $refBibVolume = $oVolume->getBib_reference();
            }
        }


        if ($paper->getSid()) {
            /* @var $oSection Episciences_Section */
            $oSection = Episciences_SectionsManager::find($paper->getSid());
            if ($oSection) {
                $section = $oSection->getName('en', true);
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
        } else {
            $doi = preg_replace("/%V_INT\[.\]%/", '', $doi); //remove empty tag
        }

        if ($section !== '') {
            $hasSectionReplacementChar = preg_match("/(.*)(%S_INT\[(.)\]%)/", $doiFormat, $matchesSectionReplacementChar);
            if ($hasSectionReplacementChar) {
                $replacementChar = $matchesSectionReplacementChar[2];
                $sectionIntWithChar = self::keepOnlyIntegersInTag($section, $replacementChar);
                $doi = preg_replace("/%S_INT\[.\]%/", $sectionIntWithChar, $doi);
            } else {
                $sectionInt = self::keepOnlyIntegersInTag($section);
            }
        } else {
            $doi = preg_replace("/%S_INT\[.\]%/", '', $doi); //remove empty tag
        }


        $template[self::DOI_FORMAT_REVIEW_CODE] = $rvCode;
        $template[self::DOI_FORMAT_PAPER_VOLUME] = $volume;
        $template[self::DOI_FORMAT_PAPER_VOLUME_INT] = $volumeInt;
        $template[self::DOI_FORMAT_PAPER_VOLUME_BIB_REF] = $refBibVolume;
        $template[self::DOI_FORMAT_PAPER_VOLUME_ORDER] = $paperPosition;
        $template[self::DOI_FORMAT_PAPER_SECTION] = $section;
        $template[self::DOI_FORMAT_PAPER_SECTION_INT] = $sectionInt;
        $template[self::DOI_FORMAT_PAPER_ID] = $paper->getPaperid();
        $template[self::DOI_FORMAT_PAPER_YEAR] = $paper->getPublicationYear();
        $template[self::DOI_FORMAT_PAPER_YEAR_SHORT] = $paper->getPublicationYear('y');
        $template[self::DOI_FORMAT_PAPER_MONTH] = $paper->getPublicationMonth();


        $search = array_keys($template);
        $replace = array_values($template);
        $doi = str_replace($search, $replace, $doi);


        $hasAutoIncrementReplacementChar = preg_match("/(.*)(" . self::DOI_FORMAT_PAPER_AUTOINCREMENT . ")/", $doiFormat, $matchesAutoIncrementReplacementChar);

        if ($hasAutoIncrementReplacementChar) {
            $autoIncrementValue = $this->computeNextDoiAutoIncrement($doi, $paper->getRvid());
            $template[self::DOI_FORMAT_PAPER_AUTOINCREMENT] = $autoIncrementValue;
        $search = array_keys($template);
        $replace = array_values($template);

        $doi = str_replace($search, $replace, $doi);
        }

        $doi = str_replace([' ', '..', '--'], ['', '.', '-'], $doi);

        // DOI spec: DOI is case-insensitive
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
    public function setDoiPrefix(string $doiPrefix): void
    {
        $this->_doiPrefix = $doiPrefix;
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
    public function setDoiFormat(string $doiFormat): void
    {
        $this->_doiFormat = $doiFormat;
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
    public function getDoiRegistrationAgency(): string
    {
        return $this->_doiRegistrationAgency;
    }

    /**
     * @param string $doiRegistrationAgency
     */
    public function setDoiRegistrationAgency(string $doiRegistrationAgency): void
    {
        $this->_doiRegistrationAgency = $doiRegistrationAgency;
    }

    /**
     * @return string
     */
    public function getDoiAssignMode(): string
    {
        return $this->_doiAssignMode;
    }

    /**
     * @param string $autoAssignDoi
     */
    public function setDoiAssignMode(string $autoAssignDoi = self::DOI_DEFAULT_ASSIGN_MODE): void
    {
        $this->_doiAssignMode = $autoAssignDoi;
    }
    /**
     * Get the latest DOI of a journal, according to the journal's pattern
     * @param int $rvid
     * @param string $doiPattern
     * @return string
     */
    public static function getNextDoiAutoincrement(int $rvid, string $doiPattern): string
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->distinct('DOI')
            ->from('PAPERS', ['DOI', 'PAPERID'])
            ->where('DOI LIKE ?', $doiPattern . '%')
            ->where('RVID LIKE ?', $rvid)
            ->order('CHAR_LENGTH(DOI) DESC') // RT#211209: Select STRCMP('10.46298/theoretics.24.10', '10.46298/theoretics.24.1') As 'str_cmp' : Les deux chaînes sont identiques. ORDER BY n'a donc pas fonctionné correctement.
            ->order('DOI DESC')
            ->limit(1);

        $res = $select->query()->fetchAll(Zend_Db::FETCH_COLUMN);

        if ($res) {
            return (string) $res[0];
        }

        return '';
    }

    /**
     * @param $doi
     * @param int $rvid
     * @return int
     */
    private function computeNextDoiAutoIncrement($doi, int $rvid): int
    {

        $doiWihtoutAutoIncrementString = str_replace(self::DOI_FORMAT_PAPER_AUTOINCREMENT, '', $doi);
        $templateAutoIncrementSeparatorChar = substr($doiWihtoutAutoIncrementString, -1);

        $doiPattern = sprintf("%s/%s", $this->getDoiPrefix(), $doiWihtoutAutoIncrementString);
        $latestDoi = self::getNextDoiAutoincrement($rvid, $doiPattern);

        if ($latestDoi === '') {
            // no previous DOI start at 1
            return 1;
        }

        $autoIncrementValueArray = explode($templateAutoIncrementSeparatorChar, $latestDoi);
        if (is_array($autoIncrementValueArray)) {
            $autoIncrementValue = end($autoIncrementValueArray) + 1;
        } else {
            $autoIncrementValue = 1;
        }

        return (int) $autoIncrementValue;
    }
}
