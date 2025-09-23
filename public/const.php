<?php

// Ensure autoloader is loaded
if (!class_exists('Composer\Autoload\ClassLoader') && file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Symfony\Component\Dotenv\Dotenv;

function defineProtocol(): void
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $protocol = 'https';
    } elseif ((!empty($_SERVER['HTTPS'])) && (strtolower($_SERVER['HTTPS']) === 'on')) {
        $protocol = 'https';
    } else {
        $protocol = 'http';
    }

    defined('SERVER_PROTOCOL') || define('SERVER_PROTOCOL', $protocol);
}

/**
 * defines application constants
 */
function defineApplicationConstants(): void
{

    $entries = [
        // environnements
        'ENV_PROD' => 'production',
        'ENV_PREPROD' => 'preprod',
        'ENV_TEST' => 'testing',
        'ENV_DEV' => 'development',
        // modules
        'PORTAL' => 'portal',
        'OAI' => 'oai',
        'JOURNAL' => 'journal',
        'CONFIG' => 'config/'
    ];

    foreach ($entries as $key => $value) {
        if (!defined($key)) {
            define($key, $value);
        }
    }

    if (empty($_ENV)) {
        $dotEnv = new Dotenv();
        $envPath = sprintf('%s/.env', dirname(__DIR__));
        //Loads env vars from .env. local. php if the file exists or from the other .env files otherwise
        $dotEnv->bootEnv($envPath);
    }

    // Some cron jobs are started with the app_env parameter, which is initialised elsewhere. @sse  Script::initApp()
    $isFromCli = !isset($_SERVER ['SERVER_SOFTWARE']) && (PHP_SAPI === 'cli' || (is_numeric($_SERVER ['argc']) && $_SERVER ['argc'] > 0));

    // define application environment
    if (!defined('APPLICATION_ENV') && !$isFromCli) {
        if (getenv('APPLICATION_ENV')) {
            define('APPLICATION_ENV', getenv('APPLICATION_ENV'));
        } elseif (isset($_ENV['APP_ENV'])) {
            define('APPLICATION_ENV', $_ENV['APP_ENV']);
        }
    }

    // define application paths
    if (!defined('APPLICATION_PATH')) {
        define('APPLICATION_PATH', dirname(__DIR__) . '/application');
    }

    if (!defined('CACHE_PATH_METADATA')) {
        define('CACHE_PATH_METADATA', dirname(APPLICATION_PATH) . '/cache/');
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
function defineJournalConstants(string $rvCode = null): void
{
    $prefixUrl = ($rvCode && $rvCode !== 'portal') ? sprintf('/%s/', $rvCode) : PORTAL_PREFIX_URL;
    $isFromCli = !isset($_SERVER ['SERVER_SOFTWARE']) && (PHP_SAPI === 'cli' || (is_numeric($_SERVER ['argc']) && $_SERVER ['argc'] > 0));

    if (!defined('RVCODE')) {
        if (!$rvCode) {
            if (getenv('RVCODE')) {
                $rvCode = getenv('RVCODE');
            } elseif (!$isFromCli) {
                $rvCode = 'portal';
                $front = Zend_Controller_Front::getInstance();
                if ($front) {
                    try {
                        $front->setRequest(new Zend_Controller_Request_Http());
                        $request = $front->getRequest();
                        if ($request) {
                            $requestUri = $request->getRequestUri();
                            if ($requestUri !== '/') {
                                $explodedUri = explode('/', $requestUri);
                                $extractedCode = $explodedUri[1];
                                $application = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
                                try {
                                    $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
                                } catch (Zend_Db_Exception $e) {
                                    trigger_error($e->getMessage(), E_USER_ERROR);
                                }

                                $select = $db
                                    ->select()
                                    ->from(T_REVIEW, ['CODE'])
                                    ->where('STATUS = ?', 1)
                                    ->where('CODE = ?', $extractedCode)
                                    ->where('is_new_front_switched = ?', 'yes');

                                $result = $db->fetchOne($select);
                                if ($result && $result !== 'portal') {
                                    $rvCode = $result;
                                    $prefixUrl = sprintf('/%s/', $result);
                                }
                            }
                        }

                    } catch (Zend_Controller_Exception $e) {
                        trigger_error($e->getMessage());
                    }
                }
            }
        }
        define('RVCODE', $rvCode);
        define('PREFIX_URL', $prefixUrl);
    }

    // Ensure PREFIX_URL is always defined, even if RVCODE was already defined
    if (!defined('PREFIX_URL')) {
        $prefixUrl = ($rvCode && $rvCode !== 'portal') ? sprintf('/%s/', $rvCode) : PORTAL_PREFIX_URL;
        define('PREFIX_URL', $prefixUrl);
    }

    if ($rvCode) {

        // define application ur
        if (!defined('APPLICATION_URL')) {
            if (getenv('RVCODE')) {
                define('APPLICATION_URL', SERVER_PROTOCOL . '://' . $rvCode . '.' . DOMAIN);
            } elseif (isset($_SERVER['SERVER_NAME'])) {
                define('APPLICATION_URL', sprintf('%s://%s%s', SERVER_PROTOCOL, $_SERVER['SERVER_NAME'], rtrim(PREFIX_URL, '/')));
            } elseif (isset($_ENV['MANAGER_APPLICATION_URL'])) {
                define('APPLICATION_URL', sprintf('%s%s', rtrim($_ENV['MANAGER_APPLICATION_URL'], '/'), rtrim(PREFIX_URL, '/')));
            } else {
                // Fallback for CLI usage
                define('APPLICATION_URL', sprintf("%s://%s.%s", SERVER_PROTOCOL, $rvCode, DOMAIN));
            }
        }

        // define application module
        switch ($rvCode) {
            case PORTAL:
                define('APPLICATION_MODULE', 'portal');
                break;
            case OAI:
                define('APPLICATION_MODULE', 'oai');
                break;
            default:
                define('APPLICATION_MODULE', 'journal');
        }

        // define review path
        define('REVIEW_PATH', realpath(APPLICATION_PATH . '/../data/' . $rvCode) . '/');

        //configurable constants path
        define('CONFIGURABLE_CONSTANTS_PATH', APPLICATION_PATH . '/configs/' . APPLICATION_MODULE . '.configurable.constants.json');

        if (is_file(CONFIGURABLE_CONSTANTS_PATH)) {
            /** @var array $configurableConst */
            try {
                $configurableConst = json_decode(file_get_contents(CONFIGURABLE_CONSTANTS_PATH), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                trigger_error($e->getMessage());
            }

            $allowedExtensions = $configurableConst['allowed_extensions'] ?? ['pdf'];
            $allowedMimesTypes = $configurableConst['allowed_mimes_types'] ?? ['application/pdf'];

        } else {
            $allowedExtensions = ['pdf'];
            $allowedMimesTypes = ['application/pdf'];
        }

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
    }
}

/**
 * define db table constants
 */
function defineSQLTableConstants(): void
{
    define('T_ALIAS', 'REVIEWER_ALIAS');
    define('T_ASSIGNMENTS', 'USER_ASSIGNMENT');
    define('T_CAS_USERS', 'T_UTILISATEURS');
    define('T_DOI_QUEUE', 'doi_queue');
    define('T_DOI_QUEUE_VOLUMES', 'doi_queue_volumes');
    define('T_LOGS', 'PAPER_LOG');
    define('T_MAIL_LOG', 'MAIL_LOG');
    define('T_MAIL_REMINDERS', 'REMINDERS');
    define('T_MAIL_TEMPLATES', 'MAIL_TEMPLATE');
    define('T_NEWS', 'NEWS');
    define('T_JOURNAL_NEWS', 'news');
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
    define('T_VOLUME_PROCEEDING', 'volume_proceeding');
    define('VISITS_TEMP', 'STAT_TEMP');
    define('T_PAPER_FILES', 'paper_files');
    define('T_PAPER_DATASETS', 'paper_datasets');
    define('T_PAPER_LICENCES', 'paper_licences');
    define('T_PAPER_DATASETS_META', 'paper_datasets_meta');
    define('T_PAPER_AUTHORS', 'authors');
    define('T_PAPER_CONFLICTS', 'paper_conflicts');
    define('T_PAPER_METADATA_SOURCES', 'metadata_sources');
    define('T_PAPER_PROJECTS', 'paper_projects');
    define('T_PAPER_CITATIONS', 'paper_citations');
    define('T_PAGES', 'pages');
    define('T_PAPER_CLASSIFICATIONS', 'paper_classifications');
    define('T_PAPER_CLASSIFICATION_MSC2020', 'classification_msc2020');
    define('T_PAPER_CLASSIFICATION_JEL', 'classification_jel');
    define('T_PAPER_DATA_DESCRIPTOR', 'data_descriptor');
    define('T_FILES', 'files');
}

/**
 * define some simple constants
 */
function defineSimpleConstants(): void
{
    define('DOMAIN', 'episciences.org');
    define('KO', 1024);
    define('MO', 1048576);
    define('MAX_FILE_SIZE', 15 * MO);
    define('MAX_INPUT_TEXTAREA', 65000);
    define('ABSTRACT_MAX_LENGTH', 1500);
    define('CE_RESOURCES_NAME', 'episciences.zip');
    define('DUPLICATE_ENTRY_SQLSTATE', 23000);
    define('TINYMCE_DIR', '/js/tinymce/');
    define('MAX_PWD_INPUT_SIZE', 40);
    define('MAX_PDF_SIZE', 500 * MO);
    define('ENCODING_TYPE', 'UTF-8');
    define('PORTAL_PREFIX_URL', '/');
    define('PREFIX_ROUTE', 'rv-code');

}


/**
 * Constants to include vendor JS Libraries
 */
function defineVendorJsLibraries(): void
{
    define('VENDOR_BOOTBOX', 'https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/5.5.3/bootbox.min.js');
    define('VENDOR_BOOTSTRAP_COLORPICKER', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/js/bootstrap-colorpicker.min.js');
    define('VENDOR_BOOTSTRAP_JS', "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js");
    define('VENDOR_DATATABLES_BOOTSTRAP', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap.min.js');
    define('VENDOR_JQUERY', 'https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js');
    define('VENDOR_JQUERY_DATATABLES', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js');
    define('VENDOR_JQUERY_FILE_UPLOAD', 'https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/10.32.0/js/jquery.fileupload.min.js');
    define('VENDOR_JQUERY_NESTED_SORTABLE', 'https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/1.3.4/jquery.ui.nestedSortable.min.js');
    define('VENDOR_JQUERY_UI', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js'); // Do not upgrade
    define('VENDOR_JQUERY_URL_PARSER', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-url-parser/2.2.1/purl.min.js');
    define('VENDOR_MATHJAX', 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.7/MathJax.js?config=TeX-AMS-MML_HTMLorMML');
    define('VENDOR_TINYMCE', 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.3.0/tinymce.min.js');
    define('VENDOR_TINYMCE_JQUERY', 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.3.0/tinymce.min.js');
    define('VENDOR_CHART', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js');
    define('VENDOR_CHART_PLUGIN_DATALABELS', 'https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/0.7.0/chartjs-plugin-datalabels.min.js');
}

/**
 * Constants to include vendor CSS
 */
function defineVendorCssLibraries(): void
{
    define('VENDOR_BOOTSTRAP', 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css');
    define('VENDOR_BOOTSTRAP_COLORPICKER_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/css/bootstrap-colorpicker.css');
    define('VENDOR_DATATABLES_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap.min.css');
    define('VENDOR_FONT_AWESOME', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/fontawesome.min.css");
    define('VENDOR_FONT_AWESOME_BRAND', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/brands.min.css");
    define('VENDOR_FONT_AWESOME_SOLID', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/solid.min.css");
    define('VENDOR_JQUERY_UI_THEME_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/flick/jquery-ui.min.css');
    define('VENDOR_CHART_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css');
    define('VENDOR_COOKIE_CONSENT_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/cookieconsent/3.1.1/cookieconsent.min.css');
}

/**
 * Never called
 * Prevent warning from code analyzers for undefined constants
 * @see config pwd.json
 */
function fixUndefinedConstantsForCodeAnalysis(): void
{
    if (0 > 1) {
        define('EPISCIENCES_EXCEPTIONS_LOG_PATH', '');
        define('CACHE_PATH', '');
        define('APPLICATION_VERSION', '');
        define('PWD_PATH', '');
        define('FUSION_TOKEN_AUTH', '');
        define('EPISCIENCES_LOG_PATH', '');
        define('EPISCIENCES_SOLR_LOG_PATH', '');
        define('EPISCIENCES_API_URL', '');
        define('EPISCIENCES_API_SECRET_KEY', '');
        define('EPISCIENCES_UID', 0);
        define('EPISCIENCES_Z_SUBMIT', 0);
        define('EPISCIENCES_USER_AGENT', '');
        define('EPISCIENCES_SUPPORT','');
        define('NOTIFY_TARGET_HAL_INBOX', '');
        define('NOTIFY_TARGET_HAL_URL', '');
        define('OPENALEX_MAILTO', '');
        define('OPENALEX_APIURL', '');
        define('CROSSREF_MAILTO', '');
        define('CROSSREF_APIURL', '');
        define('OPENCITATIONS_MAILTO', '');
        define('OPENCITATIONS_APIURL', '');
        define('OPENCITATIONS_TOKEN', '');
        define('CROSSREF_PLUS_API_TOKEN', '');
        define('DOI_AGENCY', '');
        define('DOI_TESTAPI', '');
        define('DOI_API', '');
        define('DOI_LOGIN', '');
        define('DOI_PASSWORD', '');
        define('DOI_TESTAPI_QUERY', '');
        define('DOI_API_QUERY', '');

        define('ENDPOINTS_SEARCH_HOST', '');
        define('ENDPOINTS_SEARCH_PORT', 0);
        define('ENDPOINTS_SEARCH_PATH', '');
        define('ENDPOINTS_SEARCH_TIMEOUT', 0);
        define('ENDPOINTS_SEARCH_USERNAME', '');
        define('ENDPOINTS_SEARCH_PASSWORD', '');
        define('ENDPOINTS_CORENAME', '');
        define('ENDPOINTS_SEARCH_PROTOCOL', '');
        define('ENDPOINTS_INDEXING_HOST', '');
        define('ENDPOINTS_INDEXING_TIMEOUT', 0);
        define('DOI_EMAIL_CONTACT', '');
        define('NOTIFY_TARGET_HAL_LINKED_REPOSITORY', null);
        define('EPISCIENCES_IGNORED_EMAILS_WHEN_INVITING_REVIEWER', []);
        define('EPISCIENCES_BIBLIOREF', []);
        define('OAI', '');
        define('PORTAL', '');
        define('ENV_PROD', '');
        define('EPISCIENCES_MAIL_PATH', '');
        define('ENV_DEV', '');
        define('MANAGER_APPLICATION_URL', '');
        define('INBOX_ID', '');
        define('INBOX_URL', '');
        define('INBOX_DB_HOST', '');
        define('INBOX_DB_DRIVER', '');
        define('INBOX_DB_USER', '');
        define('INBOX_DB_PASSWORD', '');
        define('INBOX_DB_NAME', '');
    }
}
