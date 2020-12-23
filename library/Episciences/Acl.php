<?php

class Episciences_Acl extends Ccsd_Acl
{
    /**
     * Liste des rôles de l'application
     */
    const ROLE_ROOT = 'epiadmin';

    const ROLE_CHIEF_EDITOR = 'chief_editor';        // rédacteur en chef
    const ROLE_ADMIN = 'administrator';    // administrateur
    const ROLE_EDITOR = 'editor';            // rédacteur
    const ROLE_GUEST_EDITOR = 'guest_editor';            // rédacteur invité
    const ROLE_SECRETARY = 'secretary';
    const ROLE_WEBMASTER = 'webmaster';
    const ROLE_REVIEWER = 'reviewer';
    const ROLE_MEMBER = 'member';
    const ROLE_GUEST = 'guest';
    // Git 90
    const ROLE_COPY_EDITOR = 'copyeditor'; // CE

    const ROLE_CHIEF_EDITOR_PLURAL = 'chief_editors';
    const ROLE_ADMINISTRATOR_PLURAL = 'administrators';
    const ROLE_EDITOR_PLURAL = 'editors';
    const ROLE_GUEST_EDITOR_PLURAL = 'guest_editors';
    const ROLE_SECRETARY_PLURAL = 'secretaries';
    const ROLE_WEBMASTER_PLURAL = 'webmasters';
    const ROLE_REVIEWER_PLURAL = 'reviewers';
    const ROLE_MEMBER_PLURAL = 'members';
    const ROLE_GUEST_PLURAL = 'guests';
    const CONFIGURABLE_RESOURCE = true; // configurable by review
    const NOT_CONFIGURABLE_RESOURCE = false; //  public resource but restricted to certain roles

    /** @var array : see Episciences_User::Permissions */
    const TYPE_OF_RESOURCES_NOT_TO_BE_DISPLAYED = [
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
        'user-photo'
    ];

    /** @var array : configurable resources : see Episciences_Review */
    const CONFIGURABLE_RESOURCES = [
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

    public function __construct($file = null)
    {
        $this->_roles = [
            self::ROLE_GUEST => null,
            self::ROLE_MEMBER => self::ROLE_GUEST,
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
        // parent::__construct($file);

        //Ressources à rajouter dans les ACL
        $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/acl.ini');
        $this->_defaultAcl = $config->toArray();
    }

    public static function getCode($rightid)
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

        if (PHP_SAPI == 'cli') {
            return $roles;
        }

        if (!Episciences_Auth::isRoot()) {
            unset($roles[$acl::ROLE_ROOT]);
        }
        if (!Episciences_Auth::isSecretary()) { // git #235
            unset($roles[$acl::ROLE_CHIEF_EDITOR]);
            unset($roles[$acl::ROLE_ADMIN]);
            unset($roles[$acl::ROLE_EDITOR]);
            unset($roles[$acl::ROLE_GUEST_EDITOR]);
            unset($roles[$acl::ROLE_WEBMASTER]);
            unset($roles[$acl::ROLE_SECRETARY]);
            unset($roles[$acl::ROLE_COPY_EDITOR]);
        }
        unset($roles[$acl::ROLE_GUEST]);
        unset($roles[$acl::ROLE_MEMBER]);

        return $roles;
    }
}