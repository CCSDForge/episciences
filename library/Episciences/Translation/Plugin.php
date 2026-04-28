<?php

use Episciences\Trait\LocaleByCookieTrait;
use Psr\Log\LogLevel;

/**
 * Plugin de traduction pour Episciences
 *
 */
class Episciences_Translation_Plugin extends Zend_Controller_Plugin_Abstract
{
    use LocaleByCookieTrait;

    public const LANG_FR = 'fr';
    public const LANG_EN = 'en';

    /**
     * @var string[] Application languages
     */
    protected static array $_availableLanguages = [self::LANG_EN, self::LANG_FR];


    /**
     * Essaie de trouver une langue disponible d'après :
     * 1 - Une URL en paramètre
     * 2 - la session
     * 3 - La langue envoyée par la navigateur
     * 4 - La langue par défaut
     * @throws Zend_Exception
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request): void
    {
        //Initialisation des langues de l'interface
        $this->initLanguages();
        $translator = null;

        // teste url
        try {
            $translator = $this->getLocaleByUrl($request);
        } catch (Zend_Exception $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
        }

        // sinon teste session
        if ($translator === null) {
            try {
                $translator = $this->getLocalFromCookie();
            } catch (Zend_Exception $e) {
                Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            }
        }

        // sinon teste browser
        if ($translator === null) {
            $translator = $this->getLocaleByBrowser();
        }

        // sinon teste lang par default
        if ($translator === null) {
            try {
                $translator = $this->checkTranslator(self::LANG_FR);
            } catch (Zend_Exception $e) {
                Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            }
        }

        $locale = $translator->getLocale();
        $this->setLocaleCookie($locale);

        if (!in_array($locale, Zend_Registry::get('languages'), true)) {
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

        Zend_Registry::set('lang', $translator->getLocale());
        Zend_Registry::set('Zend_Translate', $translator);
        Zend_Registry::set('Zend_Locale', new Zend_Locale($translator->getLocale()));
    }

    /**
     * Initialisation des langues disponibles de l'interface
     */
    private function initLanguages(): void
    {
        if (!Zend_Registry::isRegistered('languages')) {
            $website = new Ccsd_Website_Common(RVID, array('sidField' => 'SID'));
            $languages = $website->getLanguages();
            if (count($languages) === 0) {
                $languages = self::getAvailableLanguages();
            }
            Zend_Registry::set('languages', $languages);
        }
    }

    /**
     * Retourne les langues disponibles de la plateforme
     * @return array
     */
    public static function getAvailableLanguages(): array
    {
        return self::$_availableLanguages;
    }

    /**
     * Retourne la langue en fonction du navigateur
     *
     * @return Zend_Translate|null
     */
    private function getLocaleByBrowser(): ?Zend_Translate
    {
        try {
            $browserLocale = new Zend_Locale(Zend_Locale::BROWSER);
            if (strlen($browserLocale) > 2) {
                $browserLocale = substr($browserLocale, 0, 2);
            }
            return $this->checkTranslator($browserLocale);
        } catch (Zend_Exception $e) {
            return null;
        }
    }

    /**
     * Retourne la langue en fonction du paramètre dans l'URL
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return Zend_Translate|null
     * @throws Zend_Exception
     * @throws Zend_Translate_Exception
     */
    private function getLocaleByUrl(Zend_Controller_Request_Abstract $request): ?Zend_Translate
    {
        return $this->checkTranslator($request->getParam('lang'));
    }

    /**
     * Retourne la langue en fonction de la session
     *
     * @return Zend_Translate|null
     * @throws Zend_Exception
     * @throws Zend_Translate_Exception
     */
    private function getLocaleBySession(): ?Zend_Translate
    {
        $localeSession = new Zend_Session_Namespace('Zend_Translate');
        $lang = $localeSession->lang ?? null;
        return $this->checkTranslator($lang);
    }

    /**
     * @return Zend_Translate|null
     * @throws Zend_Exception
     * @throws Zend_Translate_Exception
     */
    private function getLocalFromCookie(): ?Zend_Translate
    {
        return $this->checkTranslator($this->getLocaleCookie());
    }

    /**
     * Ajoute une traduction si la langue existe
     *
     * @param string|null $language
     * @return Zend_Translate|null Zend_Translate
     * @throws Zend_Exception
     * @throws Zend_Translate_Exception
     */
    private function checkTranslator(string $language = null): ?Zend_Translate
    {
        if ($language === null) {
            return null;
        }

        if (!in_array($language, Zend_Registry::get('languages'), true)) {
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
        }

        return null;
    }

}
