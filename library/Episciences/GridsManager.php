<?php

// rating grids manager
class Episciences_GridsManager
{
    /** Prefix of the key of the grid of a review request type (#319): file grid_t<type id>.xml */
    public const REQUEST_TYPE_KEY_PREFIX = 't';

    /**
     * Key of a grid from a request: a volume id (0: default grid) or "t<request type id>"; null for anything else
     */
    public static function gridKey(mixed $raw): ?string
    {
        if (is_int($raw)) {
            $raw = (string)$raw;
        }

        return is_string($raw) && preg_match('/^t?\d{1,10}$/', $raw) === 1 ? $raw : null;
    }

    public static function requestTypeGridKey(int $requestTypeId): string
    {
        return self::REQUEST_TYPE_KEY_PREFIX . $requestTypeId;
    }

    /**
     * Request type id of a grid key, null for a volume or the default grid
     */
    public static function requestTypeIdOfKey(string $key): ?int
    {
        return str_starts_with($key, self::REQUEST_TYPE_KEY_PREFIX) ? (int)substr($key, strlen(self::REQUEST_TYPE_KEY_PREFIX)) : null;
    }

    public static function filenameOf(string $key): string
    {
        return 'grid_' . $key . '.xml';
    }

    /**
     * Path of the grid of a review request type, null when the journal has not created it
     */
    public static function requestTypeGridPath(int $requestTypeId): ?string
    {
        $filename = self::filenameOf(self::requestTypeGridKey($requestTypeId));

        return self::gridExists($filename) ? REVIEW_GRIDS_PATH . $filename : null;
    }

    /**
     * returns a list of all the rating grids
     * @return array
     */
    public static function getList()
    {

        $grids = [];

        // create review grid path if needed
        if (!is_dir(REVIEW_GRIDS_PATH)) {
            mkdir(REVIEW_GRIDS_PATH);
        }

        // strip .. + .
        $files = array_diff(scandir(REVIEW_GRIDS_PATH), ['..', '.']);

        // no rating grids: copy default grid
        if (!$files) {
            // be sure to not overwrite existing $destinationFile
            $destinationFile = REVIEW_GRIDS_PATH . REVIEW_GRID_NAME_DEFAULT;
            if (!is_readable($destinationFile)) {
                copy(REVIEW_PATH_DEFAULT . REVIEW_GRID_NAME_DEFAULT, $destinationFile);
            }
            $files = scandir(REVIEW_GRIDS_PATH);
        }

        foreach ($files as $file) {
            $fileinfo = pathinfo(REVIEW_GRIDS_PATH . $file);
            if ($fileinfo['extension'] != 'xml') {
                continue;
            }
            $oGrid = new Episciences_Rating_Grid();
            $oGrid->loadXML(REVIEW_GRIDS_PATH . $file);
            $grids[$fileinfo['filename']] = $oGrid;
        }
        return $grids;
    }

    /**
     * grid creation form (volume list)
     * @param $grids
     * @return bool|Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getGridForm($grids)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $vids = [];
        foreach ($grids as $grid) {
            // the grids of the request types are not volume grids (#319)
            if (self::requestTypeIdOfKey((string)$grid->getId()) === null) {
                $vids[] = $grid->getId();
            }
        }

        if (!empty($vids)) {
            $select = $db->select()
                ->from(T_VOLUMES, 'VID')
                ->where('VID NOT IN (?)', $vids)
                ->where('RVID = ?', RVID)
                ->order('POSITION', 'ASC');

            $vids = $db->fetchCol($select);

            $options = [];
            foreach ($vids as $vid) { //#542
                $options[$vid] =   Episciences_VolumesManager::translateVolumeKey('volume_' . $vid . '_title');

            }

            if (!empty($options)) {

                $form = new Ccsd_Form();
                $form->setAction('/grid/add');
                $form->setAttrib('class', 'form-horizontal');

                $form->addElement('select', 'volume', [
                    'label' => 'Créer une grille pour le volume :',
                    'multioptions' => $options,
                    'style' => 'width:300px'
                ]);

                Episciences_Csrf_Helper::addSessionTokenElement($form);

                $form->setActions(true)->createSubmitButton('submit', [
                    'label' => 'Créer la grille',
                    'class' => 'btn btn-primary'
                ]);

                return $form;
            }
        }

        return false;
    }

    /**
     * check if a grid exists, based on file path
     * @param $filename
     * @return bool
     */
    public static function gridExists($filename)
    {
        return file_exists(REVIEW_GRIDS_PATH . $filename);
    }

    /**
     * delete a grid
     * @param $filename
     * @return bool
     */
    public static function delete($filename)
    {
        return unlink(REVIEW_GRIDS_PATH . $filename);
    }

    /**
     * update rating criterion positions
     * @param $params
     * @return bool
     */
    public static function sortCriterion($params)
    {
        $key = self::gridKey($params['rgid'] ?? null);
        $grid = new Episciences_Rating_Grid;
        if ($key === null || !$grid->loadXML(REVIEW_GRIDS_PATH . self::filenameOf($key))) {
            return false;
        }

        $criteria = [];
        foreach ($params['sorted'] as $i => $item) {
            preg_match("#grid_(.*)_criterion_(.*)#", $item, $matches);
            if (empty($matches)) {
                continue;
            }
            $id = filter_var($matches[2], FILTER_SANITIZE_NUMBER_INT);
            $criterion = $grid->getCriterion($id);
            $criterion->setId('item_' . $id);
            $criteria[] = $criterion;
        }

        $grid->setCriteria($criteria);
        $grid->save();

        return true;
    }


    /**
     * Returns the subtype and the options of a criterion from the criterion form values
     * (standard options are forced back, whatever the posted option labels)
     * @param array<string, mixed> $post
     * @return array{subType: string, options: array<int, array<string, mixed>>|null}
     */
    public static function getCriterionRatingFromPost(array $post): array
    {
        $evaluationType = (string)($post['evaluation_type'] ?? '');

        if ($evaluationType === Episciences_Rating_Criterion::EVALUATION_TYPE_FREE) {
            return ['subType' => Episciences_Rating_Criterion::EVALUATION_TYPE_FREE, 'options' => null];
        }

        if ($evaluationType === Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE) {
            return match ((int)($post['quantitative_rating_type'] ?? 0)) {
                0 => ['subType' => Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_10, 'options' => self::getUnlabelledOptions(10)],
                2 => ['subType' => Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_5, 'options' => self::getUnlabelledOptions(5)],
                default => ['subType' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE_CUSTOM, 'options' => self::getCustomOptionsFromPost($post)],
            };
        }

        if ($evaluationType === Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE) {
            return ((int)($post['qualitative_rating_type'] ?? 0) === 0) ?
                ['subType' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE, 'options' => Episciences_Rating_Criterion::STANDARD_QUALITATIVE_OPTIONS] :
                ['subType' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE_CUSTOM, 'options' => self::getCustomOptionsFromPost($post)];
        }

        return ['subType' => '', 'options' => self::getCustomOptionsFromPost($post)];
    }

    /**
     * @return array<int, array{value: int, label: array<string, string>}> options 0 to $max, without label
     */
    private static function getUnlabelledOptions(int $max): array
    {
        $options = [];
        for ($i = 0; $i <= $max; $i++) {
            $options[] = ['value' => $i, 'label' => []];
        }
        return $options;
    }

    /**
     * Custom options from the "option_<n>" form values (labels by language)
     * @param array<string, mixed> $post
     * @return array<int, array{value: int, label: array<string, string>}>
     */
    private static function getCustomOptionsFromPost(array $post): array
    {
        $options = [];
        $i = 0;

        foreach ($post as $key => $value) {
            if (!str_starts_with((string)$key, 'option_')) {
                continue;
            }
            $labels = [];
            foreach ((is_array($value) ? $value : []) as $lang => $label) {
                $cleanLabel = trim(strip_tags((string)$label));
                if ($cleanLabel !== '') {
                    $labels[$lang] = htmlspecialchars($cleanLabel, ENT_QUOTES, 'UTF-8');
                }
            }
            $options[$i] = ['value' => $i, 'label' => $labels];
            $i++;
        }

        return $options;
    }

    /**
     * Returns criterion form defaults
     * @param Episciences_Rating_Criterion $criterion
     * @return array<string, mixed>
     */
    public static function getCriterionFormDefaults(Episciences_Rating_Criterion $criterion): array
    {

        $defaults['coef'] = $criterion->getCoefficient();
        $defaults['comment'] = $criterion->getComment_setting();
        // $defaults['type'] = $data['TYPE'];
        $defaults['upload'] = $criterion->getAttachment_setting();
        // $defaults['options'] = (!is_null($data['OPTIONS'])) ? explode(',' , $data['OPTIONS']) : null;
        $defaults['options'] = $criterion->getOptions();

        $criterionVisibility = $criterion->getVisibility();

        if ($criterionVisibility === '') {
            $criterionVisibility = 'editors';
        }

        $defaults['visibility'] = $criterionVisibility;

        // criterion name
        foreach ($criterion->getLabels() as $lang => $label) {
            $defaults['critere'][$lang] = $label;
        }

        // criterion description
        foreach ($criterion->getDescriptions() as $lang => $label) {
            $defaults['description'][$lang] = $label;
        }

        // options labels (for custom values)
        if ($criterion->isCustom()) {
            foreach ($defaults['options'] as $i => $option) {
                if (array_key_exists('label', $option) && !empty($option['label'])) {
                    foreach ($option['label'] as $lang => $label) {
                        $defaults['option_' . $i][$lang] = $label;
                    }
                }
            }
        }

        // rating type: the saved subtype first (a custom rating may have empty option labels)
        return array_merge($defaults, match ($criterion->getRatingType()) {
            Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_10 => ['evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE, 'quantitative_rating_type' => 0],
            Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE_CUSTOM => ['evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE, 'quantitative_rating_type' => 1],
            Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_5 => ['evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE, 'quantitative_rating_type' => 2],
            Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE => ['evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE, 'qualitative_rating_type' => 0],
            Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE_CUSTOM => ['evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE, 'qualitative_rating_type' => 1],
            default => ['evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_FREE],
        });
    }


    /**
     * return criterion form (creation / edit)
     * @param array|null $defaults
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public static function getCriterionForm(array $defaults = null): \Ccsd_Form
    {
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');

        $lang = ['class' => 'Episciences_Tools', 'method' => 'getLanguages'];
        $reqLang = ['class' => 'Episciences_Tools', 'method' => 'getRequiredLanguages'];

        // criterion name
        $form->addElement(new Ccsd_Form_Element_MultiTextSimpleLang([
            'name' => 'critere',
            'populate' => $lang,
            'validators' => [new Ccsd_Form_Validate_RequiredLang(['populate' => $reqLang])],
            'required' => true,
            'display' => 'advanced',
            'label' => 'Nom'
        ]));

        // criterion description
        $form->addElement(new Ccsd_Form_Element_MultiTextAreaLang([
            'name' => 'description',
            'populate' => $lang,
            'rows' => 4,
            'display' => 'advanced',
            'label' => 'Description'
        ]));


        // criterion visibility
        $form->addElement('select', 'visibility', [
            'label' => "Visibilité du critère",
            'value' => 'editors',
            'multioptions' => [
                'public' => 'Publique',
                'contributor' => 'Contributeur',
                'editors' => 'Rédacteurs']]);

        // evaluation type
        $form->addElement('select', 'evaluation_type', [
            'label' => "Type d'évaluation",
            'onchange' => "setEvaluationType(this.value)",
            'multioptions' => [
                'quantitative' => 'Quantitative',
                'qualitative' => 'Qualitative',
                'free' => 'Libre']]);


        // comments (yes/no)
        $form->addElement('select', 'comment', [
            'label' => 'Commentaires',
            'multioptions' => [0 => "Non", 1 => "Oui"]]);


        // upload (yes/no)
        $form->addElement('select', 'upload', [
            'label' => 'Upload de fichier',
            'multioptions' => [0 => "Non", 1 => "Oui"]]);


        // cefficient
        $array = range(0, 5);
        unset($array[0]);
        $form->addElement('select', 'coef', [
            'label' => 'Coefficient',
            'value' => 1,
            ]);

        $form->getElement('coef')->setDisableTranslator(true)->setOptions(['multioptions' => $array]);

        // rating type (quantitative)
        $form->addElement('select', 'quantitative_rating_type', [
            'label' => 'Type de notation',
            'onchange' => "setRatingType(this.value)",
            'multioptions' => [
                0 => "Notation sur 10",
                2 => "Notation sur 5",
                1 => "Notation personnalisée"
            ]]);


        // rating type (qualitative)
        $form->addElement('select', 'qualitative_rating_type', [
            'label' => 'Type de notation',
            'onchange' => "setRatingType(this.value)",
            'multioptions' => [
                0 => "Notation classique (oui, non, peut-être)",
                1 => "Notation personnalisée"
            ]]);


        // custom rating opening ul
        $form->addElement('hidden', 'openul', [
            'decorators' => [[
                'decorator' => 'HtmlTag',
                'options' => ['tag' => 'ul', 'class' => 'sortable', 'style' => 'list-style-type: none;', 'openOnly' => true]]]]);
        $optionsGroup[] = 'openul';

        // custom rating

        if (
            isset($defaults['options']) &&
            is_array($defaults['options']) && (count($defaults['options']))) {
            $max = count($defaults['options']) - 1;
        } else {
            $max = 0;
        }

        for ($i = 0; $i <= $max; $i++) {

            // opening li
            $form->addElement('hidden', 'openli_' . $i, [
                'decorators' => [[
                    'decorator' => 'HtmlTag',
                    'options' => ['tag' => 'li', 'id' => 'li_' . $i, 'openOnly' => true]]]]);


            // remove button
            $form->addElement('button', 'close_' . $i, [
                'label' => '×',
                'onclick' => "removeValue(this)",
                'class' => "close",
                'style' => 'margin-top: 10px; margin-right: 10px',
                'decorators' => ['ViewHelper']]);

            // custom rating value
            $form->addElement('multiTextSimpleLang', 'option_' . $i, [
                'populate' => $lang,
                'display' => 'advanced',
                'label' => $i . '/' . $max]);
            $form->getElement('option_' . $i)->getDecorator('HtmlTag')->setOption('style', 'margin-top: 20px');

            // custom rating value position (hidden)
            $form->addElement('hidden', 'position_' . $i, ['value' => $i]);

            // closing li
            $form->addElement('hidden', 'closeli_' . $i, [
                'decorators' => [[
                    'decorator' => 'HtmlTag',
                    'options' => ['tag' => 'li', 'closeOnly' => true]]]]);


            $optionsGroup[] = 'openli_' . $i;
            $optionsGroup[] = 'close_' . $i;
            $optionsGroup[] = 'option_' . $i;
            $optionsGroup[] = 'position_' . $i;
            $optionsGroup[] = 'closeli_' . $i;
        }

        // closing ul
        $form->addElement('hidden', 'closeul', [
            'decorators' => [[
                'decorator' => 'HtmlTag',
                'options' => ['tag' => 'ul', 'closeOnly' => true]]]]);

        $optionsGroup[] = 'closeul';


        // add custom rating value button
        $form->addElement('button', 'add_option', [
            'label' => 'Ajouter une valeur',
            'onclick' => 'addValue(this)',
            'class' => 'btn btn-default btn-sm pull-right',
            'decorators' => ['ViewHelper']]);
        $optionsGroup[] = 'add_option';

        // displaygroup: custom rating values
        $form->addDisplayGroup($optionsGroup, 'options'/*, array("legend" => "Valeurs de notation")*/);
        $form->getDisplayGroup('options')->removeDecorator('DtDdWrapper');
        $form->getDisplayGroup('options')->removeDecorator('HtmlTag');
        $form->getDisplayGroup('options')->setDecorators([
            'FormElements',
            ['Fieldset', ['class' => 'col-md-offset-3 col-md-9']],
            [['div' => 'HtmlTag'], ['tag' => 'div', 'class' => 'form-group row']]
        ]);

        Episciences_Csrf_Helper::addSessionTokenElement($form);

        $form->setActions(true)->createSubmitButton('submit', [
            'label' => 'Valider',
            'class' => 'btn btn-primary'
        ]);
        $form->setActions(true)->createCancelButton('back', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'onclick' => "window.location='/grid'"]);

        if ($defaults) {
            $form->setDefaults($defaults);
        }

        return $form;
    }

    /**
     *  return separator form (creation / edit)
     * @param null $defaults
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public static function getSeparatorForm($defaults = null)
    {
        $form = new Ccsd_Form;
        $form->setAttrib('class', 'form-horizontal');

        $lang = ['class' => 'Episciences_Tools', 'method' => 'getLanguages'];
        $reqLang = ['class' => 'Episciences_Tools', 'method' => 'getRequiredLanguages'];

        // criterion name
        $form->addElement(new Ccsd_Form_Element_MultiTextSimpleLang([
            'name' => 'critere',
            'populate' => $lang,
            'validators' => [new Ccsd_Form_Validate_RequiredLang(['populate' => $reqLang])],
            'required' => true,
            'display' => 'advanced',
            'label' => 'Nom'
        ]));

        // criterion description
        $form->addElement(new Ccsd_Form_Element_MultiTextAreaLang([
            'name' => 'description',
            'populate' => $lang,
            'rows' => 4,
            'display' => 'advanced',
            'label' => 'Description'
        ]));

        // criterion visibility
        $form->addElement('select', 'visibility', [
            'label' => "Visibilité du critère",
            'value' => 'editors',
            'style' => "width: 200px",
            'multioptions' => [
                'public' => 'Publique',
                'contributor' => 'Contributeur',
                'editors' => 'Rédacteurs']]);

        Episciences_Csrf_Helper::addSessionTokenElement($form);

        $form->setActions(true)->createSubmitButton('submit', [
            'label' => 'Valider',
            'class' => 'btn btn-primary'
        ]);
        $form->setActions(true)->createCancelButton('back', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'onclick' => "window.location='/grid'"]);

        if ($defaults) {
            $form->setDefaults($defaults);
        }

        return $form;
    }
}