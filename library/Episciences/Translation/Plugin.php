<?php

/**
 * Plugin de traduction pour Episciences
 *
 */
class Episciences_Translation_Plugin extends Zend_Controller_Plugin_Abstract
{

    const LANG_FR = 'fr';
    const LANG_EN = 'en';

    /**
     * Essaie de trouver une langue disponible d'après :
     * 1 - Une URL en paramètre
     * 2 - la session
     * 3 - La langue envoyée par la navigateur
     * 4 - La langue par défaut
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        //Initialisation des langues de l'interface
        $this->initLanguages();

        // teste url
        $translator = $this->getLocaleByUrl($request);

        // sinon teste session
        if ($translator == null) {
            $translator = $this->getLocaleBySession();
        }

        // sinon teste browser
        if ($translator == null) {
            $translator = $this->getLocaleByBrowser();
        }

        // sinon teste lang par default
        if ($translator == null) {
            $translator = $this->checkTranslator(self::LANG_FR);
        }

        if (!in_array($translator->getLocale(), Zend_Registry::get('languages'))) {
            $translator->setLocale(Zend_Registry::get('languages')[0]);
        }

        /**
         * log des chaines non traduites
         */

        /*if (APPLICATION_ENV == 'development') {

            if ($translator->getLocale() != self::LANG_FR) {

                $writer = new Zend_Log_Writer_Stream(realpath(sys_get_temp_dir()) . '/traductionsManquantes_' . $translator->getLocale() . '.log');
                $log = new Zend_Log($writer);

                $translator->setOptions(array(
                        'log' => $log,
                        'logMessage' => "Locale %locale% - manque : '%message%'",
                        'logUntranslated' => true
                ));
            }
        }*/
        /**
         * log des chaines non traduites //
         */

        $localeSession = new Zend_Session_Namespace('Zend_Translate');
        $localeSession->lang = $translator->getLocale();

        Zend_Registry::set('lang', $translator->getLocale());
        Zend_Registry::set('Zend_Translate', $translator);
        Zend_Registry::set('Zend_Locale', new Zend_Locale($translator->getLocale()));
    }

    /**
     * Initialisation des langues disponibles de l'interface
     */
    private function initLanguages()
    {
        if (!Zend_Registry::isRegistered('languages')) {
            $website = new Ccsd_Website_Common(RVID, array('sidField' => 'SID'));
            $languages = $website->getLanguages();
            if (count($languages) == 0) {
                $languages = self::getAvalaibleLanguages();
            }
            Zend_Registry::set('languages', $languages);
        }
    }

    /**
     * Retourne les langues disponibles de la plateforme
     * @return array
     */
    public static function getAvalaibleLanguages()
    {
        $languages = array();
        $reflect = new ReflectionClass('Episciences_Translation_Plugin');
        foreach ($reflect->getConstants() as $const => $value) {
            if (substr($const, 0, 5) === 'LANG_') {
                $languages[] = $value;
            }
        }
        return $languages;
    }

    /**
     * Retourne la langue en fonction du navigateur
     *
     * @return Ambigous <NULL, Zend_Translate>
     */
    private function getLocaleByBrowser()
    {
        try {
            $browserLocale = new Zend_Locale(Zend_Locale::BROWSER);
            if (strlen($browserLocale) > 2) {
                $browserLocale = substr($browserLocale, 0, 2);
            }
            return $this->checkTranslator($browserLocale);
        } catch (Zend_Locale_Exception $e) {
            return null;
        }
    }

    /**
     * Retourne la langue en fonction du paramètre dans l'URL
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return Ambigous <NULL, Zend_Translate>
     */
    private function getLocaleByUrl(Zend_Controller_Request_Abstract $request)
    {
        return $this->checkTranslator($request->getParam('lang', null));
    }

    /**
     * Retourne la langue en fonction de la session
     *
     * @return Ambigous <NULL, Zend_Translate>
     */
    private function getLocaleBySession()
    {
        $localeSession = new Zend_Session_Namespace('Zend_Translate');
        $lang = (isset($localeSession->lang)) ? $localeSession->lang : null;
        return $this->checkTranslator($lang);
    }

    /**
     * Ajoute une traduction si la langue existe
     *
     * @param string $language
     * @return NULL Zend_Translate
     */
    private function checkTranslator($language = null)
    {
        if ($language == null) {
            return null;
        }

        if (!in_array($language, Zend_Registry::get('languages'))) {
            $language = self::LANG_FR;
        }

        $translator = new Zend_Translate(Zend_Translate::AN_ARRAY, PATH_TRANSLATION, null, array(
            'scan' => Zend_Translate::LOCALE_DIRECTORY,
            'disableNotices' => true
        ));

        if (is_dir(APPLICATION_PATH . '/languages') && count(scandir(APPLICATION_PATH . '/languages')) > 2) {
            $translator->addTranslation(APPLICATION_PATH . '/languages');
        }

        if (is_dir(REVIEW_PATH . 'languages') && count(scandir(REVIEW_PATH . 'languages')) > 2) {
            $translator->addTranslation(REVIEW_PATH . 'languages');
        }

        if ($translator->isAvailable($language)) {
            $translator->setLocale($language);
            return $translator;
        } else {
            return null;
        }
    }
}
