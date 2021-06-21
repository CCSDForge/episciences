<?php


class Episciences_Review_DoiSettingsManager
{

    /**
     * Form settings for journal DOI
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getSettingsForm()
    {

        /** @var Ccsd_Form $form */
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->getDecorator('FormRequired')->setOption('style', 'float: none;');


        $translator = Zend_Registry::get('Zend_Translate');

        // DOI RA
        $form->addElement('select', Episciences_Review_DoiSettings::SETTING_DOI_REGISTRATION_AGENCY, [
            'label' => "Agence d'enregistrement pour les DOI",
            'style' => 'width: auto;',
            'multioptions' => [
                'crossref' => "Crossref",
                'datacite' => "DataCite"

            ]]);

        $form->addElement('select', Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE, [
            'label' => "Assignation automatique des DOI",
            'style' => 'width: auto;',
            'multioptions' => [
                'true' => "Automatique",
                'false' => "Manuel"
            ]]);


        $tooltipMsg = $translator->translate("Préfixe pour l'assignation de DOI");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = Zend_Registry::get('Zend_Translate')->translate("Préfixe DOI");
        $form->addElement('text', Episciences_Review_DoiSettings::SETTING_DOI_PREFIX, [
            'label' => $tooltip . $label,
            'description' => $translator->translate('Un préfixe DOI commence toujours par "10." et se poursuit par un nombre.'),
            'placeholder' => $translator->translate('Par exemple') . ' 10.12345',
            'style' => 'width: 200px',
            'required' => false
        ]);

        $tooltipMsg = $translator->translate("Modèle de format pour la création de DOI");
        $tooltip = '<span class="lightgrey glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="bottom" title="' . $tooltipMsg . '"></span> ';
        $label = Zend_Registry::get('Zend_Translate')->translate("Format du DOI");
        $form->addElement('text', Episciences_Review_DoiSettings::SETTING_DOI_FORMAT, [
            'label' => $tooltip . $label,
            'style' => 'width: 300px',
            'required' => false,
            'decorators' => [['ViewScript', ['viewScript' => '/doi/doi_format.phtml']]],
        ]);

        // display group : DOI
        $form->addDisplayGroup([
            Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE,
            Episciences_Review_DoiSettings::SETTING_DOI_REGISTRATION_AGENCY,
            Episciences_Review_DoiSettings::SETTING_DOI_PREFIX,
            Episciences_Review_DoiSettings::SETTING_DOI_FORMAT
        ], 'doi', ["legend" => "Paramètres pour l'assignation de DOI"]);
        $form->getDisplayGroup('doi')->removeDecorator('DtDdWrapper');

        // submit button
        $form->setActions(true)->createSubmitButton('submit', [
                'label' => 'Enregistrer les paramètres',
                'class' => 'btn btn-primary'
            ]
        );
        return $form;
    }

    /**
     * @param Episciences_Review_DoiSettings $doiSettings
     * @param int $rvid
     * @return bool
     */
    public static function save(Episciences_Review_DoiSettings $doiSettings, int $rvid): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $settingsValues = $doiSettings->__toArray();
        $values = [];


        foreach ($settingsValues as $setting => $value) {
            $setting = $db->quote($setting);
            if (is_array($value) && !empty($value)) {
                $value = Zend_Json::encode($value);
            }
            $value = $db->quote($value);
            $values[] = '(' . $rvid . ',' . $setting . ',' . $value . ')';
        }

        $sql = 'INSERT INTO ';
        $sql .= T_REVIEW_SETTINGS;
        $sql .= ' (RVID, SETTING, VALUE) VALUES ';
        $sql .= implode(',', $values);
        $sql .= ' ON DUPLICATE KEY UPDATE VALUE = VALUES(VALUE)';

        if (!$db->getConnection()->query($sql)) {
            return false;
        }
        return true;
    }

    /**
     * Find review DOI settings
     * @param int $rvid
     * @return Episciences_Review_DoiSettings
     */
    public static function findByJournal(int $rvid)
    {
        // review configuration
        $select = Zend_Db_Table_Abstract::getDefaultAdapter()->select()->from(T_REVIEW_SETTINGS)->where('RVID = ' . $rvid);

        $journalDoiSettings = [];
        foreach (Zend_Db_Table_Abstract::getDefaultAdapter()->fetchAll($select) as $row) {
            if (in_array($row['SETTING'], Episciences_Review_DoiSettings::getDoiSettings(), false)) {
                $journalDoiSettings[$row['SETTING']] = $row['VALUE'];
            }
        }

        return new Episciences_Review_DoiSettings($journalDoiSettings);

    }

}