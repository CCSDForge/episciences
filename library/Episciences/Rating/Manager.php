<?php

class Episciences_Rating_Manager
{

    /**
     * fetch a rating report, given a docid and a reviewer uid
     * @param $docid
     * @param $uid
     * @return Episciences_Rating_Report|false
     */
    public static function find($docid, $uid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_REVIEWER_REPORTS)
            ->where('DOCID = ?', $docid)
            ->where('UID = ?', $uid);

        $row = $db->fetchRow($sql);
        return ($row) ? new Episciences_Rating_Report($row) : false;
    }

    /**
     * fetch an array of rating reports
     * @param null $docid
     * @param null $uid
     * @param null $status
     * @return Episciences_Rating_Report[]
     */
    public static function getList($docid = null, $uid = null, $status = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_REVIEWER_REPORTS)->order('CREATION_DATE', 'DESC');

        if ($docid) {
            $sql->where('DOCID = ?', $docid);
        }
        if ($uid) {
            $sql->where('UID = ?', $uid);
        }
        if ($status) {
            $sql->where('STATUS = ?', $status);
        }

        $reports = array();
        foreach ($db->fetchAll($sql) as $row) {
            $reports[] = new Episciences_Rating_Report($row);
        }

        return $reports;
    }

    /**
     * return an average note calculated from an array of rating reports
     * @param Episciences_Rating_Report[] $ratings
     * @return float|null
     */
    public static function getAverageRating($ratings, int $precision = 0)
    {
        if (!$ratings) {
            return null;
        }

        $total = 0;
        $nbRatings = 0;

        foreach ($ratings as $rating) {
            if ($rating->getStatus()) {
                $total += $rating->getScore();
                $nbRatings++;
            }
        }

        return ($nbRatings) ? round($total / $nbRatings, $precision) : null;
    }

    /**
     * return a paper rating form
     * @param Episciences_Rating_Report $grid
     * @return Ccsd_Form|null
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getRatingForm(Episciences_Rating_Report $grid)
    {
        $criteria = $grid->getCriteria();
        if (empty($criteria)) {
            return null;
        }

        $form = new Ccsd_Form;
        $form->setAttrib('enctype', 'multipart/form-data');
        $form->setAttrib('class', 'form-horizontal');
        $form->setAttrib('id', 'rating-grid');
        $form->addElementPrefixPath('Episciences_Form_Decorator', 'Episciences/Form/Decorator/', 'decorator');
        $translator = Zend_Registry::get('Zend_Translate');

        foreach ($criteria as $criterion) {

            $id = $criterion->getId();
            $criterion_description = $criterion->getDescription();
            $canComment = $criterion->getComment_setting();
            $canUpload = $criterion->getAttachment_setting();

            $group = array();

            // separator
            if ($criterion->isSeparator()) {
                $form->addElement('note', $id, [
                    'value' => '<h2 class="separator">' . $criterion->getLabel() . '</h2>']);
            }

            // note
            if ($criterion->hasOptions()) {
                $notes = array();
                $max = count($criterion->getOptions()) - 1;

                $cOptions = $criterion->getOptions();
                // user language
                $userLanguage = Episciences_Auth::getLangueid();
                $locale = Episciences_Tools::getLocale();

                foreach ($cOptions as $i => $option) {
                    $label = '';

                    // if quantitative rating, or if no translation for this option, get the note value (x/y)
                    if ($criterion->hasCoefficient() || empty($option['label'])) {
                        $label = $option['value'] . '/' . $max;
                    } else {

                        if (!array_key_exists($userLanguage, $option['label'])) {
                            $option['label'] += [$userLanguage => current($option['label'])];
                        }

                        if (!array_key_exists($locale, $option['label'])) {
                            $option['label'] += [$locale => current($option['label'])];
                        }
                    }
                    
                    // if option has a translation, add it to the label
                    if (is_array($option['label']) && array_key_exists($userLanguage, $option['label'])) {
                        if ($label) {
                            $label .= ' : ';
                        }
                        $label .= $option['label'][$locale];
                    }
                    $notes[$i] = $label;
                }

                $form->addElement('select', 'note_' . $id, array(
                    'label' => ($criterion->hasCoefficient()) ? 'Note' . ' (coefficient ' . $criterion->getCoefficient() . ')' : 'Votre avis',
                    'multioptions' => $notes,
                    'value' => $criterion->getNote(),
                    'onchange' => 'updateNote()'));

                $group[] = 'note_' . $id;
            }

            // file upload
            if ($canUpload) {

                $extensions = ALLOWED_EXTENSIONS;
                $description = '<div class="col-sm-12">';
                $description.= Episciences_Tools::buildAttachedFilesDescription();
                $description .=  '</div>';

                if ($criterion->hasAttachment() && file_exists($grid->getPath() . $criterion->getAttachment())) {
                    $filepath = PREFIX_URL . $grid->getDocid() . '/report/' . $grid->getId() . '/' . $criterion->getAttachment();

                    $file_delete_url = PREFIX_URL . 'paper/deleteattachmentreport/docid/' .
                        $grid->getDocid() . '/uid/' . $grid->getUid() .
                        '/cid/' . $criterion->getId() .
                        '/file/' . $criterion->getAttachment();

                    $bloc_delete_file = "<div class=col-sm-2'>";
                    $bloc_delete_file .= "<a class='btn btn-danger btn-xs pull-right' onclick=\"confirmDeleteAttachment('$file_delete_url')\">";
                    $bloc_delete_file .= '<span class="glyphicon glyphicon-trash" style="margin-right: 5px;"></span>';
                    $bloc_delete_file .= $translator->translate('Supprimer');
                    $bloc_delete_file .= '</a></div>';
                    $description .= "<div class='col-sm-10'><p><a href='$filepath' target='_blank'>" . $criterion->getAttachment() . "</a></p></div>"
                        . $bloc_delete_file;
                    $description .= "<div class='row'></div>";
                }
                $form->addElement('file', 'file_' . $id, array(
                    'label' => 'Fichier',
                    'description' => $description,
                    'valueDisabled' => true,
                    'maxFileSize' => MAX_FILE_SIZE,
                    'validators' => array(
                        'Count' => array(false, 1),
                        'Extension' => array(false, implode(',', $extensions)),
                        'Size' => array(false, MAX_FILE_SIZE))));
                $form->getElement('file_' . $id)->getDecorator('Description')->setOption('escape', false);
                $group[] = 'file_' . $id;
            }

            // comment
            if ($canComment) {
                $form->addElement('textarea', 'comment_' . $id, ['label' => 'Commentaire', 'rows' => 5, 'value' => $criterion->getComment()]);
                $group[] = 'comment_' . $id;
            }

            // display group
            if (!empty($group)) {
                $visibility = $criterion->getVisibility();
                switch ($visibility) {
                    default:
                    case Episciences_Rating_Criterion::VISIBILITY_EDITORS:
                        $tooltip = $translator->translate('Votre réponse à ce critère ne pourra être vue que par les rédacteurs.');
                        break;

                    case Episciences_Rating_Criterion::VISIBILITY_PUBLIC:
                        $tooltip = $translator->translate("Votre réponse à ce critère pourra être visible publiquement sur la page de l'article.");
                        break;

                    case Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR:
                        $tooltip = $translator->translate("Votre réponse à ce critère pourra être vue par l'auteur.");
                        break;
                }
                $info = sprintf("[%s %s %s <span class=\"fa-solid fa-circle-question\" data-toggle=\"tooltip\" title=\"%s\"></span>] ", $translator->translate('Visibilité'), Episciences_Rating_Criterion::$visibilityEmojis[$visibility], $translator->translate(ucfirst($visibility)), $tooltip);
                $form->addDisplayGroup($group, 'criterion_' . $id, array("legend" => $info . $criterion->getLabel()));
                $displayGroup = $form->getDisplayGroup('criterion_' . $id);
                $displayGroup->setDescription($criterion_description);
                $displayGroup->setDecorators(array(
                    array('Description', array('style' => 'padding-left: 15px', 'class' => 'hint col-md-offset-3')),
                    'FormElements',
                    array('Fieldset', array('escape' => false))
                ));
            }
        }

        // hidden : global note
        $form->addElement('hidden', 'noteGlobale');

        // display zone for global note
        $form->addElement('hidden', 'overallValue', array(
            'decorators' => array(
                array(
                    'decorator' => 'HtmlTag',
                    'options' => array('tag' => 'div', 'class' => 'overallValue'))
            )));


        $form->addElement('button', 'submitRatingForm', array(
            'label' => "Enregistrer l'évaluation",
            'type' => 'submit',
            'class' => 'btn btn-default',
            'style' => 'margin-top: 15px',
            'decorators' => array(
                array('decorator' => 'ViewHelper'),
                array('decorator' => 'HtmlTag',
                    'options' => array('tag' => 'div', 'class' => 'form-actions text-center', 'openOnly' => true))
            )));

        $form->addElement('button', 'validateRating', array(
            'label' => "Terminer l'évaluation",
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'style' => 'margin-top: 15px',
            'decorators' => array(
                array('decorator' => 'ViewHelper'),
                array('decorator' => 'HtmlTag',
                    'options' => array('tag' => 'div', 'closeOnly' => true))
            )));

        return $form;
    }

    /**
     * @return Zend_Db_Select
     */
    private static function getListQuery()
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db
            ->select()
            ->from(T_REVIEWER_REPORTS)
            ->order('CREATION_DATE DESC');
    }


    /**
     * fetch a  previous ratings
     * @param Episciences_Paper $paper
     * @param null $uid
     * @param null $status
     * @return array
     */
    public static function getPreviousRatings(Episciences_Paper $paper, $uid = null, $status = null)
    {
        $reports = [];
        $versionsIds = $paper->getVersionsIds();
        foreach ($versionsIds as $version => $docId) {
            if ((float)$paper->getVersion() <= (float)$version) {
                unset($versionsIds[$version]);
            }
        }
        $docsIds = array_values($versionsIds);

        if (!empty($docsIds)) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sql = self::getListQuery();
            $sql->where('DOCID in (?)', $docsIds);

            if ($uid) {
                $sql->where('UID = ?', $uid);
            }

            if ($status) {
                $sql->where('STATUS = ?', $status);
            }

            foreach ($db->fetchAll($sql) as $row) {
                $reports[] = new Episciences_Rating_Report($row);
            }

        }

        return $reports;
    }

    /**
     * @return bool
     */

    public static function isExistingCriterion(): bool
    {
        $oGrid = new Episciences_Rating_Grid();
        return ($oGrid->loadXML(REVIEW_GRIDS_PATH . REVIEW_GRID_NAME_DEFAULT) && $oGrid->getCriteria());
    }
}
