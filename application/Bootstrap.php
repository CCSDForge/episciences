<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{

    /**
     * Chargement automatique des différents modèles
     *
     * @return Zend_Loader_Autoloader
     */
    protected function _initAutoload(): \Zend_Loader_Autoloader
    {
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
        return $autoloader;
    }

    protected function _initDb(): \Zend_Db_Adapter_Abstract
    {
        $dbDriverOption1002Const = $this->getOption('resources')['db']['driver_options']['1002']; // see application.ini
        Zend_Db_Table::setDefaultAdapter($this->getPluginResource('db')->getDbAdapter());
        Zend_Db_Table_Abstract::getDefaultAdapter()->getConnection()->exec($dbDriverOption1002Const);
        return Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    protected function _initModule()
    {

        if (APPLICATION_MODULE === 'oai') {
            defined('RVID') || define('RVID', 0);
            defined('RVNAME') || define('RVNAME', 'OAI Episciences');
            defined('PIWIKID') || define('PIWIKID', 0);
        } else {
            $oReview = Episciences_ReviewsManager::find(RVCODE);
            if ($oReview) {
                defined('RVID') || define('RVID', $oReview->getRvid());
                defined('RVNAME') || define('RVNAME', $oReview->getName());
                defined('PIWIKID') || define('PIWIKID', $oReview->getPiwikid());
                $oReview->loadSettings();
                Zend_Registry::set('reviewSettings', $oReview->getSettings());
                Zend_Registry::set('reviewSettingsDoi', $oReview->getDoiSettings());
                defined('RVISSN') || define('RVISSN', $oReview->getSetting(Episciences_Review::SETTING_ISSN));
            } else {
                exit("Configuration Error: This journal does not exists.");
            }
        }

        define('SESSION_NAMESPACE', APPLICATION_MODULE . '-' . RVCODE);
    }

    /**
     * @throws Zend_Session_Exception
     */
    protected function _initSession()
    {
        $options = $this->getOptions();
        $sessionOptions = [
            'name' => SESSION_NAMESPACE,
            'cookie_httponly' => $options['resources']['session']['cookie_httponly'],
            'cookie_secure' => $options['resources']['session']['cookie_secure']
        ];
        Zend_Session::setOptions($sessionOptions);
        Zend_Session::start();
    }

    // Initialisation du log des exceptions
    protected function _initLog()
    {
        try {
            $writer = new Zend_Log_Writer_Stream(EPISCIENCES_EXCEPTIONS_LOG_PATH . RVCODE . '.exceptions.log');
            $logger = new Zend_Log($writer);
            Zend_Registry::set('Logger', $logger);
        } catch (Zend_Log_Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * Ajout des Helpers de vue
     *
     * @return Zend_View
     */
    protected function _initView(): \Zend_View
    {
        $view = new Zend_View();

        $view->setEncoding('utf-8');
        $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html; charset=utf-8');

        $view->addHelperPath('ZendX/JQuery/View/Helper', 'ZendX_JQuery_View_Helper');
        $view->addHelperPath('Ccsd/View/Helper', 'Ccsd_View_Helper');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');


        $view->addScriptPath(APPLICATION_PATH . '/modules/common/views/scripts');
        /** @var Zend_Controller_Action_Helper_ViewRenderer $viewRenderer */
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        $viewRenderer->setView($view);

        return $view;
    }

    /**
     * Définition du DOCTYPE
     */
    protected function _initDoctype()
    {
        $doctypeHelper = new Zend_View_Helper_Doctype();
        try {
            $doctypeHelper->doctype('XHTML1_STRICT');
        } catch (Zend_View_Exception $e) {
            error_log($e->getMessage());
        }
    }

    protected function _initAcl()
    {
        if (APPLICATION_MODULE === 'oai') {
            return;
        }
        //Chargement des Acl et de la navigation
        $acl = new Episciences_Acl();

        // Fichier de configuration global (de l'appli)
        $globalNavigationFile = APPLICATION_PATH . '/configs/' . APPLICATION_MODULE . '.navigation.json';

        // Fichier de configuration local (par revue)
        if (is_file(REVIEW_PATH . '/config/navigation.json')) {
            // De la revue, si il existe
            $localNavigationFile = REVIEW_PATH . '/config/navigation.json';
        } else {
            // Sinon, par défaut
            $localNavigationFile = APPLICATION_PATH . '/../data/default/config/navigation.json';
        }
        $navigationFiles = [$globalNavigationFile, $localNavigationFile];

        $acl->loadFromNavigation($navigationFiles);

        $config = new Zend_Config_Json($localNavigationFile, null, ['ignoreconstants' => true]);

        //Chargement de la navigation
        if (Episciences_Auth::isLogged()) {
            $connectedConfig = new Zend_Config_Json($globalNavigationFile, null, ['ignoreconstants' => true]);
        } else {
            $connectedConfig = new Zend_Config_Json(APPLICATION_PATH . '/configs/' . APPLICATION_MODULE . '.guest.navigation.json', null, ['ignoreconstants' => true]);
        }

        if ($config) {
            $config = new Zend_Config(array_merge($config->toArray(), $connectedConfig->toArray()));
        } else {
            $config = $connectedConfig;
        }

        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $navigation = new Ccsd_Navigation($config);

        //Initialisation du menu
        $viewRenderer->view->nav($navigation)
            ->setAcl($acl)
            ->setRoles(Episciences_Auth::getRoles());

        return $acl;
    }

    /**
     * Cache Zend_Db_Table
     * @see http://framework.zend.com/manual/1.12/fr/zend.db.table.html#zend.db.table.metadata.caching
     */
    protected function _initZend_Db_TableCache()
    {
        $frontendOptions = [
            'cache_id_prefix' => 'epi',
            'automatic_cleaning_factor' => 1,
            'lifetime' => 120,
            'automatic_serialization' => true
        ];
        $backendOptions = [
            'cache_dir' => sys_get_temp_dir()
        ];
        $dbCache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
        Zend_Db_Table_Abstract::setDefaultMetadataCache($dbCache);
    }


    protected function _initApplicationVersion()
    {
        define('APPLICATION_VERSION', Episciences_Settings::getApplicationVersion()['gitHash']);
    }

    protected function _initcheckApplicationDirectories()
    {
        // Verification de l'existence des dossiers de stockage, creation si necessaire
        $folders = [
            REVIEW_PATH . 'config',
            REVIEW_PATH . 'files',
            REVIEW_PATH . 'languages',
            REVIEW_PATH . 'layout',
            REVIEW_PATH . 'public',
            REVIEW_PATH . 'tmp'];
        foreach ($folders as $folder) {
            if (!file_exists($folder)) {
                $resMkdir = mkdir($folder, 0770, true);
                if (!$resMkdir) {
                    die('Fatal error, no configuration folder and unable to create folder: ' . $folder);
                }
            }
        }
    }
}
