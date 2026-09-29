<?php

use Episciences\Trait\LocaleByCookieTrait;
use Episciences\Translation\TranslatorFactory;
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
     * preDispatch() runs again for every internal forward (e.g. journal index -> page):
     * the language is resolved once per request.
     */
    private bool $translatorRegistered = false;


    /**
     * Resolves the interface language and registers the translator
     * @throws Zend_Exception
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request): void
    {
        if ($this->translatorRegistered && Zend_Registry::isRegistered('Zend_Translate')) {
            return;
        }

        //Initialisation des langues de l'interface
        $this->initLanguages();

        try {
            $translator = TranslatorFactory::create(PATH_TRANSLATION, defined('REVIEW_PATH') ? REVIEW_PATH . 'languages' : null);
        } catch (Zend_Exception $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            throw $e;
        }

        $languages = array_values(Zend_Registry::get('languages'));
        $allowed = array_values(array_intersect($languages, $translator->getList() ?? []));
        if ($allowed === []) {
            $allowed = $languages;
        }

        $urlLang = $request->getParam('lang');
        $urlLang = is_string($urlLang) ? $urlLang : null;
        $locale = self::resolveLocale(
            $urlLang,
            $this->getLocaleCookie(),
            $this->getAccountLanguage(),
            $this->getBrowserLanguage(),
            $allowed
        );

        $translator->setLocale($locale);

        // Only an explicit choice is remembered: otherwise the same sources give the same result next time
        if ($locale === $urlLang) {
            $this->setLocaleCookie($locale);
        }

        Zend_Registry::set('lang', $locale);
        Zend_Registry::set('Zend_Translate', $translator);
        Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
        $this->translatorRegistered = true;
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
     * @return string[]
     */
    public static function getAvailableLanguages(): array
    {
        return self::$_availableLanguages;
    }

    /**
     * Picks the interface language: URL parameter, then cookie (last explicit choice),
     * then the logged-in user's account language, then browser.
     * A missing or unsupported value falls through to the next source.
     * Defaults to French when allowed, otherwise to the first allowed language.
     *
     * @param string[] $allowed languages offered by the journal (must not be empty)
     */
    public static function resolveLocale(?string $urlLang, ?string $cookieLang, ?string $accountLang, ?string $browserLang, array $allowed): string
    {
        foreach ([$urlLang, $cookieLang, $accountLang, $browserLang] as $candidate) {
            if ($candidate !== null && in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        return in_array(self::LANG_FR, $allowed, true) ? self::LANG_FR : (string)reset($allowed);
    }

    /**
     * Language saved in the logged-in user's account
     */
    private function getAccountLanguage(): ?string
    {
        try {
            $lang = Episciences_Auth::isLogged() ? Episciences_Auth::getLangueid() : null;
        } catch (Throwable $e) {
            return null;
        }

        return is_string($lang) && $lang !== '' ? $lang : null;
    }

    /**
     * Retourne la langue préférée du navigateur (code sur 2 lettres)
     */
    private function getBrowserLanguage(): ?string
    {
        try {
            $browserLocale = (string)new Zend_Locale(Zend_Locale::BROWSER);
        } catch (Zend_Exception $e) {
            return null;
        }

        return $browserLocale !== '' ? substr($browserLocale, 0, 2) : null;
    }

}
