<?php

class Episciences_Mail_Tags
{
    // tags common to all templates
    public const TAG_REVIEW_CODE = '%%REVIEW_CODE%%';
    public const TAG_REVIEW_NAME = '%%REVIEW_NAME%%';

    // TODO: replace other tags with these ones
    // not used yet
    public const TAG_SENDER_FULL_NAME = '%%SENDER_FULL_NAME%%';
    public const TAG_SENDER_SCREEN_NAME = '%%SENDER_SCREEN_NAME%%';
    public const TAG_SENDER_EMAIL = '%%SENDER_EMAIL%%';
    public const TAG_SENDER_FIRST_NAME = '%%SENDER_FIRST_NAME%%';
    public const TAG_SENDER_LAST_NAME = '%%SENDER_LAST_NAME%%';

    public const TAG_RECIPIENT_FULL_NAME = '%%RECIPIENT_FULL_NAME%%';
    public const TAG_RECIPIENT_SCREEN_NAME = '%%RECIPIENT_SCREEN_NAME%%';
    public const TAG_RECIPIENT_USERNAME = '%%RECIPIENT_USERNAME%%';
    public const TAG_RECIPIENT_EMAIL = '%%RECIPIENT_EMAIL%%';
    public const TAG_RECIPIENT_FIRST_NAME = '%%RECIPIENT_FIRST_NAME%%';
    public const TAG_RECIPIENT_LAST_NAME = '%%RECIPIENT_LAST_NAME%%';

    public const TAG_PAPER_RATING_URL = '%%PAPER_RATING_URL%%';
    public const TAG_PAPER_VIEW_URL = '%%PAPER_VIEW_URL%%';
    public const TAG_PAPER_ADMINISTRATION_URL = '%%PAPER_ADMINISTRATION_URL%%';

    // user/create, user/lostpassword
    public const TAG_TOKEN_VALIDATION_LINK = '%%TOKEN_VALIDATION_LINK%%';
    public const TAG_MAIL_ACCOUNT_USERNAME_LIST = '%%MAIL_ACCOUNT_USERNAME_LIST%%';

    // user/lost login
    public const TAG_LOST_LOGINS = '%%MAIL_ACCOUNT_USERNAME_LIST%%';

    // administratepaper/suggeststatus
    // paper_suggest_acceptation
    // paper_suggest_refusal
    // paper_suggest_new_version

    public const TAG_ARTICLE_ID = '%%ARTICLE_ID%%';
    public const TAG_REFUSED_ARTICLE_MESSAGE = '%%REFUSED_ARTICLE_MESSAGE%%';
    public const TAG_ARTICLE_TITLE = '%%ARTICLE_TITLE%%';
    public const TAG_PAPER_ID = '%%PAPER_ID%%';
    public const TAG_SUBMISSION_DATE = '%%SUBMISSION_DATE%%';
    public const TAG_SUBMISSION_DATE_ISO = '%%SUBMISSION_DATE_ISO%%';
    public const TAG_PAPER_SUBMISSION_DATE = '%%PAPER_SUBMISSION_DATE%%';
    public const TAG_PAPER_SUBMISSION_DATE_ISO = '%%PAPER_SUBMISSION_DATE_ISO%%';
    public const TAG_NEW_VERSION_URL = '%%NEW_VERSION_URL%%';
    public const TAG_REFUSED_PAPER_URL = '%%REFUSED_PAPER_URL%%';
    public const TAG_TMP_PAPER_URL = '%%TMP_PAPER_URL%%';
    public const TAG_PAPER_URL = '%%PAPER_URL%%';
    public const TAG_ARTICLE_LINK = '%%ARTICLE_LINK%%'; // identique à "TAG_PAPER_URL", dans le ca où vous le supprimeriez pour en garder un seul, penssez bien a son remplacement dans les templates de mails par default et personnalisés.
    // Renvoie vers la page de l'article sur l'archive ouverte
    public const TAG_PAPER_REPO_URL = '%%PAPER_REPO_URL%%';
    public const TAG_COMMENT = '%%COMMENT%%';
    public const TAG_REQUESTER_SCREEN_NAME = '%%REQUESTER_SCREEN_NAME%%';
    public const TAG_COMMENT_DATE = '%%COMMENT_DATE%%';
    public const TAG_ANSWER = '%%ANSWER%%';
    public const TAG_REQUEST_DATE = '%%REQUEST_DATE%%';
    public const TAG_REQUEST_MESSAGE = '%%REQUEST_MESSAGE%%';
    public const TAG_REQUEST_ANSWER = '%%REQUEST_ANSWER%%';
    public const TAG_CONTRIBUTOR_FULL_NAME = '%%CONTRIBUTOR_FULL_NAME%%';
    public const TAG_REVIEWER_FULLNAME = '%%REVIEWER_FULLNAME%%';
    public const TAG_REVIEWER_SCREEN_NAME = '%%REVIEWER_SCREEN_NAME%%';
    public const TAG_PAPER_RATINGS = '%%PAPER_RATINGS%%';
    public const TAG_PAPER_RATING = '%%PAPER_RATING%%';
    public const TAG_RATING_DEADLINE = '%%RATING_DEADLINE%%';
    public const TAG_REVIEWER_SUGGESTION = '%%REVIEWER_SUGGESTION%%';
    public const TAG_REFUSAL_REASON = '%%REFUSAL_REASON%%';
    public const TAG_INVITATION_DEADLINE = '%%INVITATION_DEADLINE%%';
    public const TAG_INVITATION_URL = '%%INVITATION_URL%%';
    public const TAG_INVITATION_LINK = '%%INVITATION_LINK%%'; // // identique à "TAG_INVITATION_URL", dans le ca où vous le supprimeriez pour en garder un seul, penssez bien a son remplacement dans les templates de mails par default et personnalisés.
    public const TAG_INVITATION_DATE = '%%INVITATION_DATE%%';
    public const TAG_EXPIRATION_DATE = '%%EXPIRATION_DATE%%';
    public const TAG_UPDATED_DEADLINE = '%%UPDATED_DEADLINE%%';
    public const TAG_REMINDER_DELAY = '%%REMINDER_DELAY%%';
    // Ajout de Tags git #93
    // Dernier statut connu de l'article avant l'arrêt du processus de publication
    public const TAG_LAST_STATUS = '%%LAST_STATUS%%';

    // Abandonner le processus de publication
    public const TAG_ACTION_DATE = '%%ACTION_DATE%%';
    public const TAG_ACTION_TIME = '%%ACTION_TIME%%';

    public const TAG_EDITOR_SCREEN_NAME = '%%EDITOR_SCREEN_NAME%%';
    //git #180
    public const TAG_AUTHORS_NAMES = '%%AUTHORS_NAMES%%';

    //Login oublié ? > /user/lostlogin
    public const TAG_RECIPIENT_USERNAME_LOST_LOGIN = '%%RECIPIENT_FORGOTTEN_USERNAME_LINK%%';
    public const TAG_VOLUME_NAME = '%%VOLUME_NAME%%';
    public const TAG_SECTION_NAME = '%%SECTION_NAME%%';
    // date d'acceptation d'un article
    public const TAG_ACCEPTANCE_DATE = '%%ACCEPTANCE_DATE%%';
    public const TAG_ACCEPTANCE_DATE_ISO = '%%ACCEPTANCE_DATE_ISO%%';

    //Revision deadline
    public const TAG_INVITED_REVIEWERS_COUNT = '%%INVITED_REVIEWERS_COUNT%%';
    public const TAG_REQUIRED_REVIEWERS_COUNT = '%%REQUIRED_REVIEWERS_COUNT%%';
    public const TAG_ARTICLE_RATING_LINK = '%%ARTICLE_RATING_LINK%%';
    public const TAG_REVIEWER_MAIL = '%%REVIEWER_EMAIL%%';
    public const TAG_REVISION_DEADLINE = '%%REVISION_DEADLINE%%';
    public const TAG_AUTHOR_FULL_NAME = '%%AUTHOR_FULLNAME%%'; // identique à "TAG_CONTRIBUTOR_FULL_NAME", dans le ca où vous le supprimeriez pour en garder un seul, penssez bien a son remplacement dans les templates de mails par default et personnalisés.
    public const TAG_AUTHOR_EMAIL = '%%AUTHOR_EMAIL%%';
    // git #250
    public const TAG_REVISION_DATES = '%%REVISION_DATES%%'; // toutes les dates de révision
    public const TAG_REVISION_DATES_ISO = '%%REVISION_DATES_ISO%%'; // toutes les dates de révision en format ISO
    public const TAG_LAST_REVISION_DATE = '%%LAST_REVISION_DATE%%';
    public const TAG_LAST_REVISION_DATE_ISO = '%%LAST_REVISION_DATE_ISO%%';
    public const TAG_PERMANENT_ARTICLE_ID = '%%PERMANENT_ARTICLE_ID%%';
    public const TAG_VOLUME_ID = '%%VOLUME_ID%%';
    public const TAG_SECTION_ID = '%%SECTION_ID%%';
    public const TAG_PAPER_POSITION_IN_VOLUME = '%%PAPER_POSITION_IN_VOLUME%%';
    public const TAG_CURRENT_YEAR = "%%CURRENT_YEAR%%";
    public const TAG_DOI = '%%DOI%%';
    public const TAG_REVIEW_CE_RESOURCES_NAME = '%%REVIEW_CE_RESOURCES_NAME%%';
    public const TAG_REVIEW_CE_RESOURCES_URL = '%%REVIEW_CE_RESOURCES_URL%%';
    public const TAG_ALL_REVIEW_RESOURCES_LINK = '%%ALL_REVIEW_RESOURCES_LINK%%';
    public const TAG_VOL_BIBLIOG_REF = '%%TAG_VOL_BIBLIOG_REF%%';
    public const TAG_VOLUME_EDITORS = '%%VOLUME_EDITORS%%';


    public const SENDER_TAGS = [
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_FIRST_NAME,
        Episciences_Mail_Tags::TAG_SENDER_LAST_NAME,
    ];

    public const TAG_DESCRIPTION = [
        self::TAG_ACCEPTANCE_DATE => "date d'acceptation d'un article",
        self::TAG_ACCEPTANCE_DATE_ISO  => "date d'acceptation d'un article au format ISO",
        self::TAG_ACTION_DATE => "date de l'action",
        self::TAG_ACTION_TIME => "l'heure de l'action",
        self::TAG_ALL_REVIEW_RESOURCES_LINK => 'le chemin vers les ressources de la revue  ',
        self::TAG_ANSWER => 'la réponse à une demande de modifications',
        self::TAG_ARTICLE_ID => 'identifiant de l’article',
        self::TAG_ARTICLE_LINK => 'todo',
        self::TAG_ARTICLE_RATING_LINK => 'todo',
        self::TAG_ARTICLE_TITLE => 'todo',
        self::TAG_AUTHORS_NAMES => 'todo',
        self::TAG_COMMENT => 'todo',
        self::TAG_COMMENT_DATE => 'todo',
        self::TAG_CONTRIBUTOR_FULL_NAME => 'todo',
        self::TAG_CURRENT_YEAR => 'todo',
        self::TAG_DOI => 'todo',
        self::TAG_EDITOR_SCREEN_NAME => 'todo',
        self::TAG_EXPIRATION_DATE => 'todo',
        self::TAG_INVITATION_DATE => 'todo',
        self::TAG_INVITATION_DEADLINE => 'todo',
        self::TAG_INVITATION_LINK => 'todo',
        self::TAG_INVITATION_URL => 'todo',
        self::TAG_INVITED_REVIEWERS_COUNT => 'todo',
        self::TAG_LAST_REVISION_DATE => 'todo',
        self::TAG_LAST_REVISION_DATE_ISO => 'todo',
        self::TAG_LAST_STATUS => 'todo',
        self::TAG_MAIL_ACCOUNT_USERNAME_LIST => 'todo',
        self::TAG_NEW_VERSION_URL => 'todo',
        self::TAG_PAPER_ADMINISTRATION_URL => 'todo',
        self::TAG_PAPER_ID => 'todo',
        self::TAG_PAPER_POSITION_IN_VOLUME => 'todo',
        self::TAG_PAPER_RATING => 'todo',
        self::TAG_PAPER_RATINGS => 'todo',
        self::TAG_PAPER_REPO_URL => 'todo',
        self::TAG_PAPER_SUBMISSION_DATE => 'todo',
        self::TAG_PAPER_SUBMISSION_DATE_ISO => 'todo',
        self::TAG_PAPER_URL => 'todo',
        self::TAG_PERMANENT_ARTICLE_ID => 'todo',
        self::TAG_RATING_DEADLINE => 'todo',
        self::TAG_RECIPIENT_EMAIL => 'todo',
        self::TAG_RECIPIENT_USERNAME_LOST_LOGIN  => 'todo',
        self::TAG_RECIPIENT_FULL_NAME => 'todo',
        self::TAG_RECIPIENT_SCREEN_NAME => 'todo',
        self::TAG_RECIPIENT_USERNAME => 'todo',
        self::TAG_REFUSAL_REASON => 'todo',
        self::TAG_REFUSED_ARTICLE_MESSAGE => 'todo',
        self::TAG_REFUSED_PAPER_URL => 'todo',
        self::TAG_REMINDER_DELAY => 'todo',
        self::TAG_REQUESTER_SCREEN_NAME => 'todo',
        self::TAG_REQUEST_ANSWER => 'todo',
        self::TAG_REQUEST_DATE => 'todo',
        self::TAG_REQUEST_MESSAGE => 'todo',
        self::TAG_REQUIRED_REVIEWERS_COUNT => 'todo',
        self::TAG_REVIEWER_MAIL => 'todo',
        self::TAG_REVIEWER_FULLNAME => 'todo',
        self::TAG_REVIEWER_SCREEN_NAME => 'todo',
        self::TAG_REVIEWER_SUGGESTION => 'todo',
        self::TAG_REVIEW_CE_RESOURCES_NAME => 'todo',
        self::TAG_REVIEW_CE_RESOURCES_URL => 'todo',
        self::TAG_REVIEW_CODE => 'code de la revue (Ex : JDMDH)',
        self::TAG_REVIEW_NAME => 'Nom de la revue (Ex : Journal of Data Mining and Digital Humanities)',
        self::TAG_REVISION_DATES => 'todo',
        self::TAG_REVISION_DATES_ISO => 'todo',
        self::TAG_REVISION_DEADLINE => 'todo',
        self::TAG_SECTION_ID => 'todo',
        self::TAG_SECTION_NAME => 'todo',
        self::TAG_SENDER_EMAIL => 'todo',
        self::TAG_SENDER_FULL_NAME => 'todo',
        self::TAG_SENDER_SCREEN_NAME => 'todo',
        self::TAG_SUBMISSION_DATE => 'todo',
        self::TAG_SUBMISSION_DATE_ISO => 'todo',
        self::TAG_VOL_BIBLIOG_REF => 'todo',
        self::TAG_TMP_PAPER_URL => 'todo',
        self::TAG_TOKEN_VALIDATION_LINK => 'todo',
        self::TAG_UPDATED_DEADLINE => 'todo',
        self::TAG_VOLUME_EDITORS => 'todo',
        self::TAG_VOLUME_ID => 'todo',
        self::TAG_VOLUME_NAME => 'todo',
    ];
}
