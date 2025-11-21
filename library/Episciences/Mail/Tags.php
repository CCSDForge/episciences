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
    public const TAG_EDITOR_FULL_NAME = '%%EDITOR_FULL_NAME%%';
    //git #180
    public const TAG_AUTHORS_NAMES = '%%AUTHORS_NAMES%%';

    //Login oublié ? > /user/lostlogin
    public const TAG_RECIPIENT_USERNAME_LOST_LOGIN = '%%RECIPIENT_FORGOTTEN_USERNAME_LINK%%';
    // TAG obsolète : remplacé par "%%RECIPIENT_FORGOTTEN_USERNAME_LINK%%" dans des templates par défault;
    // Présent encore dans ceux déjà personnalisés
    public const TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN = '%%RECIPIENT_USERNAME_LOST_LOGIN%%';
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
    public const TAG_AUTHOR_SCREEN_NAME = '%%AUTHOR_SCREEN_NAME%%';
    // git #250
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
        self::TAG_ACTION_TIME => "heure de l'action",
        self::TAG_ALL_REVIEW_RESOURCES_LINK => 'lien vers les ressources de la revue (e.g. : https://dmtcs.episciences.org/website/public)',
        self::TAG_ANSWER => 'texte de la réponse à une demande de modifications',
        self::TAG_ARTICLE_ID => 'identifiant de l’article',
        self::TAG_ARTICLE_LINK => "lien vers la page de gestion de l'article",
        self::TAG_ARTICLE_RATING_LINK => "lien vers le formulaire à renseigner pour l’évaluation de l'article",
        self::TAG_ARTICLE_TITLE => 'titre de l’article',
        self::TAG_AUTHORS_NAMES => "les auteurs de l'article (e.g. : Rodney, Hartman ;  Bryan, Daniel et John, Walls)",
        self::TAG_COMMENT => 'commentaire du relecteur',
        self::TAG_COMMENT_DATE => 'date du commentaire du relecteur',
        self::TAG_CONTRIBUTOR_FULL_NAME => 'nom complet du déposant',
        self::TAG_CURRENT_YEAR => "l'année en cours (e.g. : 2022)",
        self::TAG_DOI => "DOI de l'article : DOI_prefix/DOI_format (e.g. : 10.46298/jdmdh.9251)",
        self::TAG_EDITOR_SCREEN_NAME => "nom d’affichage du rédacteur à l'origine de l'action (ajouter un commentaire / suggestion)",
        self::TAG_EDITOR_FULL_NAME => "nom complet du rédacteur à l'origine de l'action (ajouter un commentaire / suggestion)",
        self::TAG_EXPIRATION_DATE => "date d'expiration de l'invitation",
        self::TAG_INVITATION_DATE => "date d'envoi de l'invitation",
        self::TAG_INVITATION_DEADLINE => "date d'expiration de l'invitation",
        self::TAG_INVITATION_LINK => 'Url du formulaire de réponse à l’invitation',
        self::TAG_INVITATION_URL => 'Url du formulaire de réponse à l’invitation',
        self::TAG_INVITED_REVIEWERS_COUNT => "le nombre de relecteurs assignés à un article (invitations acceptées et en attente de réponse)",
        self::TAG_LAST_REVISION_DATE => "date de la dernière revision de l'article",
        self::TAG_LAST_REVISION_DATE_ISO => "date de la dernière révision de l'article au format ISO",
        self::TAG_LAST_STATUS => "dernier statut connu de l'article",
        self::TAG_MAIL_ACCOUNT_USERNAME_LIST => "la liste d'adresses e-mail associée à un login" ,
        self::TAG_NEW_VERSION_URL => 'lien vers la nouvelle version de l’article',
        self::TAG_PAPER_ADMINISTRATION_URL => "lien vers la page d'administration de l'article",
        self::TAG_PAPER_ID => "identifiant du document (spécifique à une version d'un article)",
        self::TAG_PAPER_POSITION_IN_VOLUME => "position de l'article dans un volume",
        self::TAG_PAPER_RATING => 'relecture de l’article',
        self::TAG_PAPER_RATINGS => 'relectures de l’article',
        self::TAG_PAPER_REPO_URL => "lien vers la page de l'article sur l'archive ouverte (e.g. : https://hal.archives-ouvertes.fr/hal-03242823v2)",
        self::TAG_PAPER_SUBMISSION_DATE => 'date de soumission de l’article',
        self::TAG_PAPER_SUBMISSION_DATE_ISO => 'date de soumission de l’article au format ISO',
        self::TAG_PAPER_URL => "lien vers la page de gestion de l’article",
        self::TAG_PERMANENT_ARTICLE_ID => "identifiant permanent d'un article (lie les différentes versions)",
        self::TAG_RATING_DEADLINE => 'date limite de rendu de relecture (traduite dans la langue du destinataire)',
        self::TAG_RECIPIENT_EMAIL => "adresse mail du destinataire",
        self::TAG_RECIPIENT_USERNAME_LOST_LOGIN  => "Lien servant à la récupération du login (e.g. : https://jtcam.episciences.org/user/lostlogin)",
        self::TAG_RECIPIENT_FULL_NAME => "nom complet du destinataire (e.g. : Gaëlle Campione)",
        self::TAG_RECIPIENT_SCREEN_NAME => "nom d’affichage du destinataire (e.g. : Gaëlle Campione)",
        self::TAG_RECIPIENT_USERNAME => "identifiant du destinataire (e.g. : gaelle_campione",
        self::TAG_REFUSAL_REASON => "motifs de refus d'un invitation à relire un article",
        self::TAG_REFUSED_ARTICLE_MESSAGE => "contient ce message : Cet article a été précédemment refusé dans sa première version, pour le consulter, merci de suivre ce lien : ",
        self::TAG_REFUSED_PAPER_URL => "lien vers la version refusée de l'article",
        self::TAG_REMINDER_DELAY => "différence, en nombre de jours, entre le moment où le rappel a été envoyé et la date limite",
        self::TAG_REQUESTER_SCREEN_NAME => "nom d'affichage de l'originaire de l'action",
        self::TAG_REQUEST_ANSWER => "réponse du contributeur",
        self::TAG_REQUEST_DATE => "date de la demande",
        self::TAG_REQUEST_MESSAGE => 'texte de la demande',
        self::TAG_REQUIRED_REVIEWERS_COUNT => 'minimum de relectures requis : nombre minimum de relectures avant de pouvoir accepter un article',
        self::TAG_REVIEWER_MAIL => 'adresse mail du relecteur',
        self::TAG_REVIEWER_FULLNAME => 'nom complet de relecteur',
        self::TAG_REVIEWER_SCREEN_NAME => "nom d'affichage du relecteur",
        self::TAG_REVIEWER_SUGGESTION => 'texte (suggestions) du relecteur',
        self::TAG_REVIEW_CE_RESOURCES_NAME => "nom du dossier zippé de l'ensemble de fichiers de style et les règles de préparation d'un document (e.g. : dmtcs_episciences.zip)",
        self::TAG_REVIEW_CE_RESOURCES_URL => "lien vers les fichiers de style et les règles de préparation d'un document",
        self::TAG_REVIEW_CODE => 'code de la revue (e.g. : JDMDH)',
        self::TAG_REVIEW_NAME => 'nom de la revue (e.g. : Journal of Data Mining and Digital Humanities)',
        self::TAG_REVISION_DATES => 'dates de révision',
        self::TAG_REVISION_DATES_ISO => 'dates de révision au format ISO',
        self::TAG_REVISION_DEADLINE => 'date limite de révision',
        self::TAG_SECTION_ID => 'ID de la rubrique',
        self::TAG_SECTION_NAME => 'nom de la rubrique',
        self::TAG_SENDER_EMAIL => 'adresse mail de l’utilisateur à l’origine de l’action',
        self::TAG_SENDER_FULL_NAME => 'nom complet de l’utilisateur à l’origine de l’action',
        self::TAG_SENDER_SCREEN_NAME => "nom d'affichage de l’utilisateur à l’origine de l’action",
        self::TAG_SUBMISSION_DATE => 'date de soumission de l’article',
        self::TAG_SUBMISSION_DATE_ISO => 'date de soumission de l’article au format ISO',
        self::TAG_VOL_BIBLIOG_REF => 'référence bibliographique du volume',
        self::TAG_TMP_PAPER_URL => "lien vers la page de gestion de la version temporaire d'un article",
        self::TAG_TOKEN_VALIDATION_LINK => "lien servant à la validation du compte",
        self::TAG_UPDATED_DEADLINE => 'nouvelle date limite de rendu de relecture',
        self::TAG_VOLUME_EDITORS => "tous les rédacteurs assignés au volume de l'article (e.g. : Hartman Rodney, Daniel Bryan, Walls John)",
        self::TAG_VOLUME_ID => "identifiant du volume de l'article",
        self::TAG_VOLUME_NAME => "nom du volume de l'article",
        self::TAG_AUTHOR_FULL_NAME => "nom d'affichage de l’auteur",
        self::TAG_AUTHOR_SCREEN_NAME => 'nom complet de l’auteur'
    ];
}
