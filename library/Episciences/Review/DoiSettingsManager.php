<?php


class Episciences_Review_DoiSettingsManager
{
    /**
     * A DOI prefix: "10." followed by the registrant code (digits, possibly with dot-separated subdivisions)
     */
    public const DOI_PREFIX_PATTERN = '/^10\.\d{4,}(\.\d+)*$/';

    /**
     * Form settings for journal DOI
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getSettingsForm()
    {

        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->setAttrib('id', 'doi-settings-form');
        $form->setAttrib('data-unsaved-warning', '1');
        $form->getDecorator('FormRequired')->setOption('style', 'float: none;');


        $fieldDecorators = [
            ['ViewHelper', ['class' => 'form-control input-sm']],
            ['Description', ['tag' => 'p', 'class' => 'help-block', 'placement' => 'APPEND', 'escape' => false]],
            ['Errors', ['placement' => 'APPEND']],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9']],
            ['Label', ['tag' => 'label', 'class' => 'col-md-3 control-label']],
        ];

        // DOI RA
        $form->addElement('select', Episciences_Review_DoiSettings::SETTING_DOI_REGISTRATION_AGENCY, [
            'label' => "Agence d'enregistrement pour les DOI",
            'multioptions' => [
                'crossref' => "Crossref"
            ],
            'decorators' => $fieldDecorators,
        ]);

        $form->addElement('select', Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE, [
            'label' => "Assignation automatique des DOI",
            'description' => "<strong>Automatique</strong> : le DOI est créé à l'acceptation de l'article. <strong>Manuel</strong> : la revue le demande depuis la page de l'article (bouton « Demander un DOI »). <strong>Désactivé</strong> : aucun DOI n'est créé à partir du format, la revue saisit le DOI de chaque article.",
            'multioptions' => [
                Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_AUTO => "Automatique",
                Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL => "Manuel",
                Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_DISABLED => "Désactivé"
            ],
            'decorators' => $fieldDecorators,
        ]);

        $form->addElement('text', Episciences_Review_DoiSettings::SETTING_DOI_PREFIX, [
            'label' => "Préfixe DOI",
            'description' => 'Préfixe attribué à la revue par l\'agence d\'enregistrement. Un préfixe DOI commence toujours par "10." et se poursuit par un nombre. Sans préfixe, aucun DOI ne peut être créé.',
            'placeholder' => '10.12345',
            'filters' => ['StringTrim'],
            'validators' => [
                (new Zend_Validate_Regex(self::DOI_PREFIX_PATTERN))
                    ->setMessage('Un préfixe DOI commence toujours par "10." et se poursuit par un nombre.', Zend_Validate_Regex::NOT_MATCH),
            ],
            'required' => false,
            'decorators' => $fieldDecorators,
        ]);

        $form->addElement('text', Episciences_Review_DoiSettings::SETTING_DOI_FORMAT, [
            'label' => "Format du DOI",
            'filters' => ['StringTrim'],
            'validators' => [
                new Zend_Validate_StringLength(['max' => 255]),
            ],
            'required' => true,
            'decorators' => [['ViewScript', ['viewScript' => '/doi/doi_format.phtml']]],
        ]);

        // display group : DOI
        $form->addDisplayGroup([
            Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE,
            Episciences_Review_DoiSettings::SETTING_DOI_REGISTRATION_AGENCY,
            Episciences_Review_DoiSettings::SETTING_DOI_PREFIX,
            Episciences_Review_DoiSettings::SETTING_DOI_FORMAT
        ], 'doi', ["legend" => "Paramètres pour l'assignation de DOI"]);
        $form->getDisplayGroup('doi')->setDecorators(['FormElements', 'Fieldset']);
        $form->getDisplayGroup('doi')->removeDecorator('DtDdWrapper');

        // submit button
        $form->setActions(true)->createSubmitButton('submit', [
            'label' => 'Enregistrer les paramètres',
            'class' => 'btn btn-primary'
        ]);
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
        $sql .= ' AS new_row ON DUPLICATE KEY UPDATE VALUE = new_row.VALUE';

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
        $select = Zend_Db_Table_Abstract::getDefaultAdapter()
            ->select()
            ->from(T_REVIEW_SETTINGS)
            ->where('RVID = ' . $rvid);

        $journalDoiSettings = [];
        foreach (Zend_Db_Table_Abstract::getDefaultAdapter()->fetchAll($select) as $row) {
            if (in_array($row['SETTING'], Episciences_Review_DoiSettings::getDoiSettings())) {
                $journalDoiSettings[$row['SETTING']] = $row['VALUE'];
            }
        }

        return new Episciences_Review_DoiSettings($journalDoiSettings);
    }
}
