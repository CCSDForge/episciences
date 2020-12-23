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
            ->where('SETTING = \'suggestedReviewer\'');

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
            ->where('SETTING = \'unwantedReviewer\'');

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
            'class' => 'btn btn-danger',
            'onclick' => 'cancel()',
            'decorators' => array('ViewHelper')
        )));
        $form->addElement(new Zend_Form_Element_Button(array(
            'name' => 'submit-accept',
            'type' => 'submit',
            'label' => 'Valider',
            'class' => 'btn btn-success',
            'decorators' => array('ViewHelper')
        )));
        $form->setDecorators(array(array('ViewScript', array('viewScript' => 'reviewer/invitation_part2.phtml', 'name' => 'Détails du compte'))));

        return $form;
    }

    /**
     * Renvoie le formulaire de refus de relecture d'un article
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function refuseInvitationForm()
    {
        $form = new Zend_Form();
        // $form->setAttrib('class', 'form-inline');
        $form->removeDecorator('HtmlTag');

        // Decorators
        $decorators = array(
            'ViewHelper',
            'Errors',
            array('Description', array('tag' => 'p', 'class' => 'description')),
            'Label',
            array('HtmlTag', array('class' => 'form-group'))
        );

        // Souhaitez-vous suggérer un relecteur ?
        // Input Text : Suggérer relecteur
        $form->addElement(new Zend_Form_Element_Text(array(
            'name' => 'suggest-reviewer',
            'class' => 'form-control',
            'label' => 'Si vous le souhaitez, vous pouvez nous suggérer un autre relecteur :',
            'style' => 'width: 50%',
            'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Suggérez un relecteur'),
            'decorators' => $decorators
        )));

        // Commentaire (motif du refus)
        // Input Text : Suggérer relecteur
        $form->addElement(new Zend_Form_Element_Textarea(array(
            'name' => 'comment',
            'class' => 'form-control',
            'label' => 'Si vous le souhaitez, vous pouvez nous indiquer la raison de votre refus :',
            'style' => 'width: 50%; height: 150px',
            'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Motif de votre refus'),
            'decorators' => $decorators
        )));


        // Confirmer
        $form->addElement(new Zend_Form_Element_Button(array(
            'name' => 'submit-refuse',
            'type' => 'submit',
            'label' => 'Confirmer mon refus',
            'class' => 'btn btn-success',
            'decorators' => array('ViewHelper')
        )));

        // Annuler
        $form->addElement(new Zend_Form_Element_Button(array(
            'name' => 'cancel-accept',
            'label' => "J'ai changé d'avis !",
            'class' => 'btn btn-danger',
            'onclick' => 'cancel()',
            'decorators' => array('ViewHelper')
        )));

        return $form;
    }

    /**
     * @param $uid
     * @param int $vid
     * @param int $rvid
     * @return bool
     */

    public static function addReviewerToPool($uid, $vid = 0, $rvid = RVID)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->query('INSERT IGNORE INTO ' . T_REVIEWER_POOL . ' (RVID, VID, UID) VALUES (?, ?, ?)', array($rvid, $vid, $uid));
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
                'Size'  =>  array(false, MAX_FILE_SIZE))
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
