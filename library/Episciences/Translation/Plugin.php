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
     * E-mail templates (<locale>/emails/*.phtml) live next to the dictionaries but are not
     * translation arrays: they must never be loaded by Zend_Translate.
     */
    public const EMAIL_TEMPLATES_IGNORE_REGEX = '#/[a-z]{2,3}(_[A-Z]{2})?/emails(/|$)#';

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
            $translator = self::createTranslator(PATH_TRANSLATION, defined('REVIEW_PATH') ? REVIEW_PATH . 'languages' : null);
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
        $locale = self::resolveLocale(
            is_string($urlLang) ? $urlLang : null,
            $this->getLocaleCookie(),
            $this->getBrowserLanguage(),
            $allowed
        );

        $translator->setLocale($locale);
        $this->setLocaleCookie($locale);

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
     * Picks the interface language: URL parameter, then cookie, then browser.
     * A missing or unsupported value falls through to the next source.
     * Defaults to French when allowed, otherwise to the first allowed language.
     *
     * @param string[] $allowed languages offered by the journal (must not be empty)
     */
    public static function resolveLocale(?string $urlLang, ?string $cookieLang, ?string $browserLang, array $allowed): string
    {
        foreach ([$urlLang, $cookieLang, $browserLang] as $candidate) {
            if ($candidate !== null && in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        return in_array(self::LANG_FR, $allowed, true) ? self::LANG_FR : (string)reset($allowed);
    }

    /**
     * Builds the translator from the application dictionaries, then the journal ones.
     *
     * Application files are loaded explicitly in a fixed (sorted) order because, on duplicate keys,
     * the last loaded file wins: a directory scan would depend on the filesystem order.
     * Journal dictionaries are loaded last so that they override the application ones.
     *
     * The 'scan' and 'ignore' options are kept by the adapter: later addTranslation() calls on a
     * languages directory (journal translations in OAI, TEI...) still detect the locale from the
     * sub-directory name and skip the e-mail templates.
     *
     * @throws Zend_Translate_Exception when no dictionary is found
     */
    public static function createTranslator(string $applicationLanguagesPath, ?string $journalLanguagesPath = null): Zend_Translate
    {
        $translator = null;

        foreach (self::getLanguageDirectories($applicationLanguagesPath) as $lang => $directory) {
            $files = glob($directory . '/*.php') ?: [];
            sort($files, SORT_STRING);

            foreach ($files as $file) {
                $options = ['content' => $file, 'locale' => $lang];

                if ($translator === null) {
                    $translator = new Zend_Translate(['adapter' => Zend_Translate::AN_ARRAY] + $options + [
                            'scan' => Zend_Translate::LOCALE_DIRECTORY,
                            'disableNotices' => true,
                            'ignore' => ['.', 'regex_emails' => self::EMAIL_TEMPLATES_IGNORE_REGEX],
                        ]);
                } else {
                    $translator->addTranslation($options);
                }
            }
        }

        if ($translator === null) {
            throw new Zend_Translate_Exception('No translation file found in ' . $applicationLanguagesPath);
        }

        if ($journalLanguagesPath !== null && is_dir($journalLanguagesPath)) {
            $translator->addTranslation($journalLanguagesPath);
        }

        return $translator;
    }

    /**
     * @return array<string, string> locale => directory, sorted by locale
     */
    private static function getLanguageDirectories(string $languagesPath): array
    {
        $directories = [];

        foreach (glob(rtrim($languagesPath, '/') . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $lang = basename($directory);
            if (Zend_Locale::isLocale($lang, true, false)) {
                $directories[$lang] = $directory;
            }
        }

        ksort($directories, SORT_STRING);
        return $directories;
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
