<?php

class Episciences_Mail_TemplatesManager
{
    public const TYPE_USER_REGISTRATION = 'user_registration';
    public const TYPE_USER_LOST_PASSWORD = 'user_lost_password';
    public const TYPE_USER_LOST_LOGIN = 'user_lost_login';
    public const TYPE_PAPER_ACCEPTED_TMP_VERSION = 'paper_accepted_tmp_version';
    public const TYPE_PAPER_ACCEPTED_TMP_VERSION_MANAGERS_COPY = 'paper_accepted_tmp_version_managers_copy';
    public const TYPE_PAPER_ACCEPTED = 'paper_accepted';
    public const TYPE_PAPER_PUBLISHED_AUTHOR_COPY = 'paper_published_author_copy';
    public const TYPE_PAPER_PUBLISHED_EDITOR_COPY = 'paper_published_editor_copy';
    public const TYPE_PAPER_REFUSED = 'paper_refused'; // author copy
    public const TYPE_PAPER_REFUSED_EDITORS_COPY = 'paper_refused_editors_copy'; // editors copy
    public const TYPE_PAPER_REVISION_REQUEST = 'paper_revision_request';
    public const TYPE_PAPER_MINOR_REVISION_REQUEST = 'paper_minor_revision_request';
    public const TYPE_PAPER_MAJOR_REVISION_REQUEST = 'paper_major_revision_request';
    public const TYPE_PAPER_UPDATED_RATING_DEADLINE = 'paper_updated_rating_deadline';
    public const TYPE_PAPER_EDITOR_ASSIGN = 'paper_editor_assign';
    public const TYPE_PAPER_EDITOR_UNASSIGN = 'paper_editor_unassign';
    public const TYPE_PAPER_ASK_OTHER_EDITORS = 'paper_ask_other_editors';

    public const TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY = 'paper_comment_answer_reviewer_copy';
    public const TYPE_PAPER_COMMENT_ANSWER_EDITOR_COPY = 'paper_comment_answer_editor_copy';
    public const TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_AUTHOR_COPY = 'paper_comment_author_copy';
    public const TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_EDITOR_COPY = 'paper_comment_editor_copy'; // existe mais pas encore exploiter

    public const TYPE_PAPER_REVISION_ANSWER = 'paper_revision_answer';
    public const TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION = 'paper_new_version_reviewer_reinvitation';
    public const TYPE_PAPER_TMP_VERSION_REVIEWER_REASSIGN = 'paper_tmp_version_reviewer_reassign';
    public const TYPE_PAPER_TMP_VERSION_SUBMITTED = 'paper_tmp_version_submitted';
    public const TYPE_PAPER_NEW_VERSION_REVIEWER_REASSIGN = 'paper_new_version_reviewer_reassign';
    public const TYPE_PAPER_NEW_VERSION_SUBMITTED = 'paper_new_version_submitted';
    public const TYPE_PAPER_REVIEWED_REVIEWER_COPY = 'paper_reviewed_reviewer_copy';
    public const TYPE_PAPER_REVIEWED_EDITOR_COPY = 'paper_reviewed_editor_copy';
    public const TYPE_PAPER_DELETED_AUTHOR_COPY = 'paper_deleted_author_copy';
    public const TYPE_PAPER_DELETED_EDITOR_COPY = 'paper_deleted_editor_copy';
    public const TYPE_PAPER_DELETED_REVIEWER_COPY = 'paper_deleted_reviewer_copy';

    public const TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER = 'paper_reviewer_invitation1';
    public const TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER = 'paper_reviewer_invitation2';
    public const TYPE_PAPER_REVIEWER_INVITATION_NEW_USER = 'paper_reviewer_invitation3';

    public const TYPE_PAPER_REVIEWER_REMOVAL = 'paper_reviewer_removal';
    public const TYPE_PAPER_REVIEWER_ACCEPTATION_REVIEWER_COPY = 'paper_reviewer_acceptation_reviewer_copy';
    public const TYPE_PAPER_REVIEWER_ACCEPTATION_EDITOR_COPY = 'paper_reviewer_acceptation_editor_copy';
    public const TYPE_PAPER_REVIEWER_REFUSAL_REVIEWER_COPY = 'paper_reviewer_refusal_reviewer_copy';
    public const TYPE_PAPER_REVIEWER_REFUSAL_EDITOR_COPY = 'paper_reviewer_refusal_editor_copy';
    public const TYPE_PAPER_SUBMISSION_EDITOR_COPY = 'paper_submission_editor_copy';
    public const TYPE_PAPER_SUBMISSION_AUTHOR_COPY = 'paper_submission_author_copy';
    public const TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION = 'reminder_unanswered_reviewer_invitation_reviewer_version';
    public const TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_EDITOR_VERSION = 'reminder_unanswered_reviewer_invitation_editor_version';
    public const TYPE_REMINDER_BEFORE_RATING_DEADLINE_REVIEWER_VERSION = 'reminder_before_deadline_reviewer_version';
    public const TYPE_REMINDER_BEFORE_RATING_DEADLINE_EDITOR_VERSION = 'reminder_before_deadline_editor_version';
    public const TYPE_REMINDER_AFTER_RATING_DEADLINE_REVIEWER_VERSION = 'reminder_after_deadline_reviewer_version';
    public const TYPE_REMINDER_AFTER_RATING_DEADLINE_EDITOR_VERSION = 'reminder_after_deadline_editor_version';
    public const TYPE_REMINDER_BEFORE_REVISION_DEADLINE_AUTHOR_VERSION = 'reminder_before_revision_deadline_author_version';
    public const TYPE_REMINDER_BEFORE_REVISION_DEADLINE_EDITOR_VERSION = 'reminder_before_revision_deadline_editor_version';
    public const TYPE_REMINDER_AFTER_REVISION_DEADLINE_AUTHOR_VERSION = 'reminder_after_revision_deadline_author_version';
    public const TYPE_REMINDER_AFTER_REVISION_DEADLINE_EDITOR_VERSION = 'reminder_after_revision_deadline_editor_version';
    public const TYPE_REMINDER_NOT_ENOUGH_REVIEWERS_EDITOR_VERSION = 'reminder_not_enough_reviewers';
    public const TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION = 'reminder_article_blocked_in_accepted_state_editor_version';
    public const TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_CHIEF_EDITOR_VERSION = 'reminder_article_blocked_in_accepted_state_editor_version';

    public const TYPE_PAPER_SUGGEST_ACCEPTATION = 'paper_suggest_acceptation';
    public const TYPE_PAPER_SUGGEST_REFUSAL = 'paper_suggest_refusal';
    public const TYPE_PAPER_SUGGEST_NEW_VERSION = 'paper_suggest_new_version';

    // Abandon publication process
    public const TYPE_PAPER_ABANDON_PUBLICATION_AUTHOR_COPY = 'paper_abandon_publication_author_copy';
    public const TYPE_PAPER_ABANDON_PUBLICATION_EDITOR_COPY = 'paper_abandon_publication_editor_copy';
    public const TYPE_PAPER_ABANDON_PUBLICATION_REVIEWER_REMOVAL = 'paper_abandon_publication_reviewer_removal';
    public const TYPE_PAPER_ABANDON_PUBLICATION_BY_AUTHOR_AUTHOR_COPY = 'paper_abandon_publication_by_author_author_copy';
    public const TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS = 'paper_abandon_publication_no_assigned_editors';

    // Continue publication process
    public const TYPE_PAPER_CONTINUE_PUBLICATION_AUTHOR_COPY = "paper_continue_publication_author_copy";
    public const TYPE_PAPER_CONTINUE_PUBLICATION_EDITOR_COPY = "paper_continue_publication_editor_copy";

    // COPY EDITOR
    public const TYPE_PAPER_COPY_EDITOR_ASSIGN = 'paper_copyeditor_assign';
    public const TYPE_PAPER_COPY_EDITOR_UNASSIGN = 'paper_copyeditor_unassign';
    public const TYPE_PAPER_COPY_EDITOR_ASSIGN_AUTHOR_COPY = 'paper_copyeditor_assign_author_copy';
    public const TYPE_PAPER_COPY_EDITOR_ASSIGN_EDITOR_COPY = 'paper_copyeditor_assign_editor_copy';

    //Changement état Copy editing : en attente des sources auteurs
    public const TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_EDITOR_COPY = 'paper_ce_waiting_for_author_sources_editor_copy';
    public const TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_AUTHOR_COPY = 'paper_ce_waiting_for_author_sources_author_copy';
    public const TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_AUTHOR_COPY = 'paper_ce_waiting_for_author_formatting_author_copy';
    public const TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_EDITOR_AND_COPYEDITOR_COPY = 'paper_ce_waiting_for_author_formatting_editor_and_copyeditor_copy';

    // editor comments
    public const TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY = 'paper_comment_by_editor_editor_copy';
    public const TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_AUTHOR_COPY = 'paper_ce_author_sources_deposed_response_author_copy';
    public const TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_COPYEDITORS_AND_EDITORS_COPY = 'paper_ce_author_sources_deposed_response_copyeditors_and_editors_copy';
    // Reponse à une demande d'une version finale
    public const TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY = 'paper_ce_author_vesrion_finale_deposed_author_copy';
    public const TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY = 'paper_ce_author_vesrion_finale_deposed_editor_and_copyeditor_copy';
    // review formatting deposed
    public const TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_AUTHOR_COPY = 'paper_ce_review_formatting_deposed_author_copy';
    public const TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY = 'paper_ce_review_formatting_deposed_editor_and_copyeditor_copy';
    // ready to  publish
    public const TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_AUTHOR_COPY = 'paper_ce_accepted_final_version_author_copy';
    public const TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_COPYEDITOR_AND_EDITOR_COPY = 'paper_ce_accepted_final_version_copyeditor_and_editor_copy';

    // paper accepted => stop pending reviewing
    public const TYPE_REVIEWER_PAPER_ACCEPTED_STOP_PENDING_REVIEWING = "paper_reviewer_paper_accepted_stop_pending_reviewing";

    // demande de modifications => inviter les relecteurs à ne pas poursuivre leurs relectures.
    public const TYPE_REVIEWER_PAPER_REVISION_REQUEST_STOP_PENDING_REVIEWING = "paper_reviewer_paper_revision_request_stop_pending_reviewing";
    public const TYPE_REVIEWER_PAPER_REFUSED_REQUEST_STOP_PENDING_REVIEWING = "paper_reviewer_paper_refused_stop_pending_reviewing";
    public const TYPE_REVIEWER_PAPER_PUBLISHED_REQUEST_STOP_PENDING_REVIEWING = "paper_reviewer_paper_published_stop_pending_reviewing";

    // paper accepted: editors notification
    public const TYPE_PAPER_ACCEPTED_EDITORS_COPY = 'paper_accepted_editors_copy';

    // refus de gérer un article
    public const TYPE_PAPER_EDITOR_REFUSED_MONITORING = 'paper_editor_refused_monitoring';

    public const TYPE_PAPER_SECTION_EDITOR_ASSIGN = 'paper_section_editor_assign';
    public const TYPE_PAPER_VOLUME_EDITOR_ASSIGN = 'paper_volume_editor_assign';
    public const TYPE_PAPER_SUGGESTED_EDITOR_ASSIGN = 'paper_suggested_editor_assign';
    // Mise à jour d'un article
    public const TYPE_PAPER_SUBMISSION_UPDATED_AUTHOR_COPY = 'paper_submission_updated_author_copy';
    public const TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY = 'paper_submission_updated_editor_copy';
    //git #230
    public const TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY = 'paper_submission_other_recipient_copy';
    public const TYPE_PAPER_ACCEPTED_ASK_FINAL_AUTHORS_VERSION = 'paper_accepted_ask_authors_final_version';


    public static function getList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(T_MAIL_TEMPLATES)
            ->where('RVID IS NULL')
            ->orWhere('RVID = ?', RVID)
            ->order('TYPE')
            ->order('POSITION');

        $templates = $db->fetchAssoc($select);

       /* $order = array(
            'user',
            'paper_submission',
            'paper_editor_assign',
            'paper_review',
            'paper_comment',
            'paper_editor_suggestion',
            'paper_revision',
            'paper_final_decision');
       */

        // On retire de la liste les templates par défaut qui ont une version modifiée
        foreach ($templates as $template) {
            if ($template['PARENTID']) {
                unset($templates[$template['PARENTID']]);
            }
        }

        // Tri des templates
        /*
        usort($templates, function($a, $b) use($order) {
            if ($a['TYPE'] == $b['TYPE']) {
                if ($a['POSITION'] == $b['POSITION']) return 0;
                return ($a['POSITION'] < $b['POSITION']) ? -1 : 1;
            }
            return (array_search($a['TYPE'], $order) < array_search($b['TYPE'], $order)) ? -1 : 1;
        });
        */

        return $templates;
    }

    /**
     * @param Episciences_Mail_Template $template
     * @param null $langs
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getTemplateForm(Episciences_Mail_Template $template, $langs = null)
    {
        $id = $template->getId();
        $form = new Zend_Form();
        $form->setAttrib('id', 'template-' . $id);
        $form->setAction('/administratemail/savetemplate?id=' . $id);
        $form->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'div', 'class' => 'tab-content')),
            'Form',
        ));

        if (!$langs) {
            $langs = Episciences_Tools::getLanguages();
        }
        $translator = Zend_Registry::get("Zend_Translate");
        $locale = $translator->getLocale();
        $defaultLang = (array_key_exists($locale, $langs)) ? $locale : 'fr';
        $description = $translator->translate('Tags disponibles : ', $locale) . $template->getAvailableTagsListDescription();

        foreach ($langs as $code => $lang) {

            $subform = new Zend_Form_SubForm();

            $class = 'tab-pane fade';
            if (count($langs) == 1 || $code == $defaultLang) {
                $class .= ' in active';
            }

            $subform->setDecorators(array(
                'FormElements',
                array('HtmlTag', array('tag' => 'div', 'class' => $class, 'id' => $code . '_form')),
            ));

            // Template name
            $name = new Zend_Form_Element_Text('name');
            $name->setLabel(Zend_Registry::get("Zend_Translate")->translate('Nom du template'));
            $name->setAttribs(array('style' => 'width:538px'));
            $subform->addElement($name);

            // Mail subject
            $subject = new Zend_Form_Element_Text('subject');
            $subject->setLabel(Zend_Registry::get("Zend_Translate")->translate('Sujet du mail'));
            $subject->setAttribs(array('style' => 'width:538px'));
            $subform->addElement($subject);

            $body = new Zend_Form_Element_Textarea('body');
            $body->setLabel(Zend_Registry::get("Zend_Translate")->translate('Corps du message'));
            $body->setDescription($description);
            $body->setAttribs(array('rows' => 10, 'style' => 'width:538px'));
            $subform->addElement($body);

            $form->addSubForm($subform, $code);
        }

        if ($template) {
            $defaults = self::getTemplateFormDefaults($template, $langs);
            $form->setDefaults($defaults);
        }

        return $form;
    }

    private static function getTemplateFormDefaults(Episciences_Mail_Template $template, $langs)
    {
        $defaults = array();
        $template->loadTranslations();

        foreach ($langs as $code => $lang) {
            $defaults[$code]['name'] = $template->getName($code);
            $defaults[$code]['subject'] = $template->getSubject($code);
            // if template is default, nltobr
            $defaults[$code]['body'] = ($template->getParentid()) ? $template->getBody($code) : nl2br($template->getBody($code));
        }

        return $defaults;
    }

    public static function getTemplatePath($key, $locale = null)
    {
        if (!$locale) {
            $locale = Zend_Registry::get('Zend_Translate')->getLocale();
        }
        $applicationPath = APPLICATION_PATH . '/languages/' . $locale . '/emails';
        $localPath = REVIEW_LANG_PATH . $locale . '/emails';

        if (file_exists($localPath . '/custom_' . $key . '.phtml')) {
            $result['path'] = $localPath;
            $result['key'] = 'custom_' . $key;
            $result['file'] = $result['key'] . '.phtml';
        } elseif (file_exists($applicationPath . '/' . $key . '.phtml')) {
            $result['path'] = $applicationPath;
            $result['key'] = $key;
            $result['file'] = $result['key'] . '.phtml';
        } else {
            $result = false;
        }

        return $result;
    }
}
