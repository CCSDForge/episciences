<?php
return array(

    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES] => "Copy ed.: waiting for author's sources",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_CE_AUTHOR_SOURCES_DEPOSED] => 'Copy ed.: waiting for formatting by the journal',
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION] => "Copy ed.: waiting for author's final version",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED] => 'Copy ed.: final version submitted, waiting for validation',
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_CE_REVIEW_FORMATTING_DEPOSED] => 'Copy ed.: formatting by journal completed, waiting for a final version',
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_CE_AUTHOR_FORMATTING_DEPOSED] => "Copy ed.: formatting by author completed, waiting for final version",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_CE_READY_TO_PUBLISH] => 'Copy ed.: ready to publish',
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION] => "Accepted - waiting for author's validation",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION] => 'Accepted - waiting for final publication',


    Episciences_Mail_TemplatesManager::AUTHOR_RECEP_EXP => 'Article’s tenderer',
    Episciences_Mail_TemplatesManager::MANAGERS_RECEP_EXP => "all editors and copy editors assigned to the article and, depending on the journal's settings, editors, administrators and editorial secretaries",
    Episciences_Mail_TemplatesManager::EDITORS_RECEP_EXP => "all editors assigned to the article",
    Episciences_Mail_TemplatesManager::MANAGERS_COPY_EDITORS_EXCEPTED_EXP => "all editors assigned to the article and, depending on the journal's settings, editors, administrators and editorial secretaries",
    Episciences_Mail_TemplatesManager::REVIEWER_RECEP_EXP => "all reviewers assigned to the article whose review has not yet been completed",

    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE] => "date of acceptance of an article",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE_ISO] => "date d'acceptation d'un article in ISO format",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ACTION_DATE] => "action date",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ACTION_TIME] => "action time",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ALL_REVIEW_RESOURCES_LINK] => "link to journal's resources(e . g . https://dmtcs.episciences.org/website/public)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ANSWER] => "text of the answer to a request for modifications",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ARTICLE_ID] => 'article ID',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ARTICLE_LINK] => "link to the article's management page",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ARTICLE_RATING_LINK] => "link to the form to fill in for the article's review",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_ARTICLE_TITLE] => 'article title',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_AUTHORS_NAMES] => "the authors of the article (e.g. Rodney, Hartman ;  Bryan, Daniel and John, Walls)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_COMMENT] => 'reviewer comment',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_COMMENT_DATE] => 'reviewer comments date',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME] => "tenderer First Name and Last Name",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_CURRENT_YEAR] => "current year (e.g. 2022)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_DOI] => "article's DOI: DOI_prefix/DOI_format (e.g. 10.46298/jdmdh.9251)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME] => "editor's Screen Name at the origin of the action (add a comment / suggestion)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_EDITOR_FULL_NAME] => "editor's First Name and Last Name at the origin of the action (add a comment / suggestion)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_EXPIRATION_DATE] => "expiration date of invitation",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_INVITATION_DATE] => "date of sending the invitation",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_INVITATION_DEADLINE] => "expiration date of invitation",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_INVITATION_LINK] => 'invitation response form URL',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_INVITATION_URL] => 'invitation response form URL',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_INVITED_REVIEWERS_COUNT] => "the number of reviewers assigned to an article (invitations accepted and pending)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_LAST_REVISION_DATE] => "date of the last revision of the article",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_LAST_REVISION_DATE_ISO] => "date of the last revision of the article in ISO format",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_LAST_STATUS] => "last known status of the article",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_MAIL_ACCOUNT_USERNAME_LIST] => "Account User List",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_NEW_VERSION_URL] => 'link to article’s new version',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_ADMINISTRATION_URL] => "link to the article's management page",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_ID] => "document ID (specific to an article version)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_POSITION_IN_VOLUME] => "position of the article in a volume",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_RATING] => "article's reviewer",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_RATINGS] => "article's reviewers",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_REPO_URL] => "link to the open access repository (e.g. https://hal.science/hal-03242823v2)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE] => 'submission date',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE_ISO] => "submission date at ISO format",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PAPER_URL] => "Link to article URL",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID] => "permanent article ID (links all versions)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_RATING_DEADLINE] => "deadline for the review (translated into the recipient's language)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL] => "Recipient's email address",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN] => "Link used for login recovery (e.g. https://jtcam.episciences.org/user/lostlogin)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME] => "recipient First Name and Last Name (e.g. Gaëlle Sample)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME] => "Recipient Screen Name (e.g. Gaëlle Sample)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME] => "recipient ID (e.g. gaelle_sample)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REFUSAL_REASON] => "reasons for refusing an invitation to review an article",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REFUSED_ARTICLE_MESSAGE] => "contains this message: This article was previously rejected in its first version. To review it, please follow this link: ",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL] => "link to the rejected version of the article",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REMINDER_DELAY] => "difference, in number of days, between the time the reminder was sent and the deadline",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REQUESTER_SCREEN_NAME] => "Screen Name of the requester of the action",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REQUEST_ANSWER] => "contributor's response",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REQUEST_DATE] => "request date",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REQUEST_MESSAGE] => 'request message',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REQUIRED_REVIEWERS_COUNT] => 'minimum review count: minimum reviews count required for accepting an article',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEWER_MAIL] => "reviewer's email",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME] => "reviewer's First Name and Last Name",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEWER_SCREEN_NAME] => "reviewer's Screen Name",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEWER_SUGGESTION] => "reviewer's suggestions (text)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_NAME] => "zipped folder name of the set of style files and the rules for copy editing(e.g. dmtcs_episciences.zip)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_URL] => "link to style files and document preparation rules",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEW_CODE] => "journal code (e.g. JDMDH)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVIEW_NAME] => "Journal name (e.g. Journal of Data Mining and Digital Humanities)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVISION_DATES] => 'revision dates',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVISION_DATES_ISO] => 'revision deadline in ISO format',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_REVISION_DEADLINE] => 'revision deadline',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_SECTION_ID] => 'section ID',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_SECTION_NAME] => 'section name',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_SENDER_EMAIL] => 'Sender’s email',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_SENDER_FULL_NAME] => "Sender’s First Name and Last Name",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME] => "Screen Name of the user who initiated the action",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_SUBMISSION_DATE] => 'submission date',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_SUBMISSION_DATE_ISO] => 'submission date in ISO format',
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF] => "volume's bibliographical reference",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_TMP_PAPER_URL] => "link to the article's temporary version management page",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_TOKEN_VALIDATION_LINK] => "account activation link",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_UPDATED_DEADLINE] => "new review deadline",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_VOLUME_EDITORS] => "all editors assigned to the volume of the article (e.g. Hartman Rodney, Daniel Bryan, Walls John)",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_VOLUME_ID] => "volume ID",
    Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_VOLUME_NAME] => "volume name",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_USER_REGISTRATION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification of confirmation and validation of a new account",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_USER_REGISTRATION][Episciences_Mail_TemplatesManager::RECIPIENT] => "User who’s just created an account",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_USER_LOST_PASSWORD][Episciences_Mail_TemplatesManager::DESCRIPTION] => "password reset",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_USER_LOST_PASSWORD][Episciences_Mail_TemplatesManager::RECIPIENT] => "user who forgot his/her password",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_USER_LOST_LOGIN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "login ID reminder",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_USER_LOST_LOGIN][Episciences_Mail_TemplatesManager::RECIPIENT] => "user who forgot his/her login",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_TMP_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of acceptance of article's temporary version",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_TMP_VERSION_MANAGERS_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee of the acceptance of article's temporary version ",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED][Episciences_Mail_TemplatesManager::DESCRIPTION] => "article acceptance confirmation",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_EDITORS_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee of the acceptance of the article",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_PUBLISHED_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "article publication confirmation",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_PUBLISHED_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee of the publication of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REFUSED][Episciences_Mail_TemplatesManager::DESCRIPTION] => "article rejection confirmation",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REFUSED_EDITORS_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee of the rejection of the article",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVISION_REQUEST][Episciences_Mail_TemplatesManager::DESCRIPTION] => "not used",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_MINOR_REVISION_REQUEST][Episciences_Mail_TemplatesManager::DESCRIPTION] => "minor revision request",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_MINOR_REVISION_REQUEST][Episciences_Mail_TemplatesManager::RECIPIENT] => "the author of the article as the main recipient and in BCC (depending on the journal's settings), the editors-in-chief, administrators and editorial secretaries",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_MAJOR_REVISION_REQUEST][Episciences_Mail_TemplatesManager::DESCRIPTION] => 'major revision request',

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_UPDATED_RATING_DEADLINE][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the reviewer of the new review deadline",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_ASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editor that has been assigned to an article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_ASSIGN][Episciences_Mail_TemplatesManager::RECIPIENT] => "editor assigned to the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_UNASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editor that his/her assignment to an article has been removed",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_UNASSIGN][Episciences_Mail_TemplatesManager::RECIPIENT] => "the editor whose assignment was removed",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ASK_OTHER_EDITORS][Episciences_Mail_TemplatesManager::DESCRIPTION] => "request for an editor's opinion on the article",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the reviewer of the author's response to his/her comment",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY][Episciences_Mail_TemplatesManager::RECIPIENT] => "reviewer (applicant)",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_ANSWER_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee when an editor comments on an article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the author when a reviewer posts a comment on their article page",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee when a reviewer posts a comment on the article page",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVISION_ANSWER][Episciences_Mail_TemplatesManager::DESCRIPTION] => "author reply to a request for modifications issued by an editor: the author does not make any changes",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the reviewer of their reassignment to the new version of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_REVIEWER_REASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the reviewer of their reassignment to the temporary version of the article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_REVIEWER_REASSIGN][Episciences_Mail_TemplatesManager::RECIPIENT] => "all assigned reviewers if the 'Automatically reassign the same reviewers when a new version is submitted' option is enabled",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_SUBMITTED][Episciences_Mail_TemplatesManager::DESCRIPTION] => "author reply to a request for modifications issued by an editor: the author suggest a temporary version",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_SUBMITTED][Episciences_Mail_TemplatesManager::RECIPIENT] => "all assigned editors and according to the journal's settings, the editors-in-chief, administrators and sub-editors",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMITTED][Episciences_Mail_TemplatesManager::DESCRIPTION] => "author reply to a request for modifications issued by an editor in chief: the author suggest a new version",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWED_REVIEWER_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "message of thanks to the reviewer, following a complete review",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWED_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification warning editors a reviewer has completed its review",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of removal of an article by its author",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editors that an author has deleted his/her article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_REVIEWER_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the reviewers that an author has deleted his/her article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_REVIEWER_COPY][Episciences_Mail_TemplatesManager::RECIPIENT] => "all assigned reviewers",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER][Episciences_Mail_TemplatesManager::DESCRIPTION] => "invitation notification from a reviewer (already known to the journal, i.e. they have already accepted at least one review invitation) to review an article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER][Episciences_Mail_TemplatesManager::RECIPIENT] => 'reviewer (known by the journal)',

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification of invitation of a new reviewer (the user does not yet have a profile in the journal's website, but he/she is known by the CCSD authentication system)",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER][Episciences_Mail_TemplatesManager::RECIPIENT] => "new reviewer",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification of invitation of a new reviewer with a temporary account. In this specific case and precise moment of the acceptance of the invitation, the user will be invited to create a new account to access the review grid",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REMOVAL][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of the cancellation of an invitation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_ACCEPTATION_REVIEWER_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of acceptance of the invitation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_ACCEPTATION_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that a reviewer has just accepted an invitation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REFUSAL_REVIEWER_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of invitation rejection",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REFUSAL_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that a reviewer has just refused an invitation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editors that a new article has been submitted",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "article’s submission confirmation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "reminder notification of reviewer not responding to invitation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_EDITOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing editors of the non-response of the reviewer to an invitation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_RATING_DEADLINE_REVIEWER_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "reminder notification of approaching reviewing deadline",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_RATING_DEADLINE_EDITOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing editors of approaching reviewing deadline",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_RATING_DEADLINE_REVIEWER_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification to reviewers if reviewing deadline is exceeded",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_RATING_DEADLINE_EDITOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification to editors if reviewing deadline is exceeded",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_AUTHOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "reminder notification of approaching revision deadline",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_EDITOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing editors of approaching revision deadline",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_REVISION_DEADLINE_AUTHOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification if revision deadline is exceeded",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_REVISION_DEADLINE_EDITOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification to editors if revision deadline is exceeded",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_NOT_ENOUGH_REVIEWERS_EDITOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing editors that does not have enough reviewers ",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editors that the status of the article has not changed since its acceptance",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION][Episciences_Mail_TemplatesManager::RECIPIENT] => "all assigned editors (editors in Chief)",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_ACCEPTATION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that an editor suggests acceptance of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_REFUSAL][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that an editor suggests the rejection of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_NEW_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial board that an editor suggests the request for revision (modifications) of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of the interruption of the publication process",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee of the interruption of the publication process of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_REVIEWER_REMOVAL][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation informing the reviewer of the non-necessity of continuing his/her review",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_BY_AUTHOR_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation to the author of their decision to abandon the publication process",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation informing the editorial committee that there is no need to assign an editor to the article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS][Episciences_Mail_TemplatesManager::RECIPIENT] => "depending on the journal's settings, one of the (editors, administrators and editorial secretaries) as the main recipient, the others will be in CC",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CONTINUE_PUBLICATION_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of the resumption of the publication process",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CONTINUE_PUBLICATION_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editors of the resumption of the publication process of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the copy editor that it has been assigned to an article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN][Episciences_Mail_TemplatesManager::RECIPIENT] => "assigned copy editor",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_UNASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the copy editor that his/her assignment to an article has been removed",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_UNASSIGN][Episciences_Mail_TemplatesManager::RECIPIENT] => "the copy editor whose assignment was removed",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the author that his/her article is assigned for copy editing",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that an article has just been assigned for copy editing",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that the article is awaiting author sources",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification inviting the author to submit his/her sources",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the author of the procedure to follow and the rules for preparing his/her document",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_EDITOR_AND_COPYEDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that the article is awaiting copy editing by the author",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that an editor has just added a comment",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY][Episciences_Mail_TemplatesManager::RECIPIENT] => "all assigned editors (except the one at the origin of the comment) and according to the journal's settings, the editors in chief, administrators and secretaries",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "sources submission conformation",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_COPYEDITORS_AND_EDITORS_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that the author has just submitted his/her sources for copy editing by the journal",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of submission of the version formatted to the journal's standards",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that the author has just finished formatting their article",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the author that the final version (formatted by the journal) is now available and ready to be submitted in an open archive and then on the journal's website",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that a new version to the standards of the journal (formatted by the journal) is now available and ready for publication",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the author that the version to the standards of the journal (formatted by the author) is now accepted by the journal and ready to be deposited in an open archive and then on the journal's website",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_COPYEDITOR_AND_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee of the acceptance of the version to the standards of the journal of the article (formatted by the author)",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_ACCEPTED_STOP_PENDING_REVIEWING][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation informing the reviewer of the non-necessity of continuing his/her review following acceptance of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_REVISION_REQUEST_STOP_PENDING_REVIEWING][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation informing the reviewer of the non-necessity of continuing his/her review following the request for revision of the article",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_REFUSED_REQUEST_STOP_PENDING_REVIEWING][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation informing the reviewer of the non-necessity of continuing his/her review following the rejection of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_PUBLISHED_REQUEST_STOP_PENDING_REVIEWING][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation informing the reviewer of the non-necessity of continuing his/her review following the publication of the article",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_REFUSED_MONITORING][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editorial committee that an editor refuses to manage the article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_REFUSED_MONITORING][Episciences_Mail_TemplatesManager::RECIPIENT] => "all assigned editors and copy editors; in CC, the editors in chief, administrators and secretaries",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SECTION_EDITOR_ASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editor that he/she has been automatically assigned to the article (as editor of the section)",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_VOLUME_EDITOR_ASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editor that he/she has been automatically assigned to the article (as editor of the volume)",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGESTED_EDITOR_ASSIGN][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the editor that he/she has been chosen by the author to manage his/her article",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "confirmation of the article version update: if the review process has not yet started (article without any reviewer, article waiting for review), the author will have the opportunity to replace a previously submitted version with a new one",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing editors of article version update",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing editors in chief, administrators and secretaries that a new article has been submitted",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY][Episciences_Mail_TemplatesManager::RECIPIENT] => "depending on the journal's settings, all editors in chief, administrators and editorial secretary",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_ASK_FINAL_AUTHORS_VERSION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "request for minor modifications of the article after its acceptance (if and only if the option 'Allow post-acceptance revisions of articles' is allowed by the journal)",


    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_FORMATTED_BY_JOURNAL_WAITING_AUTHOR_VALIDATION][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification inviting the author to confirm the version to the journal's standards (formatted by the copy preparer)",

    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMISSION_AUTHOR][Episciences_Mail_TemplatesManager::DESCRIPTION] => "New version of the article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_TEMPORARY_SUBMISSION_AUTHOR][Episciences_Mail_TemplatesManager::DESCRIPTION] => "New temporary version of the article",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_INBOX_PAPER_SUBMISSION_AUTHOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => 'Confirmation of automatic article submission via a preprint server',
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_AUTHOR_COMMENT_EDITOR_COPY][Episciences_Mail_TemplatesManager::RECIPIENT] => "all editors assigned to the article and, depending on the journal's settings, editors, administrators and editorial secretaries",
    Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[Episciences_Mail_TemplatesManager::TYPE_PAPER_AUTHOR_COMMENT_EDITOR_COPY][Episciences_Mail_TemplatesManager::DESCRIPTION] => "notification informing the Editorial Board that an author has just added/edited his/her comment (cover letter) ",

    Episciences_Paper_Logger::CODE_RESTORATION_OF_STATUS => 'Restoration of status',
    Episciences_Paper_Logger::CODE_STATUS => 'New status',
    Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT => "Editor assignment",
    Episciences_Paper_Logger::CODE_EDITOR_UNASSIGNMENT => "Editor unassignment",
    Episciences_Paper_Logger::CODE_REVIEWER_INVITATION => "Reviewer invitation",
    Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_ACCEPTED => "Accepted invitation",
    Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_DECLINED => "Declined invitation",
    Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT => "Reviewer unassignment",
    Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT => "Reviewer assignment",
    Episciences_Paper_Logger::CODE_REVIEWING_IN_PROGRESS => "Ongoing review",
    Episciences_Paper_Logger::CODE_REVIEWING_COMPLETED => "Review Completed",
    Episciences_Paper_Logger::CODE_MAIL_SENT => "E-mail sent",
    Episciences_Paper_Logger::CODE_REMINDER_SENT => "Reminder sent",
    Episciences_Paper_Logger::CODE_VOLUME_SELECTION => "Moved to a volume",
    Episciences_Paper_Logger::CODE_SECTION_SELECTION => "Moved to a section",
    Episciences_Paper_Logger::CODE_MINOR_REVISION_REQUEST => 'Minor revision request',
    Episciences_Paper_Logger::CODE_MAJOR_REVISION_REQUEST => 'Major revision request',
    Episciences_Paper_Logger::CODE_REVISION_REQUEST_ANSWER => 'Revision request answer (without any modifications)',
    Episciences_Paper_Logger::CODE_REVISION_REQUEST_NEW_VERSION => 'Revision request answer (new version)',
    Episciences_Paper_Logger::CODE_REVISION_REQUEST_TMP_VERSION => 'Revision request answer (temporary version)',
    Episciences_Paper_Logger::CODE_OTHER_VOLUMES_SELECTION => "Secondary volumes assignment",
    Episciences_Paper_Logger::CODE_ALTER_REPORT_STATUS => 'Permission to change the reviewing by',
    Episciences_Paper_Logger::CODE_MONITORING_REFUSED => 'Handling of article refused',
    Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS => 'Abandon publication process',
    Episciences_Paper_Logger::CODE_COPY_EDITOR_ASSIGNMENT => "Copy editor assignment",
    Episciences_Paper_Logger::CODE_COPY_EDITOR_UNASSIGNMENT => "Copy editor unassignment",
    Episciences_Paper_Logger::CODE_CE_AUTHOR_SOURCES_REQUEST => 'Copy editing (waiting for authors sources)',
    Episciences_Paper_Logger::CODE_NEW_PAPER_COMMENT => 'New comment',
    Episciences_Paper_Logger::CODE_CE_AUTHOR_SOURCES_DEPOSED => 'Copy editing (sources submitted)',
    Episciences_Paper_Logger::CODE_CE_AUTHOR_FINALE_VERSION_REQUEST => 'Copy ed. : Pending a final author version',
    Episciences_Paper_Logger::CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED => 'Copy ed. : final version submitted',
    Episciences_Paper_Logger::CODE_CE_READY_TO_PUBLISH => 'Copy ed. : final version validated',
    Episciences_Paper_Logger::CODE_CE_REVIEW_FORMATTING_DEPOSED => 'Copy ed. formatting review completed',
    Episciences_Paper_Logger::CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED => 'Copy ed. final version submitted (new version)',
    Episciences_Paper_Logger::CODE_AUTHOR_COMMENT_COVER_LETTER => "Author comment / Cover letter",
    Episciences_Paper_Logger::CODE_EDITOR_COMMENT => "Editor comment",
    Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR => "Clarification request (reviewer to contributor)",
    Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER => "Clarification answer (contributor to reviewer)",
    Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_AUTHOR_TO_EDITOR => "Message from author to assigned editors",
    Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_EDITOR_TO_AUTHOR => "Editor response to author",
    Episciences_Paper_Logger::CODE_DOI_ASSIGNED => 'DOI assignment',
    Episciences_Paper_Logger::CODE_DOI_UPDATED => 'DOI Updated',
    Episciences_Paper_Logger::CODE_DOI_CANCELED => 'DOI canceled',
    Episciences_Paper_Logger::CODE_COI_REPORTED => 'Conflict Of Interest (COI)',
    Episciences_Paper_Logger::CODE_COI_REVERTED => 'Conflict Of Interest (COI): cancelled',
    Episciences_Paper_Logger::CODE_DD_UPLOADED => 'Data descriptor uploaded',
    Episciences_Paper_Logger::CODE_SWD_UPLOADED => 'Software descriptor uploaded',

    Episciences_Paper_Logger::CODE_PAPER_UPDATED => 'Update',
    Episciences_Paper_Logger::CODE_ALTER_PUBLICATION_DATE => 'New publication date',
    Episciences_Paper_Logger::CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION => "Accepted, ask author's final version",

    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_INFO_REQUEST] => "request for clarification",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_INFO_ANSWER] => "response for clarification",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED] => "Copy editing: final version submitted",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST] => 'Copy editing: the formatting by the journal has been completed, awaiting the final version',
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER] => 'Copy editing: final version submitted, awaiting validation',
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST] => 'Copy editing: the formatted version is accepted, waiting for the final version',
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_REVISION_REQUEST] => "revision request",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT] => "revision request response (comment)",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION] => "revision request response (temporary version)",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION] => "revision request response (new version)",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION] => "acceptance suggestion",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_SUGGESTION_REFUS] => "refusal suggestion",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION] => "revision suggestion",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED] => "refusal to follow up",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_EDITOR_COMMENT] => "editor's comment",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST] => "Copy editing: awaiting formatting by author",
    Episciences_CommentsManager::$_typeLabel[Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT] => "revision request response (without sending a new version)",

    Episciences_Paper_Logger::CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION => 'Accepted, waiting for authors validation',
    Episciences_Paper_Logger::CODE_VERSION_REPOSITORY_UPDATED => 'Version number updated',
    Episciences_Paper_Logger::CODE_NEW_REVIEWING_DEADLINE => 'New deadline for review',
    Episciences_Paper_Logger::CODE_INBOX_COAR_NOTIFY_REVIEW => 'New submission: automatically transferred from',
    Episciences_Paper_Logger::CODE_LD_ADDED => 'Related work added',
    Episciences_Paper_Logger::CODE_LD_CHANGED => 'Related work changed',
    Episciences_Paper_Logger::CODE_LD_REMOVED => 'Related work removed',
    Episciences_Paper_Logger::CODE_REVISION_DEADLINE_UPDATED => 'New revision deadline',
    Episciences_Paper_Logger::CODE_DOCUMENT_IMPORTED => 'The document has been imported',

    Episciences_Editor::TAG_SECTION_EDITOR => 'Section editor',
    Episciences_Editor::TAG_VOLUME_EDITOR => 'Volume editor',
    Episciences_Editor::TAG_SUGGESTED_EDITOR => "Suggested by author",
    Episciences_Submit::SUBMIT_DOCUMENT_LABEL => "Submit a document",

    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION] => "accepted - waiting for author's final version",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION] => "accepted, waiting for major revision",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING] => "Accepted - final version submitted, waiting for formatting by copy editors",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION] => "accepted temporary version after author's modifications",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION] => "accepted temporary version, waiting for minor revision",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION] => "accepted temporary version , waiting for major revision",
    Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_NO_REVISION] => "revision request answer: without any modifications",

);
