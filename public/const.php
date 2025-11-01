<?php

// Ensure autoloader is loaded
if (!class_exists('Composer\Autoload\ClassLoader') && file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Symfony\Component\Dotenv\Dotenv;

/**
 * Safe define helper - checks if constant exists before defining
 * Encapsulates the pattern: defined('X') || define('X', value)
 *
 * @param string $name Constant name
 * @param mixed $value Constant value
 * @return void
 */
function safeDef(string $name, mixed $value): void
{
    if (!defined($name)) {
        define($name, $value);
    }
}

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

    // Some cron jobs are started with the app_env parameter, which is initialised elsewhere. @see  Script::initApp()
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

    defined('APPLICATION_PUBLIC_PATH') || define('APPLICATION_PUBLIC_PATH', dirname(APPLICATION_PATH) . '/public');
    defined('PATH_TRANSLATION') || define('PATH_TRANSLATION', APPLICATION_PATH . '/languages');
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
    $tableConstants = [
        'T_ALIAS' => 'REVIEWER_ALIAS',
        'T_ASSIGNMENTS' => 'USER_ASSIGNMENT',
        'T_CAS_USERS' => 'T_UTILISATEURS',
        'T_DOI_QUEUE' => 'doi_queue',
        'T_DOI_QUEUE_VOLUMES' => 'doi_queue_volumes',
        'T_LOGS' => 'PAPER_LOG',
        'T_MAIL_LOG' => 'MAIL_LOG',
        'T_MAIL_REMINDERS' => 'REMINDERS',
        'T_MAIL_TEMPLATES' => 'MAIL_TEMPLATE',
        'T_NEWS' => 'NEWS',
        'T_JOURNAL_NEWS' => 'news',
        'T_PAPERS' => 'PAPERS',
        'T_PAPER_COMMENTS' => 'PAPER_COMMENTS',
        'T_PAPER_SETTINGS' => 'PAPER_SETTINGS',
        'T_PAPER_VISITS' => 'PAPER_STAT',
        'T_REVIEW' => 'REVIEW',
        'T_REVIEWER_POOL' => 'REVIEWER_POOL',
        'T_REVIEWER_REPORTS' => 'REVIEWER_REPORT',
        'T_REVIEW_SETTINGS' => 'REVIEW_SETTING',
        'T_SECTIONS' => 'SECTION',
        'T_SECTION_SETTINGS' => 'SECTION_SETTING',
        'T_TMP_USER' => 'USER_TMP',
        'T_USERS' => 'USER',
        'T_USER_INVITATIONS' => 'USER_INVITATION',
        'T_USER_INVITATION_ANSWER' => 'USER_INVITATION_ANSWER',
        'T_USER_INVITATION_ANSWER_DETAIL' => 'USER_INVITATION_ANSWER_DETAIL',
        'T_USER_MERGE' => 'USER_MERGE',
        'T_USER_ROLES' => 'USER_ROLES',
        'T_USER_TOKENS' => 'USER_TOKEN',
        'T_VOLUMES' => 'VOLUME',
        'T_VOLUME_METADATAS' => 'VOLUME_METADATA',
        'T_VOLUME_PAPER' => 'VOLUME_PAPER',
        'T_VOLUME_PAPER_POSITION' => 'VOLUME_PAPER_POSITION',
        'T_VOLUME_SETTINGS' => 'VOLUME_SETTING',
        'T_VOLUME_PROCEEDING' => 'volume_proceeding',
        'VISITS_TEMP' => 'STAT_TEMP',
        'T_PAPER_FILES' => 'paper_files',
        'T_PAPER_DATASETS' => 'paper_datasets',
        'T_PAPER_LICENCES' => 'paper_licences',
        'T_PAPER_DATASETS_META' => 'paper_datasets_meta',
        'T_PAPER_AUTHORS' => 'authors',
        'T_PAPER_CONFLICTS' => 'paper_conflicts',
        'T_PAPER_METADATA_SOURCES' => 'metadata_sources',
        'T_PAPER_PROJECTS' => 'paper_projects',
        'T_PAPER_CITATIONS' => 'paper_citations',
        'T_PAGES' => 'pages',
        'T_PAPER_CLASSIFICATIONS' => 'paper_classifications',
        'T_PAPER_CLASSIFICATION_MSC2020' => 'classification_msc2020',
        'T_PAPER_CLASSIFICATION_JEL' => 'classification_jel',
        'T_PAPER_DATA_DESCRIPTOR' => 'data_descriptor',
        'T_FILES' => 'files',
    ];

    foreach ($tableConstants as $name => $value) {
        safeDef($name, $value);
    }
}

/**
 * define some simple constants
 */
function defineSimpleConstants(): void
{
    // Define base units first (needed for dependent constants)
    safeDef('KO', 1024);
    safeDef('MO', 1048576);

    // Define remaining constants (some depend on MO)
    $simpleConstants = [
        'DOMAIN' => 'episciences.org',
        'MAX_FILE_SIZE' => 15 * MO,
        'MAX_INPUT_TEXTAREA' => 65000,
        'ABSTRACT_MAX_LENGTH' => 1500,
        'CE_RESOURCES_NAME' => 'episciences.zip',
        'DUPLICATE_ENTRY_SQLSTATE' => 23000,
        'TINYMCE_DIR' => '/js/tinymce/',
        'MAX_PWD_INPUT_SIZE' => 40,
        'MAX_PDF_SIZE' => 500 * MO,
        'ENCODING_TYPE' => 'UTF-8',
        'PORTAL_PREFIX_URL' => '/',
        'PREFIX_ROUTE' => 'rv-code'
    ];

    foreach ($simpleConstants as $name => $value) {
        safeDef($name, $value);
    }
}


/**
 * Constants to include vendor JS Libraries
 */
function defineVendorJsLibraries(): void
{
    $jsLibraries = [
        'VENDOR_BOOTBOX' => 'https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/5.5.3/bootbox.min.js',
        'VENDOR_BOOTSTRAP_COLORPICKER' => 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/js/bootstrap-colorpicker.min.js',
        'VENDOR_BOOTSTRAP_JS' => 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js',
        'VENDOR_DATATABLES_BOOTSTRAP' => 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap.min.js',
        'VENDOR_JQUERY' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js',
        'VENDOR_JQUERY_DATATABLES' => 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js',
        'VENDOR_JQUERY_FILE_UPLOAD' => 'https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/10.32.0/js/jquery.fileupload.min.js',
        'VENDOR_JQUERY_NESTED_SORTABLE' => 'https://cdnjs.cloudflare.com/ajax/libs/nestedSortable/1.3.4/jquery.ui.nestedSortable.min.js',
        'VENDOR_JQUERY_UI' => 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js', // Do not upgrade
        'VENDOR_JQUERY_URL_PARSER' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery-url-parser/2.2.1/purl.min.js',
        'VENDOR_MATHJAX' => 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.7/MathJax.js?config=TeX-AMS-MML_HTMLorMML',
        'VENDOR_TINYMCE' => 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.3.0/tinymce.min.js',
        'VENDOR_TINYMCE_JQUERY' => 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.3.0/tinymce.min.js',
        'VENDOR_CHART' => 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js',
        'VENDOR_CHART_PLUGIN_DATALABELS' => 'https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/0.7.0/chartjs-plugin-datalabels.min.js',
    ];

    foreach ($jsLibraries as $name => $url) {
        safeDef($name, $url);
    }
}

/**
 * Constants to include vendor CSS
 */
function defineVendorCssLibraries(): void
{
    $cssLibraries = [
        'VENDOR_BOOTSTRAP' => 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css',
        'VENDOR_BOOTSTRAP_COLORPICKER_CSS' => 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/css/bootstrap-colorpicker.css',
        'VENDOR_DATATABLES_CSS' => 'https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap.min.css',
        'VENDOR_FONT_AWESOME' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/fontawesome.min.css',
        'VENDOR_FONT_AWESOME_BRAND' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/brands.min.css',
        'VENDOR_FONT_AWESOME_SOLID' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/solid.min.css',
        'VENDOR_JQUERY_UI_THEME_CSS' => 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/flick/jquery-ui.min.css',
        'VENDOR_CHART_CSS' => 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css',
        'VENDOR_COOKIE_CONSENT_CSS' => 'https://cdnjs.cloudflare.com/ajax/libs/cookieconsent/3.1.1/cookieconsent.min.css',
    ];

    foreach ($cssLibraries as $name => $url) {
        safeDef($name, $url);
    }
}

/**
 * Never called
 * Prevent warning from code analyzers for undefined constants
 * @see config pwd.json
 */
function fixUndefinedConstantsForCodeAnalysis(): void
{
    if (0 > 1) {
        $dummyConstants = [
            'EPISCIENCES_EXCEPTIONS_LOG_PATH' => '',
            'CACHE_PATH' => '',
            'APPLICATION_VERSION' => '',
            'PWD_PATH' => '',
            'FUSION_TOKEN_AUTH' => '',
            'EPISCIENCES_LOG_PATH' => '',
            'EPISCIENCES_SOLR_LOG_PATH' => '',
            'EPISCIENCES_API_URL' => '',
            'EPISCIENCES_API_SECRET_KEY' => '',
            'EPISCIENCES_UID' => 0,
            'EPISCIENCES_Z_SUBMIT' => 0,
            'EPISCIENCES_USER_AGENT' => '',
            'EPISCIENCES_SUPPORT' => '',
            'NOTIFY_TARGET_HAL_INBOX' => '',
            'NOTIFY_TARGET_HAL_URL' => '',
            'OPENALEX_MAILTO' => '',
            'OPENALEX_APIURL' => '',
            'CROSSREF_MAILTO' => '',
            'CROSSREF_APIURL' => '',
            'OPENCITATIONS_MAILTO' => '',
            'OPENCITATIONS_APIURL' => '',
            'OPENCITATIONS_TOKEN' => '',
            'CROSSREF_PLUS_API_TOKEN' => '',
            'DOI_AGENCY' => '',
            'DOI_TESTAPI' => '',
            'DOI_API' => '',
            'DOI_LOGIN' => '',
            'DOI_PASSWORD' => '',
            'DOI_TESTAPI_QUERY' => '',
            'DOI_API_QUERY' => '',
            'ENDPOINTS_SEARCH_HOST' => '',
            'ENDPOINTS_SEARCH_PORT' => 0,
            'ENDPOINTS_SEARCH_PATH' => '',
            'ENDPOINTS_SEARCH_TIMEOUT' => 0,
            'ENDPOINTS_SEARCH_USERNAME' => '',
            'ENDPOINTS_SEARCH_PASSWORD' => '',
            'ENDPOINTS_CORENAME' => '',
            'ENDPOINTS_SEARCH_PROTOCOL' => '',
            'ENDPOINTS_INDEXING_HOST' => '',
            'ENDPOINTS_INDEXING_TIMEOUT' => 0,
            'DOI_EMAIL_CONTACT' => '',
            'NOTIFY_TARGET_HAL_LINKED_REPOSITORY' => null,
            'EPISCIENCES_IGNORED_EMAILS_WHEN_INVITING_REVIEWER' => [],
            'EPISCIENCES_BIBLIOREF' => [],
            'OAI' => '',
            'PORTAL' => '',
            'ENV_PROD' => '',
            'ENV_PREPROD' => '',
            'EPISCIENCES_MAIL_PATH' => '',
            'ENV_DEV' => '',
            'MANAGER_APPLICATION_URL' => '',
            'INBOX_ID' => '',
            'INBOX_URL' => '',
            'INBOX_DB_HOST' => '',
            'INBOX_DB_DRIVER' => '',
            'INBOX_DB_USER' => '',
            'INBOX_DB_PASSWORD' => '',
            'INBOX_DB_NAME' => '',
        ];

        foreach ($dummyConstants as $name => $value) {
            define($name, $value);
        }
    }
}
