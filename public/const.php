<?php

/**
 * define application constants
 */
function define_app_constants()
{
    // environnements
    define('ENV_PROD', 'production');
    define('ENV_PREPROD', 'preprod');
    define('ENV_TEST', 'testing');
    define('ENV_DEV', 'development');

    // modules
    define('PORTAL', 'portal');
    define('OAI', 'oai');
    define('JOURNAL', 'journal');
    define('CONFIG', 'config/');


    // define application environment
    if (!defined('APPLICATION_ENV') && getenv('APPLICATION_ENV')) {
        define('APPLICATION_ENV', getenv('APPLICATION_ENV'));
    }

    // define application paths
    if (!defined('APPLICATION_PATH')) {
        define('APPLICATION_PATH', dirname(__DIR__) . '/application');
    }

    if (!defined('APPLICATION_INI')) {
        define('APPLICATION_INI', APPLICATION_PATH . '/configs/application.ini');
    }


    define('APPLICATION_PUBLIC_PATH', dirname(APPLICATION_PATH) . '/public');
    define('PATH_TRANSLATION', APPLICATION_PATH . '/languages');

}

/**
 * define review constants
 */
function define_review_constants()
{
    // define review code
    if (!defined('RVCODE') && getenv('RVCODE')) {
        define('RVCODE', getenv('RVCODE'));
    }

    if (defined('RVCODE')) {
        // define application module
        switch (RVCODE) {
            case PORTAL:
                define('APPLICATION_MODULE', 'portal');
                break;
            case OAI:
                define('APPLICATION_MODULE', 'oai');
                break;
            default:
                define('APPLICATION_MODULE', 'journal');
        }

        // define application url
        if (defined('HTTP') && defined('DOMAIN')) {
            if (APPLICATION_MODULE === PORTAL) {
                define('APPLICATION_URL', HTTP . '://' . DOMAIN);
            } else {
                define('APPLICATION_URL', HTTP . '://' . RVCODE . '.' . DOMAIN);
            }
        }

        // define review path
        define('REVIEW_PATH', realpath(APPLICATION_PATH . '/../data/' . RVCODE) . '/');

        //configurable constants path
        define('CONFIGURABLE_CONSTANTS_PATH', APPLICATION_PATH . '/configs/' . APPLICATION_MODULE . '.configurable.constants.json');

        if (is_file(CONFIGURABLE_CONSTANTS_PATH)) {
            /** @var stdClass $object */
            $object = json_decode(file_get_contents(CONFIGURABLE_CONSTANTS_PATH), false);
            $ignoreReviewersEmail = $object->ignore_reviewers_email;
            $allowedExtensions = $object->allowed_extensions;
            $allowedMimesTypes = $object->allowed_mimes_types;
        } else {
            $ignoreReviewersEmail = [];
            $allowedExtensions = ['pdf'];
            $allowedMimesTypes = ['application/pdf'];
        }

        define('IGNORE_REVIEWERS_EMAIL_VALUES', $ignoreReviewersEmail);
        define('ALLOWED_EXTENSIONS', $allowedExtensions);
        define('ALLOWED_MIMES_TYPES', $allowedMimesTypes);
    }

    if (defined('REVIEW_PATH')) {
        define('REVIEW_TMP_PATH', REVIEW_PATH . 'tmp/');
        define('REVIEW_URL', APPLICATION_URL . '/public/');
        define('REVIEW_PUBLIC_PATH', REVIEW_PATH . 'public/');
        define('REVIEW_PAGE_PATH', REVIEW_PATH . 'pages/');
        define('REVIEW_FILES_PATH', REVIEW_PATH . 'files/');
        define('REVIEW_GRIDS_PATH', REVIEW_FILES_PATH . 'rating_grids/');

        define('REVIEW_DOCUMENT_DIR_NAME', 'documents');

        define('REVIEW_GRID_NAME_DEFAULT', 'grid_0.xml');
        define('REVIEW_PATH_DEFAULT', dirname(APPLICATION_PATH) . '/data/default/files/rating_grids' . '/');

        define('TMP_PATH', REVIEW_FILES_PATH . 'tmp/');

        define('REVIEW_LANG_PATH', REVIEW_PATH . 'languages/');
        define('GRID_LANG_PATH', REVIEW_LANG_PATH . 'grid/');
        define('RATING_LANG_PATH', REVIEW_LANG_PATH . 'rating/');
        define('VOLUME_LANG_PATH', REVIEW_LANG_PATH . 'volumes/');
        define('SECTION_LANG_PATH', REVIEW_LANG_PATH . 'sections/');
        define('CACHE_PATH', REVIEW_PATH . "tmp/");
        define('CACHE_PATH_METADATA', dirname(APPLICATION_PATH) . '/cache/');

    }
}

/**
 * define db table constants
 */
function define_table_constants()
{
    define('T_ALIAS', 'REVIEWER_ALIAS');
    define('T_ASSIGNMENTS', 'USER_ASSIGNMENT');
    define('T_CAS_USERS', 'T_UTILISATEURS');
    define('T_DOI_QUEUE', 'doi_queue');
    define('T_LOGS', 'PAPER_LOG');
    define('T_MAIL_LOG', 'MAIL_LOG');
    define('T_MAIL_REMINDERS', 'REMINDERS');
    define('T_MAIL_TEMPLATES', 'MAIL_TEMPLATE');
    define('T_NEWS', 'NEWS');
    define('T_PAPERS', 'PAPERS');
    define('T_PAPER_COMMENTS', 'PAPER_COMMENTS');
    define('T_PAPER_SETTINGS', 'PAPER_SETTINGS');
    define('T_PAPER_VISITS', 'PAPER_STAT');
    define('T_REVIEW', 'REVIEW');
    define('T_REVIEWER_POOL', 'REVIEWER_POOL');
    define('T_REVIEWER_REPORTS', 'REVIEWER_REPORT');
    define('T_REVIEW_SETTINGS', 'REVIEW_SETTING');
    define('T_SECTIONS', 'SECTION');
    define('T_SECTION_SETTINGS', 'SECTION_SETTING');
    define('T_TMP_USER', 'USER_TMP');
    define('T_USERS', 'USER');
    define('T_USER_INVITATIONS', 'USER_INVITATION');
    define('T_USER_INVITATION_ANSWER', 'USER_INVITATION_ANSWER');
    define('T_USER_INVITATION_ANSWER_DETAIL', 'USER_INVITATION_ANSWER_DETAIL');
    define('T_USER_MERGE', 'USER_MERGE');
    define('T_USER_ROLES', 'USER_ROLES');
    define('T_USER_TOKENS', 'USER_TOKEN');
    define('T_VOLUMES', 'VOLUME');
    define('T_VOLUME_METADATAS', 'VOLUME_METADATA');
    define('T_VOLUME_PAPER', 'VOLUME_PAPER');
    define('T_VOLUME_PAPER_POSITION', 'VOLUME_PAPER_POSITION');
    define('T_VOLUME_SETTINGS', 'VOLUME_SETTING');
    define('VISITS_TEMP', 'STAT_TEMP');
    define('T_PAPER_FILES', 'paper_files');
    define('T_PAPER_DATASETS', 'paper_datasets');
    define('T_PAPER_CONFLICTS', 'paper_conflicts');
}

/**
 * define some simple constants
 */
function define_simple_constants()
{
    // define http protocol
    define('HTTP', isset($_SERVER['HTTPS']) ? 'https' : 'http');
    define('DOMAIN', 'episciences.org');

    define('KO', 1024);
    define('MO', 1048576);
    define('MAX_FILE_SIZE', 15 * MO);
    define('MAX_INPUT_TEXTAREA', 65000);
    define('ABSTRACT_MAX_LENGTH', 1500);
    define('CE_RESOURCES_NAME', 'episciences.zip');
    define('DUPLICATE_ENTRY_SQLSTATE', 23000);
    define('TINYMCE_DIR', '/js/tinymce/');

}

function check_constants()
{
    $errors = [];

    if (!defined('APPLICATION_ENV')) {
        $errors = 'APPLICATION_ENV is not defined';
    }
    if (!defined('APPLICATION_PATH')) {
        $errors = 'APPLICATION_PATH is not defined';
    }

    return [
        'status' => count($errors) ? false : true,
        'errors' => $errors
    ];
}

/**
 * Constants to include vendor JS Libraries
 */
function defineVendorJsLibraries()
{
    define('VENDOR_BOOTBOX', 'https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/5.5.2/bootbox.min.js');
    define('VENDOR_BOOTSTRAP_COLORPICKER', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/js/bootstrap-colorpicker.min.js');
    define('VENDOR_BOOTSTRAP_JS', "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js");
    define('VENDOR_DATATABLES_BOOTSTRAP', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap.min.js');
    define('VENDOR_JQUERY', 'https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js');
    define('VENDOR_JQUERY_DATATABLES', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js');
    define('VENDOR_JQUERY_FILE_UPLOAD', 'https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/10.31.0/js/jquery.fileupload.min.js');
    define('VENDOR_JQUERY_NESTED_SORTABLE', 'https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/1.3.4/jquery.ui.nestedSortable.min.js');
    define('VENDOR_JQUERY_UI', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js');
    define('VENDOR_JQUERY_URL_PARSER', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-url-parser/2.2.1/purl.min.js');
    define('VENDOR_MATHJAX', 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.7/MathJax.js?config=TeX-AMS-MML_HTMLorMML');
    define('VENDOR_TINYMCE', 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/tinymce.min.js');
    define('VENDOR_TINYMCE_JQUERY', 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/tinymce.min.js');
    define('VENDOR_CHART', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js');
    define('VENDOR_CHART_PLUGIN_DATALABELS', 'https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/0.7.0/chartjs-plugin-datalabels.min.js');
}

/**
 * Constants to include vendor CSS
 */
function defineVendorCssLibraries()
{
    define('VENDOR_BOOTSTRAP', 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/css/bootstrap.min.css');
    define('VENDOR_BOOTSTRAP_COLORPICKER_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/css/bootstrap-colorpicker.css');
    define('VENDOR_DATATABLES_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap.min.css');
    define('VENDOR_FONT_AWESOME', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/fontawesome.min.css");
    define('VENDOR_FONT_AWESOME_SOLID', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/solid.min.css");
    define('VENDOR_JQUERY_UI_THEME_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/flick/jquery-ui.min.css');
    define('VENDOR_CHART_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css');
    define('VENDOR_COOKIE_CONSENT_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/cookieconsent/3.1.1/cookieconsent.min.css');
}

/**
 * Never called
 * Prevent warning from code analyzers for undefined constants
 */
function fixUndefinedConstantsForCodeAnalysis()
{
    if (0 > 1) {
        define('EPISCIENCES_EXCEPTIONS_LOG_PATH', '');
        define('CACHE_PATH', '');
        define('APPLICATION_VERSION', '');
        define('PWD_PATH', '');
        define('FUSION_TOKEN_AUTH', '');
        define('EPISCIENCES_SOLR_LOG_PATH', '');
        define('EPISCIENCES_API_URL', '');
        define('EPISCIENCES_API_SECRET_KEY', '');
    }
}
