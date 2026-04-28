<?php

class Episciences_Acl extends Ccsd_Acl
{
    /**
     * Liste des rôles de l'application
     */
    public const ROLE_ROOT = 'epiadmin';

    public const ROLE_CHIEF_EDITOR = 'chief_editor';           // rédacteur en chef
    public const ROLE_ADMIN = 'administrator';                 // administrateur
    public const ROLE_EDITOR = 'editor';                       // rédacteur
    public const ROLE_GUEST_EDITOR = 'guest_editor';           // rédacteur invité
    public const ROLE_SECRETARY = 'secretary';
    public const ROLE_WEBMASTER = 'webmaster';
    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_AUTHOR = 'author';
    public const ROLE_EDITORIAL_BOARD = 'editorial_board';
    public const ROLE_TECHNICAL_BOARD = 'technical_board';
    public const ROLE_SCIENTIFIC_ADVISORY_BOARD = 'scientific_advisory_board';
    public const ROLE_ADVISORY_BOARD = 'advisory_board';
    public const ROLE_MANAGING_EDITOR = 'managing_editor';
    public const ROLE_HANDLING_EDITOR = 'handling_editor';
    public const ROLE_FORMER_MEMBER = 'former_member';
    public const ROLE_MEMBER = 'member';
    public const ROLE_GUEST = 'guest';
    //git 181
    public const ROLE_CO_AUTHOR = 'coauthor';
    // Git 90
    public const ROLE_COPY_EDITOR = 'copyeditor'; // CE

    public const ROLE_CHIEF_EDITOR_PLURAL = 'chief_editors';
    public const ROLE_ADMINISTRATOR_PLURAL = 'administrators';
    public const ROLE_EDITOR_PLURAL = 'editors';
    public const ROLE_GUEST_EDITOR_PLURAL = 'guest_editors';
    public const ROLE_SECRETARY_PLURAL = 'secretaries';
    public const ROLE_WEBMASTER_PLURAL = 'webmasters';
    public const ROLE_REVIEWER_PLURAL = 'reviewers';
    public const ROLE_EDITORIAL_BOARD_PLURAL = 'editorial_boards';
    public const ROLE_TECHNICAL_BOARD_PLURAL = 'technical_boards';
    public const ROLE_SCIENTIFIC_ADVISORY_BOARD_PLURAL = 'scientific_advisory_boards';
    public const ROLE_FORMER_MEMBER_PLURAL = 'former_members';
    public const ROLE_MEMBER_PLURAL = 'members';
    public const ROLE_AUTHOR_PLURAL = 'authors';
    public const ROLE_GUEST_PLURAL = 'guests';
    public const ROLE_CO_AUTHOR_PLURAL = 'coauthors';
    public const CONFIGURABLE_RESOURCE = true; // configurable by review
    public const NOT_CONFIGURABLE_RESOURCE = false; //  public resource but restricted to certain roles

    /** @var array : see Episciences_User::Permissions */
    public const TYPE_OF_RESOURCES_NOT_TO_BE_DISPLAYED = [
        'feed-rss',
        'user-dashboard',
        'user-change_account_email',
        'feed-atom',
        'feed-index',
        'file-oafiles',
        'user-reset_api_password',
        'hal-bibfeed',
        'export-json',
        'comments-addcomment',
        'api-openaire-metrics',
        'api-journals',
        'api-ccsd-metrics',
        'administratelinkeddata-ajaxgetldform',
        'import-index', // start root actions
        'administratepaper-updatehal', // end of root actions
        'api-merge', // ----- start portal actions
        'arxiv-bibfeed',
        'browse-reviews',
        'index-index',
        'sitemap-index', //----- end portal actions
        'robots-index',
        'robots-sitemap',
        "administratemail-index",
        'administrate-index',
        'administratemail-refreshreminders', // ajax resources: idem reminders
        'administratepaper-ajaxcontrolboard', // not used
        'administratemail-savetemplate', // idem edittemplate
        'administrate-review',
        'administrate-users',
        'test-index',
        'test-tei',
        'test-upload',
        'test-datatable',
        'test-delete',
        'page-index',
        'partial-modal',
        'error-error',
        'website-index',
        'website-reset', // idem website-menu
        'administratepaper-assign', // à supprimer des acl(s) ( à vérifier)
        'administratepaper-benchmark', // à supprimer des acl(s) ( à vérifier)
        'administratepaper-managed', // à supprimer des acl(s) ( à vérifier)
        'administratepaper-reviewerslist', // n'est plus utilisée (à vérifier)
        'administratepaper-savereviewerinvitation', // idem invitereviewer
        'administratepaper-status', // à supprimer des acl(s) ( à vérifier)
        'administratemail-view',
        'file-attachments', // pas d'intérêt d'indiquer qui devrait visualiser les fichiers attachés aux mails
        'file-docfiles', //  pas d'intérêt d'indiquer qui devrait visualiser les fichiers attachés aux comentaires
        'file-index',
        'grid-index',
        'news-index',
        'grid-create', // à supprimer des acl(s) ( à vérifier),
        'review-assignationmode', // obsolete
        'rss-index',
        'rss-news', // idem rss-papers
        'section-index',
        'section-list',
        'user-displaytags',
        'user-activate',
        'user-index',
        'user-login',
        'user-logout',
        'user-logoutfromcas',
        'user-permissions',
        'user-su',  // idem user-list (se connecter à la place d'un autre utilisateur)
        'user-view',
        'volume-index',
        'user-photo',
        'volume-all',
        'doi-settings', // only root
        'administratelinkeddata-ajaxgetldform',
    ];

    /** @var bool[][] */
    public const CONFIGURABLE_RESOURCES = [ // configurable resources : see Episciences_Review
        self::ROLE_CHIEF_EDITOR => [
            'administratepaper-suggeststatus' => self::NOT_CONFIGURABLE_RESOURCE,
            'administratepaper-saverefusedmonitoring' => self::NOT_CONFIGURABLE_RESOURCE,
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE
        ],

        self::ROLE_ADMIN => [
            'administratepaper-suggeststatus' => self::NOT_CONFIGURABLE_RESOURCE,
            'administratepaper-saverefusedmonitoring' => self::NOT_CONFIGURABLE_RESOURCE,
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE
        ],

        self::ROLE_EDITOR => [
            'administratepaper-list' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-accept' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-publish' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-refuse' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-revision' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-suggeststatus' => self::CONFIGURABLE_RESOURCE,
            'administratemail-deletetemplate' => self::CONFIGURABLE_RESOURCE,
            'administratemail-edittemplate' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE,
            'comments-editcomment' => self::NOT_CONFIGURABLE_RESOURCE,
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE
        ],

        self::ROLE_GUEST_EDITOR => [
            'administratepaper-accept' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-publish' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-refuse' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-revision' => self::CONFIGURABLE_RESOURCE,
            'administratepaper-suggeststatus' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE,
            'comments-editcomment' => self::NOT_CONFIGURABLE_RESOURCE,
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
        ],

        self::ROLE_MEMBER => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],

        self::ROLE_EDITORIAL_BOARD => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],
        self::ROLE_TECHNICAL_BOARD => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],
        self::ROLE_SCIENTIFIC_ADVISORY_BOARD => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],
        self::ROLE_ADVISORY_BOARD => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],
        self::ROLE_MANAGING_EDITOR => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],
        self::ROLE_HANDLING_EDITOR => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],
        self::ROLE_FORMER_MEMBER => [
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE,
            'paper-abandon' => self::CONFIGURABLE_RESOURCE
        ],
        self::ROLE_REVIEWER => [
            'paper-abandon' => self::NOT_CONFIGURABLE_RESOURCE,
            'comments-editcomment' => self::NOT_CONFIGURABLE_RESOURCE,
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE
        ],

        self::ROLE_SECRETARY => [
            'administratepaper-suggeststatus' => self::NOT_CONFIGURABLE_RESOURCE,
            'administratepaper-saverefusedmonitoring' => self::NOT_CONFIGURABLE_RESOURCE,
            'paper-savetmpversion' => self::CONFIGURABLE_RESOURCE
        ],

        self::ROLE_WEBMASTER => [
            'paper-abandon' => self::NOT_CONFIGURABLE_RESOURCE,
            'comments-editcomment' => self::NOT_CONFIGURABLE_RESOURCE,
            "paper-saveanswer" => self::NOT_CONFIGURABLE_RESOURCE,
            "paper-savenewversion" => self::NOT_CONFIGURABLE_RESOURCE,
            "paper-savetmpversion" => self::NOT_CONFIGURABLE_RESOURCE
        ],

        self::ROLE_COPY_EDITOR => [
            'paper-abandon' => self::NOT_CONFIGURABLE_RESOURCE,
            'comments-editcomment' => self::NOT_CONFIGURABLE_RESOURCE,
            "paper-saveanswer" => self::NOT_CONFIGURABLE_RESOURCE,
            "paper-savenewversion" => self::NOT_CONFIGURABLE_RESOURCE,
            "paper-savetmpversion" => self::NOT_CONFIGURABLE_RESOURCE
        ]
    ];

    /**
     * @throws Zend_Config_Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->_roles = [
            self::ROLE_GUEST => null,
            self::ROLE_MEMBER => self::ROLE_GUEST,
            self::ROLE_EDITORIAL_BOARD => self::ROLE_MEMBER,
            self::ROLE_TECHNICAL_BOARD => self::ROLE_MEMBER,
            self::ROLE_SCIENTIFIC_ADVISORY_BOARD => self::ROLE_MEMBER,
            self::ROLE_ADVISORY_BOARD => self::ROLE_MEMBER,
            self::ROLE_MANAGING_EDITOR => self::ROLE_MEMBER,
            self::ROLE_HANDLING_EDITOR => self::ROLE_MEMBER,
            self::ROLE_FORMER_MEMBER => self::ROLE_MEMBER,
            self::ROLE_AUTHOR => self::ROLE_MEMBER,
            self::ROLE_REVIEWER => self::ROLE_MEMBER,
            self::ROLE_COPY_EDITOR => self::ROLE_MEMBER,
            self::ROLE_GUEST_EDITOR => self::ROLE_REVIEWER,
            self::ROLE_WEBMASTER => self::ROLE_MEMBER,
            self::ROLE_EDITOR => self::ROLE_GUEST_EDITOR,
            self::ROLE_SECRETARY => self::ROLE_EDITOR,
            self::ROLE_ADMIN => self::ROLE_SECRETARY,
            self::ROLE_CHIEF_EDITOR => self::ROLE_ADMIN,
            self::ROLE_ROOT => self::ROLE_CHIEF_EDITOR
        ];

        //Ressources à rajouter dans les ACL
        $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/acl.ini');
        $this->_defaultAcl = $config->toArray();
    }

    public static function getCode($rightid): void
    {
        //return self::$_rolesCodes[$rightid];
    }

    public function getRolesCodes()
    {
        $rolesKeys = array_keys($this->_roles);
        krsort($rolesKeys); // high to low

        // return self::$_rolesCodes;
        return array_combine($rolesKeys, $rolesKeys);
    }

    public function getEditableRoles()
    {
        $acl = new Episciences_Acl();
        $roles = $acl->getRolesCodes();

        if (PHP_SAPI === 'cli') {
            return $roles;
        }


        if (!Episciences_Auth::isRoot()) {
            unset($roles[$acl::ROLE_ROOT], $roles[$acl::ROLE_AUTHOR]);
        }

        if (!Episciences_Auth::isSecretary()) { // git #235
            unset($roles[$acl::ROLE_CHIEF_EDITOR], $roles[$acl::ROLE_ADMIN], $roles[$acl::ROLE_EDITOR], $roles[$acl::ROLE_GUEST_EDITOR], $roles[$acl::ROLE_WEBMASTER], $roles[$acl::ROLE_SECRETARY], $roles[$acl::ROLE_COPY_EDITOR]);
        }
        unset($roles[$acl::ROLE_GUEST], $roles[$acl::ROLE_MEMBER]);

        return $roles;
    }
}