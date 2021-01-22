<?php

/**
 * Class Episciences_Review_Doi
 * DOI settings of a Journal|Review
 */
class Episciences_Review_Doi
{
    const DOI_FORMAT_REVIEW_CODE = '%R%';
    const DOI_FORMAT_PAPER_VOLUME = '%V%';
    const DOI_FORMAT_PAPER_SECTION = '%S%';
    const DOI_FORMAT_PAPER_VOLUME_ORDER = '%VP%';
    /**
     * @const int
     */
    const DOI_FORMAT_PAPER_ID = '%P%';
    const DOI_FORMAT_PAPER_YEAR = '%Y%';
    const DOI_FORMAT_PAPER_MONTH = '%M%';
    const SETTING_DOI_PREFIX = 'doiPrefix';
    const SETTING_DOI_FORMAT = 'doiFormat';
    const SETTING_DOI_REGISTRATION_AGENCY = 'doiRegistrationAgency';

    const SETTING_DOI_DEFAULT_REGISTRATION_AGENCY = 'datacite'; //datacite is default
    const SETTING_DOI_DEFAULT_PREFIX = ''; //datacite test prefix
    /**
     * DOI default format
     */
    const SETTING_DOI_DEFAULT_DOI_FORMAT =
        self::DOI_FORMAT_REVIEW_CODE
        . '-'
        . self::DOI_FORMAT_PAPER_YEAR
        . '-'
        . self::DOI_FORMAT_PAPER_ID;


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

    /**
     * Episciences_Review_Doi constructor.
     * @param $options
     */


    /**
     * Episciences_Review_Doi constructor.
     * @param string $options
     */
    public function __construct($options = [])
    {
        if (count($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array $options
     * @return Episciences_Review_Doi
     */
    public function setOptions(array $options): Episciences_Review_Doi
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
     * Episciences_Review_Doi to Array
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
     * Form settings for journal DOI
     * @param Ccsd_Form $form
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function getSettingsForm(Ccsd_Form $form)
    {
        $translator = Zend_Registry::get('Zend_Translate');

        // DOI RA
        $form->addElement('select', self::SETTING_DOI_REGISTRATION_AGENCY, [
            'label' => "Agence d'enregistrement pour les DOI",
            'style' => 'width: auto;',
            'multioptions' => [
                'datacite' => "DataCite https://datacite.org/",
                //    'crossref' => "CrossRef https://www.crossref.org/"
            ]]);


        $tooltipMsg = $translator->translate("Préfixe pour l'assignation de DOI");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = Zend_Registry::get('Zend_Translate')->translate("Préfixe DOI");
        $form->addElement('text', self::SETTING_DOI_PREFIX, [
            'label' => $tooltip . $label,
            'description' => $translator->translate('Un préfixe DOI commence toujours par "10." et se poursuit par un nombre.'),
            'placeholder' => $translator->translate('Par exemple') . ' 10.12345',
            'style' => 'width: 200px',
            'required' => false
        ]);

        $tooltipMsg = $translator->translate("Modèle de format pour la création de DOI");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = Zend_Registry::get('Zend_Translate')->translate("Format du DOI");
        $form->addElement('text', self::SETTING_DOI_FORMAT, [
            'label' => $tooltip . $label,
            'style' => 'width: 300px',
            'required' => false,
            'decorators' => [['ViewScript', ['viewScript' => '/review/doi_format.phtml']]],
        ]);

        // display group : DOI
        $form->addDisplayGroup([
            self::SETTING_DOI_REGISTRATION_AGENCY,
            self::SETTING_DOI_PREFIX,
            self::SETTING_DOI_FORMAT
        ], 'doi', ["legend" => "Paramètres pour l'assignation de DOI"]);
        $form->getDisplayGroup('doi')->removeDecorator('DtDdWrapper');


        return $form;
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
