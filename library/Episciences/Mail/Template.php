<?php

class Episciences_Mail_Template
{
    protected $_id;
    protected $_parentId;
    /* @var integer journal id */
    protected $_rvid;
    protected $_rvcode;
    protected $_key;
    protected $_type;

    protected $_translations;
    protected $_body;
    protected $_name;
    protected $_subject;

    protected $_locale;
    protected $_defaultLanguage = 'en';

    /**
     * fetch the template translations folder path (custom or default)
     * @return string
     */
    public function getTranslationsFolder()
    {
        return ($this->isCustom()) ? $this->getReviewTranslationsFolder() : $this->getDefaultTranslationsFolder();
    }

    public function getDefaultTranslationsFolder()
    {
        return APPLICATION_PATH . '/languages/';
    }

    public function getReviewTranslationsFolder()
    {
        return realpath(APPLICATION_PATH . '/../data/' . $this->getRvcode()) . '/languages/';
    }

    // available in all templates
    protected array $_tags = [
        Episciences_Mail_Tags::TAG_REVIEW_CODE,
        Episciences_Mail_Tags::TAG_REVIEW_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
    ];

    // obsolete templates ?
    // protected $_paper_comment_answer_editor_copy_tags = [];
    // protected $_paper_comment_author_copy_tags = [];
    // protected $_paper_comment_editor_copy_tags = [];
    // protected $_paper_published_editor_copy_tags = [];

    // paper status change
    protected array $_paper_accepted_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
    ];

    protected array $_paper_accepted_editors_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
        Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_NAME,
        Episciences_Mail_Tags::TAG_ALL_REVIEW_RESOURCES_LINK
    ];

    protected array $_paper_accepted_tmp_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
    ];

    protected array $_paper_accepted_tmp_version_managers_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_REQUESTER_SCREEN_NAME
    ];

    protected array $_paper_ask_other_editors_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_ADMINISTRATION_URL
    ];
    protected array $_paper_published_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    protected array $_paper_refused_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    protected array $_paper_revision_request_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    protected array $_paper_major_revision_request_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];
    protected array $_paper_minor_revision_request_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_RATING
    ];

    protected array $_paper_commnet_by_edditor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
    ];

    protected array $_paper_comment_answer_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_COMMENT_DATE,
        Episciences_Mail_Tags::TAG_ANSWER,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];

    protected array $_paper_comment_from_reviewer_to_contributor_author_copy_tags = [
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_COMMENT_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN
    ];

    protected array $_paper_comment_from_reviewer_to_contributor_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_COMMENT_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN
    ];
    protected array $_paper_deleted_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    protected array $_paper_deleted_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME, // = sender full name
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    protected array $_paper_deleted_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME, // = sender full name
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    protected array $_paper_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    protected array $_paper_editor_unassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    protected array $_paper_new_version_reviewer_reassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_NEW_VERSION_URL,
    ];
    protected array $_paper_new_version_submitted_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    protected array $_paper_reviewed_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_PAPER_RATING,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL // paper administration page url
    ];
    protected array $_paper_reviewed_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL // rating page url
    ];
    protected array $_paper_reviewer_acceptation_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_SCREEN_NAME,
    ];
    protected array $_paper_reviewer_acceptation_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_PAPER_URL,

    ];

    protected array $_paper_reviewer_invitation1_tags = [
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL
    ];
    protected array $_paper_reviewer_invitation2_tags = [
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL
    ];
    protected array $_paper_reviewer_invitation3_tags = [
        Episciences_Mail_Tags::TAG_RATING_DEADLINE,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL
    ];
    protected array $_paper_reviewer_refusal_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_REVIEWER_SUGGESTION,
        Episciences_Mail_Tags::TAG_REFUSAL_REASON,
    ];
    protected array $_paper_reviewer_refusal_reviewer_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REVIEWER_SUGGESTION,
        Episciences_Mail_Tags::TAG_REFUSAL_REASON,
    ];
    protected array $_paper_reviewer_removal_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME
    ];
    protected array $_paper_revision_answer_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_ANSWER,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];
    protected array $_paper_submission_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];
    protected array $_paper_submission_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url,
        Episciences_Mail_Tags::TAG_REFUSED_ARTICLE_MESSAGE,
        Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL,
        Episciences_Mail_Tags::TAG_SECTION_NAME,
        Episciences_Mail_Tags::TAG_VOLUME_NAME,
        Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF
    ];

    protected array $_paper_submission_updated_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];

    protected array $_paper_submission_updated_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
    ];

    protected array $_paper_suggest_acceptation_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,      // editor's message (acceptation suggestion)
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    protected array $_paper_suggest_new_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,      // editor's message (acceptation suggestion)
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    protected array $_paper_suggest_refusal_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,  // editor screen name (sender screen name)
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_COMMENT,      // editor's message (acceptation suggestion)
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    protected array $_paper_tmp_version_reviewer_reassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_TMP_PAPER_URL,
    ];
    protected array $_paper_tmp_version_submitted_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // recipient screen name
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // recipient full name
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_ANSWER,
        Episciences_Mail_Tags::TAG_PAPER_URL,    // paper management page url
    ];
    protected array $_paper_updated_rating_deadline_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,  // reviewer screen name (recipient)
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,    // reviewer full name (recipient)
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_UPDATED_DEADLINE,
    ];

    protected array $_paper_editor_refused_monitoring_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_SENDER_EMAIL,
        Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
        Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_COMMENT,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];

    protected array $_paper_new_version_reviewer_re_invitation = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_INVITATION_URL,
        Episciences_Mail_Tags::TAG_INVITATION_DEADLINE,
        Episciences_Mail_Tags::TAG_RATING_DEADLINE
    ];

    protected array $_paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
    ];

    protected array $_user_lost_login_tags = [
        Episciences_Mail_Tags::TAG_LOST_LOGINS,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN, // link
    ];
    protected array $_user_lost_password_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_TOKEN_VALIDATION_LINK
    ];
    protected array $_user_registration_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_TOKEN_VALIDATION_LINK
    ];

    protected array $_reminder_after_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    protected array $_reminder_after_deadline_reviewer_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ARTICLE_RATING_LINK,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    protected array $_reminder_after_revision_deadline_author_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    protected array $_reminder_after_revision_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    protected array $_reminder_before_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_MAIL,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];

    protected array $_reminder_before_deadline_reviewer_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ARTICLE_RATING_LINK,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    protected array $_reminder_before_revision_deadline_author_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    protected array $_reminder_before_revision_deadline_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_PAPER_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];
    protected array $_reminder_not_enough_reviewers_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY,
        Episciences_Mail_Tags::TAG_INVITED_REVIEWERS_COUNT,
        Episciences_Mail_Tags::TAG_REQUIRED_REVIEWERS_COUNT
    ];
    protected array $_reminder_unanswered_reviewer_invitation_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME,
        Episciences_Mail_Tags::TAG_REVIEWER_MAIL,
        Episciences_Mail_Tags::TAG_INVITATION_DATE,
        Episciences_Mail_Tags::TAG_EXPIRATION_DATE,
        Episciences_Mail_Tags::TAG_INVITATION_LINK,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];

    protected array $_reminder_unanswered_reviewer_invitation_reviewer_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_INVITATION_LINK,
        Episciences_Mail_Tags::TAG_INVITATION_DATE,
        Episciences_Mail_Tags::TAG_EXPIRATION_DATE,
        Episciences_Mail_Tags::TAG_REMINDER_DELAY
    ];

    protected array $_paper_abandon_publication_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];

    protected array $_paper_abandon_publication_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME,
        Episciences_Mail_Tags::TAG_LAST_STATUS
    ];

    protected array $_paper_abandon_publication_reviewer_removal_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME,
    ];


    protected array $_paper_ce_accepted_final_version_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
    ];

    protected array $_paper_ce_accepted_final_version_copyEditor_and_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES
    ];

    protected array $_paper_ce_author_sources_submitted_response_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];

    protected array $_paper_ce_author_sources_submitted_response_copyEditor_and_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REQUESTER_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_COMMENT_DATE
    ];

    protected array $_paper_ce_author_final_version_submitted_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];

    protected array $_paper_ce_author_final_version_submitted_editor_and_copyEditor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_REQUEST_MESSAGE,
        Episciences_Mail_Tags::TAG_REQUEST_DATE,
        Episciences_Mail_Tags::TAG_COMMENT_DATE
    ];

    protected array $_paper_ce_review_formatting_submitted_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
    ];

    protected array $_paper_ce_review_formatting_submitted_editor_and_copyEditor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES
    ];
    protected array $_paper_ce_waiting_for_author_formatting_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
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
        Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID,
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
    protected array $_paper_ce_waiting_for_author_formatting_editor_and_copyEditor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
    ];
    protected array $_paper_ce_waiting_for_author_sources_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    protected array $_paper_ce_waiting_for_author_sources_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    protected array $_paper_continue_publication_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME
    ];
    protected array $_paper_continue_publication_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_DATE,
        Episciences_Mail_Tags::TAG_ACTION_TIME,
        Episciences_Mail_Tags::TAG_LAST_STATUS
    ];

    protected array $_paper_copyEditor_assign_author_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE
    ];
    protected array $_paper_copyEditor_assign_Editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
    ];
    protected array $_paper_copyEditor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    protected array $_paper_copyEditor_unassign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    protected array $_paper_published_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    protected array $_paper_refused_editor_copy_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];
    protected array $_paper_volume_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_SECTION_NAME
    ];
    protected array $_paper_section_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_VOLUME_NAME,
        Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF
    ];

    protected array $_paper_suggested_editor_assign_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_PAPER_URL
    ];

    protected array $_reminder_article_blocked_in_accepted_state_editor_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_EMAIL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_LINK,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE
    ];

    protected array $_paper_accepted_ask_authors_final_version_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_PAPER_RATINGS,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
        Episciences_Mail_Tags::TAG_REVISION_DEADLINE,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN
    ];

    protected array $_paper_formatted_by_journal_waiting_author_validation_tags = [
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
        Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
        Episciences_Mail_Tags::TAG_ARTICLE_ID,
        Episciences_Mail_Tags::TAG_ARTICLE_TITLE,
        Episciences_Mail_Tags::TAG_AUTHORS_NAMES,
        Episciences_Mail_Tags::TAG_SUBMISSION_DATE,
        Episciences_Mail_Tags::TAG_PAPER_URL,
        Episciences_Mail_Tags::TAG_PAPER_RATINGS,
        Episciences_Mail_Tags::TAG_PAPER_REPO_URL,
        Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN

    ];

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
        if (!$this->getLocale()) {
            $this->setLocale($this->_defaultLanguage);
        }
    }

    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            } else {
                echo "La méthode $value n'existe pas<br/>";
            }
        }

        return $this;
    }

    public function setLocale($locale): \Episciences_Mail_Template
    {
        $availableLanguages = Episciences_Tools::getLanguages();

        if (array_key_exists($locale, $availableLanguages)) {
            // La langue choisie est disponible
            $this->_locale = $locale;
        } // Si elle n'est pas dispo, on regarde si la langue par défaut est dispo
        elseif (array_key_exists($this->_defaultLanguage, $availableLanguages)) {
            $this->_locale = $this->_defaultLanguage;
        } // Sinon, on prend la première langue dispo dans l'appli
        else {
            reset($availableLanguages);
            $this->_locale = key($availableLanguages);
        }
        return $this;
    }

    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * fetch a template from a given id, and populate it from database
     * @param int $id
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function find(int $id): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()->from(T_MAIL_TEMPLATES)->where('ID = ? ', $id);
        $template = $select->query()->fetch();

        if ($template) {
            $this->populate($template);
            return true;
        }

        return false;
    }


    /**
     * fetch a template from a given key, and populate it from database
     * @param string $key
     * @return bool
     * @throws Zend_Exception
     */
    public function findByKey(string $key): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$this->getRvcode()) {
            throw new Zend_Exception("Template could not be found because rvcode is missing");
        }

        // fetch custom template from database (if found)
        $sql = $db->select()->from(T_MAIL_TEMPLATES)->where('`KEY` = ? ', 'custom_' . $key)->where('RVCODE = ?', $this->getRvcode());
        $template = $db->fetchRow($sql);

        if ($template) {
            $this->populate($template);
            return true;
        }

        // fetch default template from database (if found)
        $sql = $db->select()->from(T_MAIL_TEMPLATES)->where('`KEY` = ? ', $key);
        $template = $db->fetchRow($sql);
        if ($template) {
            $this->populate($template);
            return true;
        }

        return false;
    }

    /**
     * populate Template object from a given array of data
     * @param array $data
     * @return bool
     */
    private function populate(array $data): bool
    {
        if ($data) {
            $this->setId($data['ID']);
            $this->setParentId($data['PARENTID']);
            $this->setRvcode($data['RVCODE']);
            $this->setKey($data['KEY']);
            $this->setType($data['TYPE']);
            // $this->loadTranslations();
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        $fields = [
            'id',
            'parentId',
            'rvcode',
            'key',
            'type'
        ];
        foreach ($fields as $key) {
            $method = 'get' . ucfirst($key);
            if (method_exists($this, $method)) {
                $result[$key] = $this->$method();
            }
        }
        $result['subject'] = $this->getSubjectTranslations();
        $result['name'] = $this->getNameTranslations();
        $result['body'] = $this->getBodyTranslations();

        return $result;
    }

    /**
     * save custom template to database
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$this->getParentid()) {
            // Nouveau template personnalisé
            $this->setParentid($this->getId());
            $key = 'custom_' . $this->getKey();
            $values = [
                'PARENTID' => $this->getParentid(),
                'RVID' => $this->getRvid(),
                'RVCODE' => $this->getRvcode(),
                'KEY' => $key,
                'TYPE' => $this->getType()
            ];

            if (!$db->insert(T_MAIL_TEMPLATES, $values)) {
                return false;
            }
        } else {
            // Modification d'un template personnalisé
            $key = $this->getKey();
        }

        $result = true;

        // Ecriture des traductions ********************************

        // Récupération du fichier de traduction
        $translations = Episciences_Tools::getOtherTranslations($this->getTranslationsFolder(), 'mails.php', '#^' . $key . '#');

        // Traductions du nom du template
        $name = $this->getNameTranslations();

        if (empty($name)) {
            $result = false;
            trigger_error('TEMPLATE::SAVE_GET_NAME_TRANSLATIONS_IS_EMPTY');
        }

        foreach ($name as $lang => $translation) {
            $translations[$lang][$key . '_tpl_name'] = $translation;
        }

        // Traductions du sujet du template
        $subject = $this->getSubjectTranslations();

        if (empty($subject)) {
            $result = false;
            trigger_error('TEMPLATE::SAVE_GET_SUBJECT_TRANSLATIONS_IS_EMPTY');
        }

        foreach ($subject as $lang => $translation) {
            $translations[$lang][$key . '_mail_subject'] = $translation;
        }

        // Mise à jour du fichier de traduction
        if (Episciences_Tools::writeTranslations($translations, $this->getTranslationsFolder(), 'mails.php') < 1) {
            trigger_error('UPDATING_THE_TRANSLATION_FILE_TOTAL_BYTES_WRITTEN_IS_EMPTY');
        }

        // Création du template dans ses différentes langues
        $body = $this->getBodyTranslations();

        if (empty($body)) {
            $result = false;
            trigger_error('TEMPLATE::SAVE_GET_BODY_TRANSLATIONS_IS_EMPTY');
        }

        foreach ($body as $lang => $translation) {
            $path = $this->getTranslationsFolder() . $lang . '/emails/';

            if (!mkdir($path) && !is_dir($path)) {
                trigger_error('Directory "%s" was not created', $path);
            }

            if (!$filePutContent = file_put_contents($path . $key . '.phtml', $translation)) {
                $result = $result && $filePutContent;
                trigger_error('TEMPLATE::SAVE_WRITE_DATA_TO_FILE_IS_EMPTY');
            }
        }

        return $result;

    }

    // Suppression d'un template
    public function delete(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $id = $this->getId();
        $key = $this->getKey();

        // Supprimer en base
        $db->delete(T_MAIL_TEMPLATES, 'ID = ' . $id);

        // Supprimer les fichiers de traduction
        $translations = Episciences_Tools::getOtherTranslations($this->getTranslationsFolder(), 'mails.php', '#^' . $key . '#');
        Episciences_Tools::writeTranslations($translations, $this->getTranslationsFolder(), 'mails.php');

        // Supprimer le template
        $langFolders = scandir($this->getTranslationsFolder());
        foreach ($langFolders as $folder) {
            $filepath = $this->getTranslationsFolder() . $folder . '/emails/' . $key . '.phtml';
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        return true;
    }

    /**
     * Charge les traductions du template (body, name et subject)
     * @param null $langs
     * @throws Zend_Exception
     */
    public function loadTranslations($langs = null): void
    {
        if (!$langs) {
            $langs = Episciences_Tools::getLanguages();
        }

        Episciences_Tools::loadTranslations($this->getTranslationsFolder(), 'mails.php');

        $this->loadName($langs);
        $this->loadSubject($langs);
        $this->loadBody();
    }

    public function getTranslations()
    {
        return $this->_translations;
    }

    /**
     * Charge le corps du template dans les différentes langues trouvées
     * @return array
     */
    public function loadBody(): array
    {
        $path = $this->getTranslationsFolder();
        $exclusions = ['.', '..', '.svn'];
        $result = [];

        if (is_dir($path)) {

            $files = scandir($path);
            foreach ($files as $file) {
                $filepath = $path . $file . '/emails/' . $this->getKey() . '.phtml';
                if (!in_array($file, $exclusions, true) && file_exists($filepath)) {
                    $result[$file] = file_get_contents($filepath);
                }
            }
        }

        if (!empty($result)) {
            $this->setBody($result);
        }

        return $result;
    }

    /**
     * Charge le nom template dans les différentes langues trouvées
     * @param $langs
     * @return array
     * @throws Zend_Exception
     */
    public function loadName($langs): array
    {
        $name = [];
        $translator = Zend_Registry::get('Zend_Translate');
        foreach ($langs as $code => $lang) {
            if ($translator->isTranslated($this->getKey() . '_tpl_name', false, $code)) {
                $name[$code] = $translator->translate($this->getKey() . '_tpl_name', $code);
            }
        }
        if (!empty($name)) {
            $this->setName($name);
        }
        return $name;
    }

    /**
     * Charge le sujet du template dans les différentes langues trouvées
     * @param $langs
     * @return array
     * @throws Zend_Exception
     */
    public function loadSubject($langs): array
    {
        $subject = [];
        $translator = Zend_Registry::get('Zend_Translate');
        foreach ($langs as $code => $lang) {
            // Subject
            if ($translator->isTranslated($this->getKey() . '_mail_subject', false, $code)) {
                $subject[$code] = $translator->translate($this->getKey() . '_mail_subject', $code);
            }
        }
        if (!empty($subject)) {
            $this->setSubject($subject);
        }

        return $subject;
    }

    /**
     * return true if template has a custom version, false otherwise
     * @return bool
     */
    public function isCustom(): bool
    {
        return ((bool)$this->getParentid());
    }

    // Getters ***************************************************

    /**
     * @return int
     */
    public function getRvid(): int
    {
        if (!$this->_rvid && defined('RVID')) {
            $this->setRvid(RVID);
        }

        return $this->_rvid;
    }


    /**
     * fetch template path
     * @param null $locale
     * @return string
     */
    public function getPath($locale = null): string
    {
        if (!$locale) {
            $locale = $this->getLocale();
        }
        return $this->getTranslationsFolder() . $locale . '/emails';
    }

    /**
     * Renvoie le body dans la langue voulue, ou la langue par défaut
     * @param $lang
     * @return mixed|null
     */
    public function getBody($lang = null)
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }
        if (is_array($this->_body) && array_key_exists($lang, $this->_body)) {
            return $this->_body[$lang];
        }

        return null;
    }

    /**
     * Renvoie toutes les traductions du body
     * @return mixed
     */
    public function getBodyTranslations()
    {
        return $this->_body;
    }

    /**
     *  Renvoie le nom du template dans la langue voulue, ou la langue par défaut
     * @param $lang
     * @return mixed|null
     */
    public function getName($lang = null)
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }
        if (is_array($this->_name) && array_key_exists($lang, $this->_name)) {
            return $this->_name[$lang];
        }

        return null;
    }

    /**
     * Renvoie toutes les traductions du nom du template
     * @return mixed
     */
    public function getNameTranslations()
    {
        return $this->_name;
    }

    /**
     *  Renvoie le sujet dans la langue voulue ou la langue par défaut
     * @param $lang
     * @return mixed|null
     * @throws Zend_Exception
     */
    public function getSubject($lang = null)
    {
        if (!$lang) {
            $lang = $this->getLocale();
        }

        if (is_array($this->_subject) && array_key_exists($lang, $this->_subject) && array_key_exists($lang, Episciences_Tools::getLanguages())) {
            return $this->_subject[$lang];
        }

        return null;
    }

    /**
     *  Renvoie toutes les traductions du sujet du mail
     * @return mixed
     */
    public function getSubjectTranslations()
    {
        return $this->_subject;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getParentid()
    {
        return $this->_parentId;
    }

    public function getRvcode()
    {
        if (!$this->_rvcode && defined('RVCODE')) {
            $this->setRvcode(RVCODE);
        }
        return $this->_rvcode;
    }

    public function getKey()
    {
        return $this->_key;
    }

    public function getType()
    {
        return $this->_type;
    }

    // SETTERS ***************************************************

    public function setBody($body): \Episciences_Mail_Template
    {
        $this->_body = $body;
        return $this;
    }

    public function setName($name): \Episciences_Mail_Template
    {
        $this->_name = $name;
        return $this;
    }

    public function setSubject($subject): \Episciences_Mail_Template
    {
        $this->_subject = $subject;
        return $this;
    }

    public function setId($id): \Episciences_Mail_Template
    {
        $this->_id = $id;
        return $this;
    }

    public function setParentid($parentId): \Episciences_Mail_Template
    {
        $this->_parentId = $parentId;
        return $this;
    }

    public function setRvcode($rvcode): \Episciences_Mail_Template
    {
        $this->_rvcode = $rvcode;
        return $this;
    }

    public function setKey($key): \Episciences_Mail_Template
    {
        $this->_key = $key;
        return $this;
    }

    public function setType($type): \Episciences_Mail_Template
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * @param int $rvid
     */
    public function setRvid($rvid): \Episciences_Mail_Template
    {
        $this->_rvid = (int)$rvid;
        return $this;
    }

    /**
     * get available tags list description
     * @return string
     */
    public function getAvailableTagsListDescription(): string
    {
        $tags = $this->_tags;
        $key = !$this->isCustom() ? $this->getKey() : substr($this->getKey(), 7); // Custom key = 'custom_' . $this->getKey();

        $map = [
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED => $this->_paper_accepted_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_EDITORS_COPY => $this->_paper_accepted_editors_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_TMP_VERSION => $this->_paper_accepted_tmp_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ASK_OTHER_EDITORS => $this->_paper_ask_other_editors_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_AUTHOR_COPY => $this->_paper_comment_from_reviewer_to_contributor_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR_EDITOR_COPY => $this->_paper_comment_from_reviewer_to_contributor_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_ANSWER_REVIEWER_COPY => $this->_paper_comment_answer_reviewer_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_ANSWER_EDITOR_COPY => $this->_paper_comment_answer_reviewer_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COMMENT_BY_EDITOR_EDITOR_COPY => $this->_paper_commnet_by_edditor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_AUTHOR_COPY => $this->_paper_deleted_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_EDITOR_COPY => $this->_paper_deleted_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_DELETED_REVIEWER_COPY => $this->_paper_deleted_reviewer_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_ASSIGN => $this->_paper_editor_assign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_REFUSED_MONITORING => $this->_paper_editor_refused_monitoring_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_UNASSIGN => $this->_paper_editor_unassign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_MAJOR_REVISION_REQUEST => $this->_paper_major_revision_request_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_MINOR_REVISION_REQUEST => $this->_paper_minor_revision_request_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_REVIEWER_REASSIGN => $this->_paper_new_version_reviewer_reassign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_REVIEWER_REINVITATION => $this->_paper_new_version_reviewer_re_invitation,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_NEW_VERSION_SUBMITTED => $this->_paper_new_version_submitted_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_PUBLISHED_AUTHOR_COPY => $this->_paper_published_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REFUSED => $this->_paper_refused_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWED_EDITOR_COPY => $this->_paper_reviewed_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWED_REVIEWER_COPY => $this->_paper_reviewed_reviewer_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_ACCEPTATION_EDITOR_COPY => $this->_paper_reviewer_acceptation_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_ACCEPTATION_REVIEWER_COPY => $this->_paper_reviewer_acceptation_reviewer_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER => $this->_paper_reviewer_invitation1_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER => $this->_paper_reviewer_invitation2_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER => $this->_paper_reviewer_invitation3_tags,
            Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_ACCEPTED_STOP_PENDING_REVIEWING => $this->_paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_PUBLISHED_REQUEST_STOP_PENDING_REVIEWING => $this->_paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_REFUSED_REQUEST_STOP_PENDING_REVIEWING => $this->_paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_REVISION_REQUEST_STOP_PENDING_REVIEWING => $this->_paper_accepted_published_refused_revision_request_stop_pending_reviewing_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REFUSAL_EDITOR_COPY => $this->_paper_reviewer_refusal_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REFUSAL_REVIEWER_COPY => $this->_paper_reviewer_refusal_reviewer_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REMOVAL => $this->_paper_reviewer_removal_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVISION_ANSWER => $this->_paper_revision_answer_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REVISION_REQUEST => $this->_paper_revision_request_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY => $this->_paper_submission_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_EDITOR_COPY => $this->_paper_submission_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY => $this->_paper_submission_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_AUTHOR_COPY => $this->_paper_submission_updated_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY => $this->_paper_submission_updated_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_ACCEPTATION => $this->_paper_suggest_acceptation_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_NEW_VERSION => $this->_paper_suggest_new_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGEST_REFUSAL => $this->_paper_suggest_refusal_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_REVIEWER_REASSIGN => $this->_paper_tmp_version_reviewer_reassign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_TMP_VERSION_SUBMITTED => $this->_paper_tmp_version_submitted_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_UPDATED_RATING_DEADLINE => $this->_paper_updated_rating_deadline_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_RATING_DEADLINE_EDITOR_VERSION => $this->_reminder_after_deadline_editor_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_RATING_DEADLINE_REVIEWER_VERSION => $this->_reminder_after_deadline_reviewer_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_REVISION_DEADLINE_AUTHOR_VERSION => $this->_reminder_after_revision_deadline_author_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_AFTER_REVISION_DEADLINE_EDITOR_VERSION => $this->_reminder_after_revision_deadline_editor_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_EDITOR_VERSION => $this->_reminder_before_revision_deadline_editor_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_REVISION_DEADLINE_AUTHOR_VERSION => $this->_reminder_before_revision_deadline_author_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_RATING_DEADLINE_EDITOR_VERSION => $this->_reminder_before_deadline_editor_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_BEFORE_RATING_DEADLINE_REVIEWER_VERSION => $this->_reminder_before_deadline_reviewer_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_NOT_ENOUGH_REVIEWERS_EDITOR_VERSION => $this->_reminder_not_enough_reviewers_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_EDITOR_VERSION => $this->_reminder_unanswered_reviewer_invitation_editor_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION => $this->_reminder_unanswered_reviewer_invitation_reviewer_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_USER_LOST_LOGIN => $this->_user_lost_login_tags,
            Episciences_Mail_TemplatesManager::TYPE_USER_LOST_PASSWORD => $this->_user_lost_password_tags,
            Episciences_Mail_TemplatesManager::TYPE_USER_REGISTRATION => $this->_user_registration_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_AUTHOR_COPY => $this->_paper_abandon_publication_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_BY_AUTHOR_AUTHOR_COPY => $this->_paper_abandon_publication_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_EDITOR_COPY => $this->_paper_abandon_publication_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_NO_ASSIGNED_EDITORS => $this->_paper_abandon_publication_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ABANDON_PUBLICATION_REVIEWER_REMOVAL => $this->_paper_abandon_publication_reviewer_removal_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_AUTHOR_COPY => $this->_paper_ce_accepted_final_version_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_COPYEDITOR_AND_EDITOR_COPY => $this->_paper_ce_accepted_final_version_copyEditor_and_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_AUTHOR_COPY => $this->_paper_ce_author_sources_submitted_response_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_SOURCES_DEPOSED_RESPONSE_COPYEDITORS_AND_EDITORS_COPY => $this->_paper_ce_author_sources_submitted_response_copyEditor_and_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY => $this->_paper_ce_author_final_version_submitted_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_EDITOR_AND_COPYEDITOR_COPY => $this->_paper_ce_author_final_version_submitted_editor_and_copyEditor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_AUTHOR_COPY => $this->_paper_ce_review_formatting_submitted_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY => $this->_paper_ce_review_formatting_submitted_editor_and_copyEditor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_AUTHOR_COPY => $this->_paper_ce_waiting_for_author_formatting_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_EDITOR_AND_COPYEDITOR_COPY => $this->_paper_ce_waiting_for_author_formatting_editor_and_copyEditor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_AUTHOR_COPY => $this->_paper_ce_waiting_for_author_sources_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_EDITOR_COPY => $this->_paper_ce_waiting_for_author_sources_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CONTINUE_PUBLICATION_AUTHOR_COPY => $this->_paper_continue_publication_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CONTINUE_PUBLICATION_EDITOR_COPY => $this->_paper_continue_publication_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN_AUTHOR_COPY => $this->_paper_copyEditor_assign_author_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN_EDITOR_COPY => $this->_paper_copyEditor_assign_Editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN => $this->_paper_copyEditor_assign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_UNASSIGN => $this->_paper_copyEditor_unassign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_PUBLISHED_EDITOR_COPY => $this->_paper_published_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_REFUSED_EDITORS_COPY => $this->_paper_refused_editor_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_VOLUME_EDITOR_ASSIGN => $this->_paper_volume_editor_assign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SECTION_EDITOR_ASSIGN => $this->_paper_section_editor_assign_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGESTED_EDITOR_ASSIGN => $this->_paper_suggested_editor_assign_tags,
            Episciences_Mail_TemplatesManager::TYPE_REMINDER_ARTICLE_BLOCKED_IN_ACCEPTED_STATE_EDITOR_VERSION => $this->_reminder_article_blocked_in_accepted_state_editor_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_TMP_VERSION_MANAGERS_COPY => $this->_paper_accepted_tmp_version_managers_copy_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_ASK_FINAL_AUTHORS_VERSION => $this->_paper_accepted_ask_authors_final_version_tags,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_FORMATTED_BY_JOURNAL_WAITING_AUTHOR_VALIDATION =>$this->_paper_formatted_by_journal_waiting_author_validation_tags
        ];

        if (array_key_exists($key, $map)) {
            $tags = array_merge($tags, $map[$key]);
        }

        return implode('; ', $tags);
    }
}
