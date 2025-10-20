<?php

// rating grids manager
class Episciences_GridsManager
{
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
            $vids[] = $grid->getId();
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
                $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'grid', 'action' => 'add']));
                $form->setAttrib('class', 'form-horizontal');

                $form->addElement('select', 'volume', [
                    'label' => 'Créer une grille pour le volume :',
                    'multioptions' => $options,
                    'style' => 'width:300px'
                ]);

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
        $grid = new Episciences_Rating_Grid;
        if (!$grid->loadXML(REVIEW_GRIDS_PATH . 'grid_' . $params['rgid'] . '.xml')) {
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
     * Returns criterion form defaults
     * @param Episciences_Rating_Criterion $criterion
     * @return array
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
        $custom = $criterion->isCustom();
        if ($custom) {
            foreach ($defaults['options'] as $i => $option) {
                if (array_key_exists('label', $option) && !empty($option['label'])) {
                    foreach ($option['label'] as $lang => $label) {
                        $defaults['option_' . $i][$lang] = $label;
                    }
                }
            }
        }

        // quantitative rating
        if ($criterion->getCoefficient()) {
            $defaults['evaluation_type'] = Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE;
            if ($custom) {
                $defaults['quantitative_rating_type'] = 1;
            } else {
                $defaults['quantitative_rating_type'] = (count($defaults['options']) === 11) ? 0 : 2;
            }
        } // qualitative rating
        elseif ( (!is_null($criterion->getOptions())) && count($criterion->getOptions()) > 1 ) {
            $defaults['evaluation_type'] = Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE;
            $defaults['qualitative_rating_type'] = ($custom) ? 1 : 0;
        } // free rating
        else {
            $defaults['evaluation_type'] = Episciences_Rating_Criterion::EVALUATION_TYPE_FREE;
        }

        return $defaults;
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

        $form->setActions(true)->createSubmitButton('submit', [
            'label' => 'Valider',
            'class' => 'btn btn-primary'
        ]);

        $cancelUrl = (new Episciences_View_Helper_Url())->url(array(
            'controller' => 'grid',
            'action' => 'index'
        ));
        $form->setActions(true)->createCancelButton('back', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'onclick' => "window.location='$cancelUrl'"]);

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

        $form->setActions(true)->createSubmitButton('submit', [
            'label' => 'Valider',
            'class' => 'btn btn-primary'
        ]);
        $cancelUrl = (new Episciences_View_Helper_Url())->url(array(
            'controller' => 'grid',
            'action' => 'index'
        ));
        $form->setActions(true)->createCancelButton('back', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'onclick' => "window.location='$cancelUrl'"]);

        if ($defaults) {
            $form->setDefaults($defaults);
        }

        return $form;
    }
}