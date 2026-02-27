<?php

class Episciences_Mail_TemplatesManager
{
    public const SUFFIX_TPL_NAME = '_tpl_name';
    public const SUFFIX_TPL_SUBJECT = '_mail_subject';
    public const TPL_TRANSLATION_FILE_NAME = 'mails.php';
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
    public const TYPE_PAPER_REVISION_REQUEST = 'paper_revision_request'; // not used
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
    public const TYPE_PAPER_NEW_VERSION_SUBMITTED = 'paper_new_version_submitted';
    public const TYPE_PAPER_NEW_VERSION_SUBMISSION_AUTHOR = 'paper_new_version_submission_author';
    public const TYPE_PAPER_NEW_VERSION_TEMPORARY_SUBMISSION_AUTHOR = 'paper_new_version_temporary_submission_author';
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
    public const TYPE_PAPER_FORMATTED_BY_JOURNAL_WAITING_AUTHOR_VALIDATION = 'paper_formatted_by_journal_waiting_author_validation';
    public const TYPE_INBOX_PAPER_SUBMISSION_AUTHOR_COPY = 'inbox_paper_submission_author_copy';
    // git #513
    public const TYPE_REMINDER_REVIEWED_ARTICLE_EDITOR_VERSION = 'reminder_reviewed_article_editors_copy';
    public const TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION = 'reminder_submitted_article_editors_copy';
    public const TYPE_PAPER_AUTHOR_COMMENT_EDITOR_COPY = 'paper_author_comment_editor_copy';

    /**
     * /!\
     * These constants are used; do not delete them.
     * **** Used dynamically @see Episciences_Mail_Reminder constant($constant_name);
     */

    public const TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_CHIEF_EDITOR_VERSION = 'reminder_article_blocked_in_accepted_state_editor_version';
    public const TYPE_REMINDER_REVIEWED_ARTICLE_CHIEF_EDITOR_VERSION = 'reminder_reviewed_article_editors_copy';
    public const TYPE_REMINDER_SUBMITTED_ARTICLE_CHIEF_EDITOR_VERSION = 'reminder_submitted_article_editors_copy';
    /**
     * END Of /!\
     */


    // available in all templates
    public const COMMON_TAGS = [
        Episciences_Mail_Tags::TAG_REVIEW_CODE,
        Episciences_Mail_Tags::TAG_REVIEW_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
    ];

    public const DESCRIPTION = 'description';
    public const RECIPIENT = 'recipient';
    public const AUTHOR_RECEP_EXP = "l'auteur (déposant) de l'article";
    public const MANAGERS_RECEP_EXP = "tous les rédacteurs et préparateurs de copie assignés à l'article et selon le paramétrage de la revue, les rédacteurs en chef, administrateurs et secrétaires de rédaction";
    public const EDITORS_RECEP_EXP = "tous les rédacteurs assignés à l'article";
    public const MANAGERS_COPY_EDITORS_EXCEPTED_EXP = "tous les rédacteurs assignés à l'article et selon le paramétrage de la revue, les rédacteurs en chef, administrateurs et secrétaires de rédaction";
    public const REVIEWER_RECEP_EXP = "tous les relecteurs assignés à l'article dont la relecture n'est pas encore achevée";

    public const AUTHORS_CO_AUTHORS = "Authors and co authors (if exist)";

    // obsolete templates ?
    // protected $_paper_comment_answer_editor_copy_tags = [];
    // protected $_paper_comment_author_copy_tags = [];
    // protected $_paper_comment_editor_copy_tags = [];
    // protected $_paper_published_editor_copy_tags = [];

    // paper status change
    public const paper_accepted_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
    ];

    public const  paper_accepted_editors_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
        Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_NAME,
        Episciences_Mail_Tags::TAG_ALL_REVIEW_RESOURCES_LINK
    ];

    public const paper_accepted_tmp_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
    ];

    public const paper_accepted_tmp_version_managers_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_REQUESTER_SCREEN_NAME
    ];

    public const paper_ask_other_editors_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_ADMINISTRATION_URL
    ];
    public const paper_published_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    public const paper_refused_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    public const paper_revision_request_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    public const paper_major_revision_request_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    public const paper_minor_revision_request_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];

    public const paper_comment_by_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_EDITOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
    ];

    public const paper_comment_answer_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_COMMENT_DATE,
        Episciences_Mail_Tags::TAG_ANSWER,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];

    public const paper_comment_from_reviewer_to_contributor_author_copy_tags = [
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_COMMENT_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN
    ];

    public const paper_comment_from_reviewer_to_contributor_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_COMMENT_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN
    ];
    public const paper_deleted_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    public const paper_deleted_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME, // = sender full name
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    public const paper_deleted_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME, // = sender full name
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    public const paper_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    public const paper_editor_unassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    public const paper_new_version_reviewer_reassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_NEW_VERSION_URL,
    ];
    public const paper_new_version_submitted_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    public const paper_new_version_submission_author = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];
    public const paper_new_version_temporary_submission_author = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];
    public const paper_reviewed_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL // paper administration page url
    ];
    public const paper_reviewed_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL // rating page url
    ];
    public const paper_reviewer_acceptation_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_SCREEN_NAME,
    ];
    public const paper_reviewer_acceptation_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_PAPER_URL,

    ];

    public const paper_reviewer_invitation1_tags = [
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL
    ];
    public const paper_reviewer_invitation2_tags = [
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL
    ];
    public const paper_reviewer_invitation3_tags = [
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL
    ];
    public const paper_reviewer_refusal_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_REVIEWER_SUGGESTION,
        Episciences_Mail_Tags::TAG_REFUSAL_REASON,
    ];
    public const paper_reviewer_refusal_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REVIEWER_SUGGESTION,
        Episciences_Mail_Tags::TAG_REFUSAL_REASON,
    ];
    public const paper_reviewer_removal_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME
    ];
    public const paper_revision_answer_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_ANSWER,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];
    public const paper_submission_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];
    public const paper_submission_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url,
        Episciences_Mail_Tags::TAG_REFUSED_ARTICLE_MESSAGE,
        Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL,
        Episciences_Mail_Tags::TAG_SECTION_NAME,
        Episciences_Mail_Tags::TAG_VOLUME_NAME,
        Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF
    ];

    public const paper_submission_updated_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];

    public const paper_submission_updated_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];

    public const paper_suggest_acceptation_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,      // editor's message (acceptation suggestion)
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    public const paper_suggest_new_version_tags = [
        Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_EDITOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,      // editor's message (acceptation suggestion)
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    public const paper_suggest_refusal_tags = [
        Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_EDITOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,      // editor's message (acceptation suggestion)
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    public const paper_tmp_version_reviewer_reassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_TMP_PAPER_URL,
    ];
    public const paper_tmp_version_submitted_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_ANSWER,
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    public const paper_updated_rating_deadline_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // reviewer screen name (recipient)
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // reviewer full name (recipient)
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_UPDATED_DEADLINE,
    ];

    public const paper_editor_refused_monitoring_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_EDITOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];

    public const paper_new_version_reviewer_re_invitation = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_INVITATION_URL,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RATING_DEADLINE
    ];

    public const paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
    ];

    public const user_lost_login_tags = [
        Episciences_Mail_Tags::TAG_LOST_LOGINS,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN, // link
    ];
    public const user_lost_password_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_TOKEN_VALIDATION_LINK
    ];
    public const user_registration_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_TOKEN_VALIDATION_LINK
    ];

    public const reminder_after_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    public const reminder_after_deadline_reviewer_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ARTICLE_RATING_LINK,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    public const reminder_after_revision_deadline_author_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    public const reminder_after_revision_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    public const reminder_before_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_MAIL,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];

    public const reminder_before_deadline_reviewer_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ARTICLE_RATING_LINK,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    public const reminder_before_revision_deadline_author_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    public const reminder_before_revision_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_PAPER_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    public const reminder_not_enough_reviewers_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY,
        Episciences_Mail_Tags::TAG_INVITED_REVIEWERS_COUNT,
        Episciences_Mail_Tags::TAG_REQUIRED_REVIEWERS_COUNT
    ];
    public const reminder_unanswered_reviewer_invitation_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_MAIL,
        Episciences_Mail_Tags::TAG_INVITATION_DATE,
        Episciences_Mail_Tags::TAG_EXPIRATION_DATE,
        Episciences_Mail_Tags::TAG_INVITATION_LINK,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];

    public const reminder_unanswered_reviewer_invitation_reviewer_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_INVITATION_LINK,
        Episciences_Mail_Tags::TAG_INVITATION_DATE,
        Episciences_Mail_Tags::TAG_EXPIRATION_DATE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];

    public const paper_abandon_publication_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];

    public const paper_abandon_publication_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME,
        Episciences_Mail_Tags::TAG_LAST_STATUS
    ];

    public const paper_abandon_publication_reviewer_removal_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME,
    ];


    public const paper_ce_accepted_final_version_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
    ];

    public const paper_ce_accepted_final_version_copyEditor_and_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES
    ];

    public const paper_ce_author_sources_submitted_response_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];

    public const paper_ce_author_sources_submitted_response_copyEditor_and_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REQUESTER_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_COMMENT_DATE
    ];

    public const paper_ce_author_final_version_submitted_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];

    public const paper_ce_author_final_version_submitted_editor_and_copyEditor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_COMMENT_DATE
    ];

    public const paper_ce_review_formatting_submitted_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
    ];

    public const paper_ce_review_formatting_submitted_editor_and_copyEditor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES
    ];
    public const paper_ce_waiting_for_author_formatting_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE_ISO,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE_ISO,
        Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE,
        Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE_ISO,
        Episciences_Mail_Tags::TAG_LAST_REVISION_DATE,
        Episciences_Mail_Tags::TAG_LAST_REVISION_DATE_ISO,
        Episciences_Mail_Tags::TAG_REVISION_DATES,
        Episciences_Mail_Tags::TAG_REVISION_DATES_ISO,
        Episciences_Mail_Tags::TAG_DOI,
        Episciences_Mail_Tags::TAG_VOLUME_ID,
        Episciences_Mail_Tags::TAG_VOLUME_EDITORS,
        Episciences_Mail_Tags::TAG_SECTION_ID,
        Episciences_Mail_Tags::TAG_VOLUME_NAME,
        Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF,
        Episciences_Mail_Tags::TAG_SECTION_NAME,
        Episciences_Mail_Tags::TAG_PAPER_POSITION_IN_VOLUME,
        Episciences_Mail_Tags::TAG_CURRENT_YEAR,
        Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_URL,
    ];
    public const paper_ce_waiting_for_author_formatting_editor_and_copyEditor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
    ];
    public const paper_ce_waiting_for_author_sources_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    public const paper_ce_waiting_for_author_sources_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    public const paper_continue_publication_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];
    public const paper_continue_publication_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME,
        Episciences_Mail_Tags::TAG_LAST_STATUS
    ];

    public const paper_copyEditor_assign_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    public const paper_copyEditor_assign_Editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
    ];
    public const paper_copyEditor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    public const paper_copyEditor_unassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    public const paper_published_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    public const paper_refused_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    public const paper_volume_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_SECTION_NAME
    ];
    public const paper_section_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_VOLUME_NAME,
        Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF
    ];

    public const paper_suggested_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];

    public const reminder_article_blocked_in_accepted_state_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];

    public const paper_accepted_ask_authors_final_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_PAPER_RATINGS,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN
    ];

    public const paper_formatted_by_journal_waiting_author_validation_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_PAPER_RATINGS,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN

    ];

    public const reminder_reviewed_article_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];

    public const reminder_submitted_article_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];

    public const paper_author_comment_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_AUTHOR_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_AUTHOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
    ];

    public const TEMPLATE_DESCRIPTION_AND_RECIPIENT = [
        self::TYPE_USER_REGISTRATION => [self::DESCRIPTION => "confirmation et de validation d'un nouveau compte", self::RECIPIENT => "l'utilisateur qui vient de se créer un compte"],
        self::TYPE_USER_LOST_PASSWORD => [self::DESCRIPTION => 'réinitialisation de mot de passe', self::RECIPIENT => "l'utilisateur qui a oublié son mot de passe"],
        self::TYPE_USER_LOST_LOGIN => [self::DESCRIPTION => "rappel de l'identifiant de connexion", self::RECIPIENT => "l'utilisateur qui a oublié ses identifiants"],
        self::TYPE_PAPER_ACCEPTED_TMP_VERSION => [self::DESCRIPTION => "confirmation de l'acceptation de la version temporaire de l'article", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_ACCEPTED_TMP_VERSION_MANAGERS_COPY => [self::DESCRIPTION => "notification informant le comité éditorial de l'acceptation de la version temporaire de l'article", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_ACCEPTED => [self::DESCRIPTION => "confirmation de l'acceptation de l'article", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_ACCEPTED_EDITORS_COPY => [self::DESCRIPTION => "notification informant le comité éditorial de l'acceptation de l'article", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_PUBLISHED_AUTHOR_COPY => [self::DESCRIPTION => "confirmation de la publication de l'article", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_PUBLISHED_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial de la publication de l'article", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_REFUSED => [self::DESCRIPTION => "confirmation de rejet de l'article", self::RECIPIENT => self::AUTHOR_RECEP_EXP], // author copy
        self::TYPE_PAPER_REFUSED_EDITORS_COPY => [self::DESCRIPTION => "notification informant le comité éditorial du rejet de l'article", self::RECIPIENT => self::MANAGERS_RECEP_EXP], // editors copy
        self::TYPE_PAPER_REVISION_REQUEST => [self::DESCRIPTION => "non utilisé", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_MINOR_REVISION_REQUEST => [self::DESCRIPTION => "demande de modifications mineures", self::RECIPIENT => "l'auteur de l'article comme destinataire principal et en BCC (selon le paramétrage de la revue), les rédacteurs en chef, administrateurs et secrétaires de rédaction"],
        self::TYPE_PAPER_MAJOR_REVISION_REQUEST => [self::DESCRIPTION => 'demande de modifications majeures', self::RECIPIENT => "l'auteur de l'article comme destinataire principal et en BCC (selon le paramétrage de la revue), les rédacteurs en chef, administrateurs et secrétaires de rédaction"],
        self::TYPE_PAPER_UPDATED_RATING_DEADLINE => [self::DESCRIPTION => 'notification informant le relecteur de la nouvelle date limite de relecture', self::RECIPIENT => 'relecteur'],
        self::TYPE_PAPER_EDITOR_ASSIGN => [self::DESCRIPTION => "notification informant le rédacteur qu'il a été assigné à un article", self::RECIPIENT => "rédacteur assigné à l'article"],
        self::TYPE_PAPER_EDITOR_UNASSIGN => [self::DESCRIPTION => 'notification informant le rédacteur que son assignation à un article a été retirée', self::RECIPIENT => "le rédacteur dont l'assignation a été supprimée"],
        self::TYPE_PAPER_ASK_OTHER_EDITORS => [self::DESCRIPTION => "demande d'avis d'un rédacteur sur l'article", self::RECIPIENT => 'rédacteurs'],
        self::TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY => [self::DESCRIPTION => "notification informant le relecteur de la réponse de l'auteur suite à son commentaire", self::RECIPIENT => "relecteur (demandeur)"],
        self::TYPE_PAPER_COMMENT_ANSWER_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial quand un rédacteur commente un article", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_AUTHOR_COPY => [self::DESCRIPTION => "notification informant l'auteur quand un relecteur poste un commentaire sur la page de son article", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial quand un relecteur poste un commentaire sur la page de l'article", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_REVISION_ANSWER => [self::DESCRIPTION => "réponse de l'auteur à une demande de modifications émise par le comité éditorial : l'auteur ne veut pas apporter de modifications", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION => [self::DESCRIPTION => "notification informant le relecteur de sa réassignation à la nouvelle version de l'article", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_TMP_VERSION_REVIEWER_REASSIGN => [self::DESCRIPTION => "notification informant le relecteur de sa réassignation à la version temporaire de l'article", self::RECIPIENT => "tous les relecteurs assignés à l'article si l'option 'Réassigner automatiquement les mêmes relecteurs quand une nouvelle version est soumise' est activée"],
        self::TYPE_PAPER_TMP_VERSION_SUBMITTED => [self::DESCRIPTION => "réponse de l'auteur à une demande de modifications émise par le comité éditorial : l’auteur propose une version temporaire", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_NEW_VERSION_SUBMITTED => [self::DESCRIPTION => "réponse de l'auteur à une demande de modifications émise par le comité éditorial : l'auteur propose une nouvelle version", self::RECIPIENT => "tous les rédacteurs et l'article et selon le paramétrage de la revue, les rédacteurs en chef, administrateurs et secrétaires de rédaction"],
        self::TYPE_PAPER_REVIEWED_REVIEWER_COPY => [self::DESCRIPTION => "message de remerciement au relecteur, suite à une relecture terminée", self::RECIPIENT => 'relecteur'],
        self::TYPE_PAPER_REVIEWED_EDITOR_COPY => [self::DESCRIPTION => "notification prévenant les rédacteurs qu'un relecteur a terminé sa relecture", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_PAPER_DELETED_AUTHOR_COPY => [self::DESCRIPTION => "confirmation de la suppression de l'article par son auteur", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_DELETED_EDITOR_COPY => [self::DESCRIPTION => "notification informant les rédacteurs qu'un auteur a supprimé son article", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_PAPER_DELETED_REVIEWER_COPY => [self::DESCRIPTION => "notification informant les relecteurs qu'un auteur a supprimé son article", self::RECIPIENT => "tous les relecteurs assignés à l'article"],
        self::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER => [self::DESCRIPTION => "notification d'invitation d'un relecteur (déjà connu par la revue, c-a-d qu'il a déjà accepté au moins une invitation) à relire un article", self::RECIPIENT => 'relecteur (connu par la revue)'],
        self::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER => [self::DESCRIPTION => "notification d'invitation d'un nouveau relecteur (l'utilisateur n'a pas encore de profil sur le site de la revue, mais il connu par le système d'authentification du CCSD)", self::RECIPIENT => "nouveau relecteur"],
        self::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER => [self::DESCRIPTION => "notification d'invitation d'un nouveau relecteur avec un compte temporaire. Dans ce cas spécifique et moment précis de l'acceptation de l'invitation, l'utilisateur sera invité à créer un nouveau compte pour accéder à la grille d'évaluation", self::RECIPIENT => "nouveau relecteur"],
        self::TYPE_PAPER_REVIEWER_REMOVAL => [self::DESCRIPTION => "confirmation de l'annulation d'une invitation", self::RECIPIENT => "relecteur"],
        self::TYPE_PAPER_REVIEWER_ACCEPTATION_REVIEWER_COPY => [self::DESCRIPTION => "confirmation de l'acceptation de l'invitation", self::RECIPIENT => 'relecteur'],
        self::TYPE_PAPER_REVIEWER_ACCEPTATION_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial qu'un relecteur vient d'accepter une invitation", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_REVIEWER_REFUSAL_REVIEWER_COPY => [self::DESCRIPTION => "confirmation du rejet de l'invitation", self::RECIPIENT => 'relecteur'],
        self::TYPE_PAPER_REVIEWER_REFUSAL_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial d'un nouveau refus à relire un article", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_SUBMISSION_EDITOR_COPY => [self::DESCRIPTION => "notification informant les rédacteurs qu'un nouvel article a été proposé", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_PAPER_SUBMISSION_AUTHOR_COPY => [self::DESCRIPTION => "confirmation de soumission de l'article", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_NEW_VERSION_SUBMISSION_AUTHOR => [self::DESCRIPTION => "confirmation de soumission de la nouvelle version de l'article", self::RECIPIENT => self::AUTHORS_CO_AUTHORS],
        self::TYPE_PAPER_NEW_VERSION_TEMPORARY_SUBMISSION_AUTHOR => [self::DESCRIPTION => "confirmation de soumission de la nouvelle version (temporaire) de l'article", self::RECIPIENT => self::AUTHORS_CO_AUTHORS],
        self::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION => [self::DESCRIPTION => "notification de rappel du relecteur ne répondant pas à l'invitation", self::RECIPIENT => 'relecteur'],
        self::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs de la non-réponse du relecture à une invitation", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_REMINDER_BEFORE_RATING_DEADLINE_REVIEWER_VERSION => [self::DESCRIPTION => "notification de rappel de l'approche de la date de livraison de la relecture", self::RECIPIENT => 'relecteur'],
        self::TYPE_REMINDER_BEFORE_RATING_DEADLINE_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs de l'approche de la date de livraison de la relecture", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_REMINDER_AFTER_RATING_DEADLINE_REVIEWER_VERSION => [self::DESCRIPTION => "notification de rappel de dépassement de la date de livraison de la relecture", self::RECIPIENT => 'relecteur'],
        self::TYPE_REMINDER_AFTER_RATING_DEADLINE_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs de dépassement de la date de livraison de la relecture", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_AUTHOR_VERSION => [self::DESCRIPTION => "notification de rappel de l'approche de la date limite de modifications (révision)", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs de l'approche de la date limite de modifications (revision)", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_REMINDER_AFTER_REVISION_DEADLINE_AUTHOR_VERSION => [self::DESCRIPTION => "notification de rappel de dépassement de la date limite de modifications (révision)", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_REMINDER_AFTER_REVISION_DEADLINE_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs de dépassement de la date limite de modifications (revision)", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_REMINDER_NOT_ENOUGH_REVIEWERS_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs qu'il n'y a pas assez de relecteurs", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs que le statut de l'article n'a pas évolué depuis son acceptation", self::RECIPIENT => "tous les rédacteurs assignés à l'article (rédacteurs en chef)"],
        self::TYPE_PAPER_SUGGEST_ACCEPTATION => [self::DESCRIPTION => "notification informant le comité éditorial qu'un rédacteur suggère l'acceptation de l'article", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_SUGGEST_REFUSAL => [self::DESCRIPTION => "notification informant le comité éditorial qu'un rédacteur suggère le refus de l'article", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_SUGGEST_NEW_VERSION => [self::DESCRIPTION => "notification informant le comité éditorial qu'un rédacteur suggère la demande de révision (modifications) de l'article", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_ABANDON_PUBLICATION_AUTHOR_COPY => [self::DESCRIPTION => "confirmation de l'interruption du processus de publication", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_ABANDON_PUBLICATION_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial de l'interruption du processus de publication de l'article", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_ABANDON_PUBLICATION_REVIEWER_REMOVAL => [self::DESCRIPTION => "confirmation informant le relecteur de la non-nécessité de poursuivre sa relecture", self::RECIPIENT => self::REVIEWER_RECEP_EXP],
        self::TYPE_PAPER_ABANDON_PUBLICATION_BY_AUTHOR_AUTHOR_COPY => [self::DESCRIPTION => "confirmation à l'auteur de sa décision d'abandonner le processus de publication", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS => [self::DESCRIPTION => "confirmation informant le comité éditorial qu'il n'est pas nécessaire d'assigner un rédacteur a l'article", self::RECIPIENT => "selon le paramétrage de la revue, l'un des (rédacteurs en chef, administrateurs et secrétaires de rédaction) comme destinataire principal, les autres seront en CC"],
        self::TYPE_PAPER_CONTINUE_PUBLICATION_AUTHOR_COPY => [self::DESCRIPTION => "confirmation de la reprise du processus de publication", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_CONTINUE_PUBLICATION_EDITOR_COPY => [self::DESCRIPTION => "notification informant les rédacteurs de la reprise du processus de publication de l'article", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_PAPER_COPY_EDITOR_ASSIGN => [self::DESCRIPTION => "notification informant le préparateur de copie qu'il a été assigné à un article", self::RECIPIENT => "préparateur de copie assigné à l'article"],
        self::TYPE_PAPER_COPY_EDITOR_UNASSIGN => [self::DESCRIPTION => "notification informant le préparateur de copie que son assignation à un article a été retirée", self::RECIPIENT => "le préparateur de copie dont l'assignation a été supprimée"],
        self::TYPE_PAPER_COPY_EDITOR_ASSIGN_AUTHOR_COPY => [self::DESCRIPTION => "notification informant l'auteur que son article est assigné pour préparation de copie", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_COPY_EDITOR_ASSIGN_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial qu'un article vient d'être assigné pour préparation de copie", self::RECIPIENT => self::MANAGERS_COPY_EDITORS_EXCEPTED_EXP],
        self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial que l'article est en attente des sources auteur pour la préparation de copie", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_AUTHOR_COPY => [self::DESCRIPTION => "notification invitant l'auteur à déposer ses sources pour la préparation de copie", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_AUTHOR_COPY => [self::DESCRIPTION => "notification informant l'auteur de la démarche à suivre et les règles de préparation d'un document adaptées pour la préparation de copie", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_EDITOR_AND_COPYEDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial que l'article est en attente de préparation de copie par l'auteur", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial qu'un rédacteur vient d'ajouter un commentaire", self::RECIPIENT => "tous les rédacteurs (excepté celui à l'origine du commentaire) assignés à l'article et selon le paramétrage de la revue, les rédacteurs en chef, administrateurs et secrétaire de rédaction"],
        self::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_AUTHOR_COPY => [self::DESCRIPTION => "confirmation du dépôt des sources", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_COPYEDITORS_AND_EDITORS_COPY => [self::DESCRIPTION => "notification informant le comité éditorial que l'auteur vient de déposer ses sources pour la préparation de copie par la revue", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY => [self::DESCRIPTION => "confirmation du dépôt de la version formatée aux norme de la revue", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial que l'auteur vient de finir la préparation de copie", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_AUTHOR_COPY => [self::DESCRIPTION => "notification informant l'auteur que la version aux normes de la revue (mise en forme par la revue) est désormais disponible et prête à être déposée dans une archive ouverte puis sur le site Episciences de l’épi-revue", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial qu'une nouvelle version aux normes de la revue (mise en forme par la revue) est désormais disponible et prête à être publiée", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_AUTHOR_COPY => [self::DESCRIPTION => "notification informant l'auteur que la version aux normes de la revue (mise en forme par l'auteur) est désormais acceptée par la revue et prête à être déposée dans une archive ouverte puis sur le site Episciences de l’épi-revue", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_COPYEDITOR_AND_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial de l'acceptation de la version aux normes de la revue de l'article (mise en forme par l'auteur)", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_REVIEWER_PAPER_ACCEPTED_STOP_PENDING_REVIEWING => [self::DESCRIPTION => "confirmation informant le relecteur de la non-nécessité de poursuivre sa relecture suite à l'acceptation de l'article", self::RECIPIENT => self::REVIEWER_RECEP_EXP],
        self::TYPE_REVIEWER_PAPER_REVISION_REQUEST_STOP_PENDING_REVIEWING => [self::DESCRIPTION => "confirmation informant le relecteur de la non-nécessité de poursuivre sa relecture suite à la demande de révision de l'article", self::RECIPIENT => self::REVIEWER_RECEP_EXP],
        self::TYPE_REVIEWER_PAPER_REFUSED_REQUEST_STOP_PENDING_REVIEWING => [self::DESCRIPTION => "confirmation informant le relecteur de la non-nécessité de poursuivre sa relecture suite au rejet de l'article", self::RECIPIENT => self::REVIEWER_RECEP_EXP],
        self::TYPE_REVIEWER_PAPER_PUBLISHED_REQUEST_STOP_PENDING_REVIEWING => [self::DESCRIPTION => "confirmation informant le relecteur de la non-nécessité de poursuivre sa relecture suite à la publication de l'article", self::RECIPIENT => self::REVIEWER_RECEP_EXP],
        self::TYPE_PAPER_EDITOR_REFUSED_MONITORING => [self::DESCRIPTION => "notification informant le comité éditorial qu'un rédacteur refuse de suive l'article", self::RECIPIENT => "tous les rédacteurs et préparateurs de copie assignés à l'article; en CC, les rédacteurs en chef, administrateurs et secrétaire de rédaction"],
        self::TYPE_PAPER_SECTION_EDITOR_ASSIGN => [self::DESCRIPTION => "notification informant le rédacteur qu'il a été assigné automatiquement à l'article (en tant qu'éditeur de la rubrique)", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_VOLUME_EDITOR_ASSIGN => [self::DESCRIPTION => "notification informant le rédacteur qu'il a été assigné automatiquement à l'article (en tant qu'éditeur du volume)", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_SUGGESTED_EDITOR_ASSIGN => [self::DESCRIPTION => "notification informant le rédacteur qu'il a été choisi par l'auteur pour suivre son article", self::RECIPIENT => self::MANAGERS_RECEP_EXP],
        self::TYPE_PAPER_SUBMISSION_UPDATED_AUTHOR_COPY => [self::DESCRIPTION => "confirmation de la mise à jour de la version de l'article : si le processus de relecture n'est pas encore entamé (article sans relecteur, article en attente de relecture), l'auteur aura la possibilité de remplacer une version soumise antérieurement par une nouvelle", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY => [self::DESCRIPTION => "notification informant les rédacteurs de la mise à jour de la version de l'article", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY => [self::DESCRIPTION => "notification informant les rédacteurs en chef, administrateurs et secrétaire de rédaction qu'un nouvel article a été proposé", self::RECIPIENT => 'selon le paramétrage de la revue, tous les rédacteurs en chef, administrateurs et secrétaire de rédaction'],
        self::TYPE_PAPER_ACCEPTED_ASK_FINAL_AUTHORS_VERSION => [self::DESCRIPTION => "demande de modifications mineures de l'article après son acceptation (si et seulement si l'option 'Permettre la demande de revision' est autoirisée par la revue)", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_PAPER_FORMATTED_BY_JOURNAL_WAITING_AUTHOR_VALIDATION => [self::DESCRIPTION => "notification invitant l'auteur à confirmer la version aux normes de la revue (mise en forme par le préparateur de copie)", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_INBOX_PAPER_SUBMISSION_AUTHOR_COPY => [self::DESCRIPTION => "confirmation de la soumission automatique de l'article depuis le serveur de preprint", self::RECIPIENT => self::AUTHOR_RECEP_EXP],
        self::TYPE_REMINDER_REVIEWED_ARTICLE_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs que le travail de révision a été effectué par les relecteurs", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION => [self::DESCRIPTION => "notification informant les rédacteurs des articles bloqués à l'état soumis", self::RECIPIENT => self::EDITORS_RECEP_EXP],
        self::TYPE_PAPER_AUTHOR_COMMENT_EDITOR_COPY => [self::DESCRIPTION => "notification informant le comité éditorial qu'un auteur vient d'ajouter / éditer son commentaire (lettre d'accompagnement)", self::RECIPIENT => "tous les rédacteurs assignés à l'article et selon le paramétrage de la revue, les rédacteurs en chef, administrateurs et secrétaire de rédaction"],


    ];

    public const AUTOMATIC_TEMPLATES = [
        self::TYPE_REVIEWER_PAPER_REFUSED_REQUEST_STOP_PENDING_REVIEWING,
        self::TYPE_PAPER_ABANDON_PUBLICATION_AUTHOR_COPY,
        self::TYPE_PAPER_ABANDON_PUBLICATION_BY_AUTHOR_AUTHOR_COPY,
        self::TYPE_PAPER_ABANDON_PUBLICATION_EDITOR_COPY,
        self::TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS,
        self::TYPE_PAPER_ABANDON_PUBLICATION_REVIEWER_REMOVAL,
        self::TYPE_PAPER_ACCEPTED_EDITORS_COPY,
        self::TYPE_PAPER_ACCEPTED_TMP_VERSION_MANAGERS_COPY,
        self::TYPE_PAPER_ASK_OTHER_EDITORS,
        self::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_COPYEDITOR_AND_EDITOR_COPY,
        self::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_AUTHOR_COPY,
        self::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_COPYEDITORS_AND_EDITORS_COPY,
        self::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY,
        self::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY,
        self::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY,
        self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_EDITOR_AND_COPYEDITOR_COPY,
        self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_EDITOR_COPY,
        self::TYPE_PAPER_COMMENT_ANSWER_EDITOR_COPY,
        self::TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY,
        self::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_AUTHOR_COPY,
        self::TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY,
        self::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_EDITOR_COPY,
        self::TYPE_PAPER_CONTINUE_PUBLICATION_AUTHOR_COPY,
        self::TYPE_PAPER_CONTINUE_PUBLICATION_EDITOR_COPY,
        self::TYPE_PAPER_COPY_EDITOR_ASSIGN,
        self::TYPE_PAPER_COPY_EDITOR_ASSIGN_EDITOR_COPY,
        self::TYPE_PAPER_COPY_EDITOR_UNASSIGN,
        self::TYPE_PAPER_DELETED_AUTHOR_COPY,
        self::TYPE_PAPER_DELETED_EDITOR_COPY,
        self::TYPE_PAPER_DELETED_REVIEWER_COPY,
        self::TYPE_PAPER_EDITOR_ASSIGN,
        self::TYPE_PAPER_EDITOR_REFUSED_MONITORING,
        self::TYPE_PAPER_EDITOR_UNASSIGN,
        self::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION,
        self::TYPE_PAPER_PUBLISHED_EDITOR_COPY,
        self::TYPE_PAPER_REFUSED_EDITORS_COPY,
        self::TYPE_PAPER_REVIEWED_EDITOR_COPY,
        self::TYPE_PAPER_REVIEWED_REVIEWER_COPY,
        self::TYPE_PAPER_REVIEWER_ACCEPTATION_REVIEWER_COPY,
        self::TYPE_PAPER_REVIEWER_ACCEPTATION_EDITOR_COPY,
        self::TYPE_REVIEWER_PAPER_ACCEPTED_STOP_PENDING_REVIEWING,
        self::TYPE_REVIEWER_PAPER_PUBLISHED_REQUEST_STOP_PENDING_REVIEWING,
        self::TYPE_REVIEWER_PAPER_REVISION_REQUEST_STOP_PENDING_REVIEWING,
        self::TYPE_PAPER_REVIEWER_REFUSAL_EDITOR_COPY,
        self::TYPE_PAPER_REVIEWER_REFUSAL_REVIEWER_COPY,
        self::TYPE_PAPER_SECTION_EDITOR_ASSIGN,
        self::TYPE_PAPER_SUBMISSION_AUTHOR_COPY,
        self::TYPE_PAPER_SUBMISSION_EDITOR_COPY,
        self::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY,
        self::TYPE_PAPER_SUBMISSION_UPDATED_AUTHOR_COPY,
        self::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY,
        self::TYPE_PAPER_SUGGEST_ACCEPTATION,
        self::TYPE_PAPER_SUGGEST_NEW_VERSION,
        self::TYPE_PAPER_SUGGEST_REFUSAL,
        self::TYPE_PAPER_SUGGESTED_EDITOR_ASSIGN,
        self::TYPE_PAPER_VOLUME_EDITOR_ASSIGN,
        self::TYPE_REMINDER_AFTER_RATING_DEADLINE_EDITOR_VERSION,
        self::TYPE_REMINDER_AFTER_RATING_DEADLINE_REVIEWER_VERSION,
        self::TYPE_REMINDER_AFTER_REVISION_DEADLINE_AUTHOR_VERSION,
        self::TYPE_REMINDER_AFTER_REVISION_DEADLINE_EDITOR_VERSION,
        self::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION,
        self::TYPE_REMINDER_BEFORE_RATING_DEADLINE_EDITOR_VERSION,
        self::TYPE_REMINDER_BEFORE_RATING_DEADLINE_REVIEWER_VERSION,
        self::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_AUTHOR_VERSION,
        self::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_EDITOR_VERSION,
        self::TYPE_REMINDER_NOT_ENOUGH_REVIEWERS_EDITOR_VERSION,
        self::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_EDITOR_VERSION,
        self::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION,
        self::TYPE_USER_LOST_LOGIN,
        self::TYPE_USER_LOST_PASSWORD,
        self::TYPE_USER_REGISTRATION,
        self::TYPE_INBOX_PAPER_SUBMISSION_AUTHOR_COPY,
        self::TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION,
        self::TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION,
        self::TYPE_PAPER_AUTHOR_COMMENT_EDITOR_COPY
    ];

    /**
     * @param Episciences_Mail_Template $template
     * @param array|null $langs
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getTemplateForm(Episciences_Mail_Template $template, array $langs = null): Zend_Form
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
        $tags = $template->getTags();
        $tagList = '<div style="width:auto">';
        foreach ($tags as $tag) {
            $tagList .= sprintf('<span style="display: inline-block" class="label label-dark">%s</span>&nbsp;', $tag);
        }
        $tagList.= '</div>';
        $description = $translator->translate('Tags disponibles : ', $locale) . $tagList;

        foreach ($langs as $code => $lang) {

            $subform = new Zend_Form_SubForm();

            $class = 'tab-pane fade';

            if (
                $code === $defaultLang ||
                count($langs) === 1
            ) {
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
            $body->getDecorator('Description')->setOption('escape', false);
            $body->setAttribs(array('rows' => 10, 'style' => 'width:auto'));
            $subform->addElement($body);

            $form->addSubForm($subform, $code);
        }

        $defaults = self::getTemplateFormDefaults($template, $langs);
        $form->setDefaults($defaults);
        return $form;
    }

    /**
     * @throws Zend_Exception
     */
    private static function getTemplateFormDefaults(Episciences_Mail_Template $template, $langs): array
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

    public static function getDefaultList(): array
    {
        return self::getList();
    }

    /**
     * @param array $withoutKeys
     * @param int|null $rvId
     * @return array
     */
    public static function getList(array $withoutKeys = [], int $rvId = null): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(T_MAIL_TEMPLATES);

        if ($rvId) {

            try {
                $journalSettings = Zend_Registry::get('reviewSettings');
                if (
                    !isset($journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION]) ||
                    (int)$journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION] === 0) {

                    $withoutKeys = [
                        self::TYPE_PAPER_ACCEPTED_ASK_FINAL_AUTHORS_VERSION,
                        self::TYPE_PAPER_FORMATTED_BY_JOURNAL_WAITING_AUTHOR_VALIDATION
                    ];

                }
            } catch (Exception $e) {
                trigger_error($e->getMessage());
            }

            $select->where('(RVID IS NULL OR RVID = ?)', $rvId);

        } else {
            $select->where('RVID IS NULL');
        }


        if (!empty($withoutKeys)) {
            foreach ($withoutKeys as $key) {
                $select->where($db->quoteIdentifier('KEY') . ' !=  ?', $key);
            }
        }

        $select->order('TYPE');
        $select->order('POSITION');

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
     *
     * @param string $key
     * @param bool $withoutCommunTags
     * @return array
     */
    public static function getAvailableTagsByKey(string $key, bool $withoutCommunTags = false): array
    {
        $key = str_replace('custom_', '', $key); // Custom key = 'custom_' . $this->getKey();

        if (!$withoutCommunTags) {
            $tags = self::COMMON_TAGS;
        } else {
            $tags = [];
        }

        $map = [
            self::TYPE_PAPER_ACCEPTED => self::paper_accepted_tags,
            self::TYPE_PAPER_ACCEPTED_EDITORS_COPY => self::paper_accepted_editors_copy_tags,
            self::TYPE_PAPER_ACCEPTED_TMP_VERSION => self::paper_accepted_tmp_version_tags,
            self::TYPE_PAPER_ASK_OTHER_EDITORS => self::paper_ask_other_editors_tags,
            self::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_AUTHOR_COPY => self::paper_comment_from_reviewer_to_contributor_author_copy_tags,
            self::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_EDITOR_COPY => self::paper_comment_from_reviewer_to_contributor_editor_copy_tags,
            self::TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY => self::paper_comment_answer_reviewer_copy_tags,
            self::TYPE_PAPER_COMMENT_ANSWER_EDITOR_COPY => self::paper_comment_answer_reviewer_copy_tags,
            self::TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY => self::paper_comment_by_editor_copy_tags,
            self::TYPE_PAPER_DELETED_AUTHOR_COPY => self::paper_deleted_author_copy_tags,
            self::TYPE_PAPER_DELETED_EDITOR_COPY => self::paper_deleted_editor_copy_tags,
            self::TYPE_PAPER_DELETED_REVIEWER_COPY => self::paper_deleted_reviewer_copy_tags,
            self::TYPE_PAPER_EDITOR_ASSIGN => self::paper_editor_assign_tags,
            self::TYPE_PAPER_EDITOR_REFUSED_MONITORING => self::paper_editor_refused_monitoring_tags,
            self::TYPE_PAPER_EDITOR_UNASSIGN => self::paper_editor_unassign_tags,
            self::TYPE_PAPER_MAJOR_REVISION_REQUEST => self::paper_major_revision_request_tags,
            self::TYPE_PAPER_MINOR_REVISION_REQUEST => self::paper_minor_revision_request_tags,
            self::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION => self::paper_new_version_reviewer_re_invitation,
            self::TYPE_PAPER_NEW_VERSION_SUBMITTED => self::paper_new_version_submitted_tags,
            self::TYPE_PAPER_NEW_VERSION_SUBMISSION_AUTHOR => self::paper_new_version_submission_author,
            self::TYPE_PAPER_NEW_VERSION_TEMPORARY_SUBMISSION_AUTHOR => self::paper_new_version_temporary_submission_author,
            self::TYPE_PAPER_PUBLISHED_AUTHOR_COPY => self::paper_published_author_copy_tags,
            self::TYPE_PAPER_REFUSED => self::paper_refused_tags,
            self::TYPE_PAPER_REVIEWED_EDITOR_COPY => self::paper_reviewed_editor_copy_tags,
            self::TYPE_PAPER_REVIEWED_REVIEWER_COPY => self::paper_reviewed_reviewer_copy_tags,
            self::TYPE_PAPER_REVIEWER_ACCEPTATION_EDITOR_COPY => self::paper_reviewer_acceptation_editor_copy_tags,
            self::TYPE_PAPER_REVIEWER_ACCEPTATION_REVIEWER_COPY => self::paper_reviewer_acceptation_reviewer_copy_tags,
            self::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER => self::paper_reviewer_invitation1_tags,
            self::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER => self::paper_reviewer_invitation2_tags,
            self::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER => self::paper_reviewer_invitation3_tags,
            self::TYPE_REVIEWER_PAPER_ACCEPTED_STOP_PENDING_REVIEWING => self::paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            self::TYPE_REVIEWER_PAPER_PUBLISHED_REQUEST_STOP_PENDING_REVIEWING => self::paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            self::TYPE_REVIEWER_PAPER_REFUSED_REQUEST_STOP_PENDING_REVIEWING => self::paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            self::TYPE_REVIEWER_PAPER_REVISION_REQUEST_STOP_PENDING_REVIEWING => self::paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            self::TYPE_PAPER_REVIEWER_REFUSAL_EDITOR_COPY => self::paper_reviewer_refusal_editor_copy_tags,
            self::TYPE_PAPER_REVIEWER_REFUSAL_REVIEWER_COPY => self::paper_reviewer_refusal_reviewer_copy_tags,
            self::TYPE_PAPER_REVIEWER_REMOVAL => self::paper_reviewer_removal_tags,
            self::TYPE_PAPER_REVISION_ANSWER => self::paper_revision_answer_tags,
            self::TYPE_PAPER_REVISION_REQUEST => self::paper_revision_request_tags,
            self::TYPE_PAPER_SUBMISSION_AUTHOR_COPY => self::paper_submission_author_copy_tags,
            self::TYPE_PAPER_SUBMISSION_EDITOR_COPY => self::paper_submission_editor_copy_tags,
            self::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY => self::paper_submission_editor_copy_tags,
            self::TYPE_PAPER_SUBMISSION_UPDATED_AUTHOR_COPY => self::paper_submission_updated_author_copy_tags,
            self::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY => self::paper_submission_updated_editor_copy_tags,
            self::TYPE_PAPER_SUGGEST_ACCEPTATION => self::paper_suggest_acceptation_tags,
            self::TYPE_PAPER_SUGGEST_NEW_VERSION => self::paper_suggest_new_version_tags,
            self::TYPE_PAPER_SUGGEST_REFUSAL => self::paper_suggest_refusal_tags,
            self::TYPE_PAPER_TMP_VERSION_REVIEWER_REASSIGN => self::paper_tmp_version_reviewer_reassign_tags,
            self::TYPE_PAPER_TMP_VERSION_SUBMITTED => self::paper_tmp_version_submitted_tags,
            self::TYPE_PAPER_UPDATED_RATING_DEADLINE => self::paper_updated_rating_deadline_tags,
            self::TYPE_REMINDER_AFTER_RATING_DEADLINE_EDITOR_VERSION => self::reminder_after_deadline_editor_version_tags,
            self::TYPE_REMINDER_AFTER_RATING_DEADLINE_REVIEWER_VERSION => self::reminder_after_deadline_reviewer_version_tags,
            self::TYPE_REMINDER_AFTER_REVISION_DEADLINE_AUTHOR_VERSION => self::reminder_after_revision_deadline_author_version_tags,
            self::TYPE_REMINDER_AFTER_REVISION_DEADLINE_EDITOR_VERSION => self::reminder_after_revision_deadline_editor_version_tags,
            self::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_EDITOR_VERSION => self::reminder_before_revision_deadline_editor_version_tags,
            self::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_AUTHOR_VERSION => self::reminder_before_revision_deadline_author_version_tags,
            self::TYPE_REMINDER_BEFORE_RATING_DEADLINE_EDITOR_VERSION => self::reminder_before_deadline_editor_version_tags,
            self::TYPE_REMINDER_BEFORE_RATING_DEADLINE_REVIEWER_VERSION => self::reminder_before_deadline_reviewer_version_tags,
            self::TYPE_REMINDER_NOT_ENOUGH_REVIEWERS_EDITOR_VERSION => self::reminder_not_enough_reviewers_tags,
            self::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_EDITOR_VERSION => self::reminder_unanswered_reviewer_invitation_editor_version_tags,
            self::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION => self::reminder_unanswered_reviewer_invitation_reviewer_version_tags,
            self::TYPE_USER_LOST_LOGIN => self::user_lost_login_tags,
            self::TYPE_USER_LOST_PASSWORD => self::user_lost_password_tags,
            self::TYPE_USER_REGISTRATION => self::user_registration_tags,
            self::TYPE_PAPER_ABANDON_PUBLICATION_AUTHOR_COPY => self::paper_abandon_publication_author_copy_tags,
            self::TYPE_PAPER_ABANDON_PUBLICATION_BY_AUTHOR_AUTHOR_COPY => self::paper_abandon_publication_author_copy_tags,
            self::TYPE_PAPER_ABANDON_PUBLICATION_EDITOR_COPY => self::paper_abandon_publication_editor_copy_tags,
            self::TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS => self::paper_abandon_publication_editor_copy_tags,
            self::TYPE_PAPER_ABANDON_PUBLICATION_REVIEWER_REMOVAL => self::paper_abandon_publication_reviewer_removal_tags,
            self::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_AUTHOR_COPY => self::paper_ce_accepted_final_version_author_copy_tags,
            self::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_COPYEDITOR_AND_EDITOR_COPY => self::paper_ce_accepted_final_version_copyEditor_and_editor_copy_tags,
            self::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_AUTHOR_COPY => self::paper_ce_author_sources_submitted_response_author_copy_tags,
            self::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_COPYEDITORS_AND_EDITORS_COPY => self::paper_ce_author_sources_submitted_response_copyEditor_and_editor_copy_tags,
            self::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY => self::paper_ce_author_final_version_submitted_author_copy_tags,
            self::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY => self::paper_ce_author_final_version_submitted_editor_and_copyEditor_copy_tags,
            self::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_AUTHOR_COPY => self::paper_ce_review_formatting_submitted_author_copy_tags,
            self::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY => self::paper_ce_review_formatting_submitted_editor_and_copyEditor_copy_tags,
            self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_AUTHOR_COPY => self::paper_ce_waiting_for_author_formatting_author_copy_tags,
            self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_EDITOR_AND_COPYEDITOR_COPY => self::paper_ce_waiting_for_author_formatting_editor_and_copyEditor_copy_tags,
            self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_AUTHOR_COPY => self::paper_ce_waiting_for_author_sources_author_copy_tags,
            self::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_EDITOR_COPY => self::paper_ce_waiting_for_author_sources_editor_copy_tags,
            self::TYPE_PAPER_CONTINUE_PUBLICATION_AUTHOR_COPY => self::paper_continue_publication_author_copy_tags,
            self::TYPE_PAPER_CONTINUE_PUBLICATION_EDITOR_COPY => self::paper_continue_publication_editor_copy_tags,
            self::TYPE_PAPER_COPY_EDITOR_ASSIGN_AUTHOR_COPY => self::paper_copyEditor_assign_author_copy_tags,
            self::TYPE_PAPER_COPY_EDITOR_ASSIGN_EDITOR_COPY => self::paper_copyEditor_assign_Editor_copy_tags,
            self::TYPE_PAPER_COPY_EDITOR_ASSIGN => self::paper_copyEditor_assign_tags,
            self::TYPE_PAPER_COPY_EDITOR_UNASSIGN => self::paper_copyEditor_unassign_tags,
            self::TYPE_PAPER_PUBLISHED_EDITOR_COPY => self::paper_published_editor_copy_tags,
            self::TYPE_PAPER_REFUSED_EDITORS_COPY => self::paper_refused_editor_copy_tags,
            self::TYPE_PAPER_VOLUME_EDITOR_ASSIGN => self::paper_volume_editor_assign_tags,
            self::TYPE_PAPER_SECTION_EDITOR_ASSIGN => self::paper_section_editor_assign_tags,
            self::TYPE_PAPER_SUGGESTED_EDITOR_ASSIGN => self::paper_suggested_editor_assign_tags,
            self::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION => self::reminder_article_blocked_in_accepted_state_editor_version_tags,
            self::TYPE_PAPER_ACCEPTED_TMP_VERSION_MANAGERS_COPY => self::paper_accepted_tmp_version_managers_copy_tags,
            self::TYPE_PAPER_ACCEPTED_ASK_FINAL_AUTHORS_VERSION => self::paper_accepted_ask_authors_final_version_tags,
            self::TYPE_PAPER_FORMATTED_BY_JOURNAL_WAITING_AUTHOR_VALIDATION => self::paper_formatted_by_journal_waiting_author_validation_tags,
            self::TYPE_INBOX_PAPER_SUBMISSION_AUTHOR_COPY => self::paper_submission_author_copy_tags,
            self::TYPE_REMINDER_SUBMITTED_ARTICLE_EDITOR_VERSION => self::reminder_submitted_article_editor_version_tags,
            self::TYPE_REMINDER_REVIEWED_ARTICLE_EDITOR_VERSION => self::reminder_reviewed_article_editor_version_tags,
            self::TYPE_PAPER_AUTHOR_COMMENT_EDITOR_COPY => self::paper_author_comment_editor_copy_tags,
        ];

        if (array_key_exists($key, $map)) {
            $tags = array_merge($tags, $map[$key]);
        }

        return $tags;
    }

    /**
     * Clean the key by removing specific suffixes.
     * @param string $key
     * @return string
     */
    public static function cleanKey(string $key): string
    {
        return str_replace(
            [self::SUFFIX_TPL_NAME, self::SUFFIX_TPL_SUBJECT],
            '',
            $key
        );
    }

}
