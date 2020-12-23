<?php

class Episciences_Mail_Tags
{
    // tags common to all templates
    const TAG_REVIEW_CODE = '%%REVIEW_CODE%%';
    const TAG_REVIEW_NAME = '%%REVIEW_NAME%%';

    // TODO: replace other tags with these ones
    // not used yet
    const TAG_SENDER_FULL_NAME = '%%SENDER_FULL_NAME%%';
    const TAG_SENDER_SCREEN_NAME = '%%SENDER_SCREEN_NAME%%';
    const TAG_SENDER_EMAIL = '%%SENDER_EMAIL%%';
    const TAG_SENDER_FIRST_NAME = '%%SENDER_FIRST_NAME%%';
    const TAG_SENDER_LAST_NAME = '%%SENDER_LAST_NAME%%';

    const TAG_RECIPIENT_FULL_NAME = '%%RECIPIENT_FULL_NAME%%';
    const TAG_RECIPIENT_SCREEN_NAME = '%%RECIPIENT_SCREEN_NAME%%';
    const TAG_RECIPIENT_USERNAME = '%%RECIPIENT_USERNAME%%';
    const TAG_RECIPIENT_EMAIL = '%%RECIPIENT_EMAIL%%';
    const TAG_RECIPIENT_FIRST_NAME = '%%RECIPIENT_FIRST_NAME%%';
    const TAG_RECIPIENT_LAST_NAME = '%%RECIPIENT_LAST_NAME%%';

    const TAG_PAPER_RATING_URL = '%%PAPER_RATING_URL%%';
    const TAG_PAPER_VIEW_URL = '%%PAPER_VIEW_URL%%';
    const TAG_PAPER_ADMINISTRATION_URL = '%%PAPER_ADMINISTRATION_URL%%';

    // user/create, user/lostpassword
    const TAG_TOKEN_VALIDATION_LINK = '%%TOKEN_VALIDATION_LINK%%';
    const TAG_MAIL_ACCOUNT_USERNAME_LIST = '%%MAIL_ACCOUNT_USERNAME_LIST%%';

    // user/lost login
    const TAG_LOST_LOGINS = '%%MAIL_ACCOUNT_USERNAME_LIST%%';

    // administratepaper/suggeststatus
    // paper_suggest_acceptation
    // paper_suggest_refusal
    // paper_suggest_new_version

    const TAG_ARTICLE_ID = '%%ARTICLE_ID%%';
    const TAG_REFUSED_ARTICLE_MESSAGE = '%%REFUSED_ARTICLE_MESSAGE%%';
    const TAG_ARTICLE_TITLE = '%%ARTICLE_TITLE%%';
    const TAG_PAPER_ID = '%%PAPER_ID%%';
    const TAG_SUBMISSION_DATE = '%%SUBMISSION_DATE%%';
    const TAG_SUBMISSION_DATE_ISO = '%%SUBMISSION_DATE_ISO%%';
    const TAG_PAPER_SUBMISSION_DATE = '%%PAPER_SUBMISSION_DATE%%';
    const TAG_PAPER_SUBMISSION_DATE_ISO = '%%PAPER_SUBMISSION_DATE_ISO%%';
    const TAG_NEW_VERSION_URL = '%%NEW_VERSION_URL%%';
    const TAG_REFUSED_PAPER_URL = '%%REFUSED_PAPER_URL%%';
    const TAG_TMP_PAPER_URL = '%%TMP_PAPER_URL%%';
    const TAG_PAPER_URL = '%%PAPER_URL%%';
    const TAG_ARTICLE_LINK = '%%ARTICLE_LINK%%'; // identique à "TAG_PAPER_URL", dans le ca où vous le supprimeriez pour en garder un seul, penssez bien a son remplacement dans les templates de mails par default et personnalisés.
    // Renvoie vers la page de l'article sur l'archive ouverte
    const TAG_PAPER_REPO_URL = '%%PAPER_REPO_URL%%';
    const TAG_COMMENT = '%%COMMENT%%';
    const TAG_REQUESTER_SCREEN_NAME = '%%REQUESTER_SCREEN_NAME%%';
    const TAG_COMMENT_DATE = '%%COMMENT_DATE%%';
    const TAG_ANSWER = '%%ANSWER%%';
    const TAG_REQUEST_DATE = '%%REQUEST_DATE%%';
    const TAG_REQUEST_MESSAGE = '%%REQUEST_MESSAGE%%';
    const TAG_REQUEST_ANSWER = '%%REQUEST_ANSWER%%';
    const TAG_CONTRIBUTOR_FULL_NAME = '%%CONTRIBUTOR_FULL_NAME%%';
    const TAG_REVIEWER_FULLNAME = '%%REVIEWER_FULLNAME%%';
    const TAG_REVIEWER_SCREEN_NAME = '%%REVIEWER_SCREEN_NAME%%';
    const TAG_PAPER_RATINGS = '%%PAPER_RATINGS%%';
    const TAG_PAPER_RATING = '%%PAPER_RATING%%';
    const TAG_RATING_DEADLINE = '%%RATING_DEADLINE%%';
    const TAG_REVIEWER_SUGGESTION = '%%REVIEWER_SUGGESTION%%';
    const TAG_REFUSAL_REASON = '%%REFUSAL_REASON%%';
    const TAG_INVITATION_DEADLINE = '%%INVITATION_DEADLINE%%';
    const TAG_INVITATION_URL = '%%INVITATION_URL%%';
    const TAG_INVITATION_LINK = '%%INVITATION_LINK%%'; // // identique à "TAG_INVITATION_URL", dans le ca où vous le supprimeriez pour en garder un seul, penssez bien a son remplacement dans les templates de mails par default et personnalisés.
    const TAG_INVITATION_DATE = '%%INVITATION_DATE%%';
    const TAG_EXPIRATION_DATE = '%%EXPIRATION_DATE%%';
    const TAG_UPDATED_DEADLINE = '%%UPDATED_DEADLINE%%';
    const TAG_REMINDER_DELAY = '%%REMINDER_DELAY%%';
    // Ajout de Tags git #93
    // Dernier statut connu de l'article avant l'arrêt du processus de publication
    const TAG_LAST_STATUS = '%%LAST_STATUS%%';

    // Abandonner le processus de publication
    const TAG_ACTION_DATE = '%%ACTION_DATE%%';
    const TAG_ACTION_TIME = '%%ACTION_TIME%%';

    const TAG_EDITOR_SCREEN_NAME = '%%EDITOR_SCREEN_NAME%%';
    //git #180
    const TAG_AUTHORS_NAMES = '%%AUTHORS_NAMES%%';

    //Login oublié ? > /user/lostlogin
    const TAG_RECIPIENT_USERNAME_LOST_LOGIN = '%%RECIPIENT_FORGOTTEN_USERNAME_LINK%%';
    const TAG_VOLUME_NAME = '%%VOLUME_NAME%%';
    const TAG_SECTION_NAME = '%%SECTION_NAME%%';
    // date d'acceptation d'un article
    const TAG_ACCEPTANCE_DATE = '%%ACCEPTANCE_DATE%%';
    const TAG_ACCEPTANCE_DATE_ISO = '%%ACCEPTANCE_DATE_ISO%%';

    //Revision deadline
    const TAG_INVITED_REVIEWERS_COUNT = '%%INVITED_REVIEWERS_COUNT%%';
    const TAG_REQUIRED_REVIEWERS_COUNT = '%%REQUIRED_REVIEWERS_COUNT%%';
    const TAG_ARTICLE_RATING_LINK = '%%ARTICLE_RATING_LINK%%';
    const TAG_REVIEWER_MAIL = '%%REVIEWER_EMAIL%%';
    const TAG_REVISION_DEADLINE = '%%REVISION_DEADLINE%%';
    const TAG_AUTHOR_FULL_NAME = '%%AUTHOR_FULLNAME%%'; // identique à "TAG_CONTRIBUTOR_FULL_NAME", dans le ca où vous le supprimeriez pour en garder un seul, penssez bien a son remplacement dans les templates de mails par default et personnalisés.
    const TAG_AUTHOR_EMAIL = '%%AUTHOR_EMAIL%%';
    // git #250
    const TAG_REVISION_DATES = '%%REVISION_DATES%%'; // toutes les dates de révision
    const TAG_REVISION_DATES_ISO = '%%REVISION_DATES_ISO%%'; // toutes les dates de révision en format ISO
    const TAG_LAST_REVISION_DATE = '%%LAST_REVISION_DATE%%';
    const TAG_LAST_REVISION_DATE_ISO = '%%LAST_REVISION_DATE_ISO%%';
    const TAG_PERMANENT_ARTICLE_ID = '%%PERMANENT_ARTICLE_ID%%';
    const TAG_VOLUME_ID = '%%VOLUME_ID%%';
    const TAG_SECTION_ID = '%%SECTION_ID%%';
    const TAG_PAPER_POSITION_IN_VOLUME = '%%PAPER_POSITION_IN_VOLUME%%';
    const TAG_CURRENT_YEAR = "%%CURRENT_YEAR%%";
    const TAG_DOI = '%%DOI%%';
    const TAG_REVIEW_CE_RESOURCES_NAME = '%%REVIEW_CE_RESOURCES_NAME%%';
    const TAG_REVIEW_CE_RESOURCES_URL = '%%REVIEW_CE_RESOURCES_URL%%';
    const TAG_ALL_REVIEW_RESOURCES_LINK = '%%ALL_REVIEW_RESOURCES_LINK%%';
    const TAG_VOL_BIBLIOG_REF = '%%TAG_VOL_BIBLIOG_REF%%';

}
