<?php

class Episciences_ReviewersManager
{
    /**
     * Renvoie la liste des relecteurs d'une revue
     * @param array $settings
     * @return Episciences_User[]
     */
    public static function getList($settings = array())
    {
        $reviewers = Episciences_Review::getReviewers();
        return $reviewers;
    }

    /**
     *  Renvoie les suggestions de relecteurs pour un papier
     * @param $docid
     * @return array
     */
    public static function getSuggestedReviewers($docid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPER_SETTINGS, 'value')
            ->where('DOCID = ?', $docid)
            ->where('SETTING = ?', 'suggestedReviewer');

        return $db->fetchCol($select);
    }

    /**
     * Renvoie les relecteurs non désirés pour un papier
     * @param int $docid
     * @return array
     */
    public static function getUnwantedReviewers($docid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPER_SETTINGS, 'value')
            ->where('DOCID = ?', $docid)
            ->where('SETTING = ?', 'unwantedReviewer');

        return $db->fetchCol($select);
    }

    /**
     * Renvoie le formulaire d'acceptation de relecture d'un article
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function acceptInvitationForm()
    {
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->setAttrib('name', 'accept_form');
        $form->setAttrib('id', 'accept_form');

        $user_form = new Episciences_User_Form_Create();
        $form->setElements($user_form->getElements());
        $form->addElement(new Zend_Form_Element_Button(array(
            'name' => 'cancel-user',
            'label' => "Annuler",
            'class' => 'btn btn-default',
            'data-answer-cancel' => 'true',
            'decorators' => array('ViewHelper')
        )));
        $form->addElement(new Zend_Form_Element_Button(array(
            'name' => 'submit-accept',
            'type' => 'submit',
            'label' => 'Valider',
            'class' => 'btn btn-success',
            'decorators' => array('ViewHelper')
        )));
        Episciences_Csrf_Helper::addSessionTokenElement($form);
        // the view script lays the form out; the styles and scripts of its fields (e.g. the "+" of the multi-value
        // fields) are still added by the Ccsd_Form decorators
        $form->setDecorators([
            ['ViewScript', ['viewScript' => 'reviewer/invitation_part2.phtml', 'name' => 'Détails du compte']],
            'FormCss',
            'FormJavascript',
        ]);

        return $form;
    }

    /**
     * Id of the heading naming the refusal form (reviewer/invitation_response_form.phtml)
     */
    public const REFUSE_FORM_TITLE_ID = 'refuse-form-title';

    /**
     * Unlike the default message, does not repeat the value: a screen reader would read it out in full
     */
    private const TOO_LONG_MESSAGE = 'Ce texte ne doit pas dépasser %max% caractères.';

    /**
     * Renvoie le formulaire de refus de relecture d'un article
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function refuseInvitationForm(): Zend_Form
    {
        $form = new Zend_Form();
        $form->removeDecorator('HtmlTag');
        $form->setAttrib('aria-labelledby', self::REFUSE_FORM_TITLE_ID);

        $form->addElement(new Zend_Form_Element_Text([
            'name' => 'suggestreviewer',
            'class' => 'form-control',
            'label' => 'Suggérer un autre relecteur (facultatif)',
            'description' => 'Indiquez par exemple son nom et son adresse e-mail.',
            'maxlength' => 255,
            'aria-describedby' => 'suggestreviewer-description',
            'filters' => ['StripTags', 'StringTrim'],
            'validators' => [['StringLength', false, ['max' => 255, 'messages' => [Zend_Validate_StringLength::TOO_LONG => self::TOO_LONG_MESSAGE]]]],
            'decorators' => self::refusalFieldDecorators('suggestreviewer'),
        ]));

        $form->addElement(new Zend_Form_Element_Textarea([
            'name' => 'comment',
            'class' => 'form-control',
            'label' => 'Motif du refus (facultatif)',
            'description' => "Votre réponse est transmise à l'équipe éditoriale. 5000 caractères maximum.",
            'rows' => 6,
            'maxlength' => 5000,
            'aria-describedby' => 'comment-description',
            'filters' => ['StripTags', 'StringTrim'],
            'validators' => [['StringLength', false, ['max' => 5000, 'messages' => [Zend_Validate_StringLength::TOO_LONG => self::TOO_LONG_MESSAGE]]]],
            'decorators' => self::refusalFieldDecorators('comment'),
        ]));

        // the action of this step; declining is a choice, not a danger: no red
        $form->addElement(new Zend_Form_Element_Button([
            'name' => 'submitrefuse',
            'type' => 'submit',
            'label' => 'Confirmer mon refus',
            'class' => 'btn btn-primary',
            'decorators' => ['ViewHelper'],
        ]));

        // closes the form and displays the answer buttons again (public/js/reviewer/invitation.js)
        $form->addElement(new Zend_Form_Element_Button([
            'name' => 'cancelaccept',
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'data-answer-cancel' => 'true',
            'decorators' => ['ViewHelper'],
        ]));

        $form->addDisplayGroup(['submitrefuse', 'cancelaccept'], 'refuseactions', [
            'decorators' => ['FormElements', ['HtmlTag', ['tag' => 'div', 'class' => 'form-actions text-center']]],
        ]);

        Episciences_Csrf_Helper::addSessionTokenElement($form);

        return $form;
    }

    /**
     * Links the validation errors of the refusal form to their fields, for assistive technologies.
     */
    public static function describeRefusalFormErrors(Zend_Form $form): void
    {
        foreach ($form->getElements() as $element) {
            if ($element->getMessages() === []) {
                continue;
            }

            $describedBy = trim($element->getName() . '-errors ' . $element->getAttrib('aria-describedby'));
            $element->setAttrib('aria-invalid', 'true');
            $element->setAttrib('aria-describedby', $describedBy);
        }
    }

    /**
     * Label, field, errors then help text; the errors and the help text carry the ids the field refers to.
     *
     * @return array<int, string|array{0: string, 1: array<string, string>}>
     */
    private static function refusalFieldDecorators(string $name): array
    {
        return [
            'ViewHelper',
            ['Errors', ['id' => $name . '-errors']],
            ['Description', ['tag' => 'p', 'class' => 'help-block', 'id' => $name . '-description']],
            'Label',
            ['HtmlTag', ['class' => 'form-group']],
        ];
    }

    /**
     * @param $uid
     * @param int $vid
     * @param int $rvid
     * @return bool
     */

    public static function addReviewerToPool($uid, $vid = 0, $rvid = RVID): bool
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $db->query('INSERT IGNORE INTO ' . T_REVIEWER_POOL . ' (RVID, VID, UID) VALUES (?, ?, ?)', [$rvid, $vid, $uid]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Renvoie le formulaire permettant de joindre un rapport de relecture
     * @param $id
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function reviewer_answer_form($id){
        $form = new Ccsd_Form();
        $form->setName('reviewer_answer_'.$id);
        $form->setAttrib('enctype', 'multipart/form-data');
        $form->setAttrib('class', 'form-horizontal');
        $implode_extensions = implode(',', ALLOWED_EXTENSIONS) ;
        $description = Episciences_Tools::buildAttachedFilesDescription();
        $form->addElement('file', 'reviewer_answer_file_'.$id,[
            'label'         =>  'Fichier',
            'description' => $description,
            'valueDisabled' =>  true,
            'maxFileSize'   =>  MAX_FILE_SIZE,
            'validators'    =>  array(
                'Count' =>  array(false, 1),
                'Extension' => array(false, $implode_extensions),
                'Size'  =>  array(false, MAX_FILE_SIZE),
                new Episciences_Form_Validate_MimeType())
        ]);

        $form->addElement('button', 'send_reviewer_report_' . $id, array(
            'label' => 'Envoyer',
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'style' => 'width:33%',
            'decorators' => array(
                array('ViewHelper', array('class' => 'form-control input-sm')),
                array('HtmlTag', array('tag' => 'div', 'class' => "col-md-9 col-md-offset-3"))
            )
        ));

        return $form;
    }
}
