<?php

declare(strict_types=1);

namespace Episciences;

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Languages;
use Zend_Locale;
use Zend_Registry;

/**
 * Language name resolution backed by Symfony Intl (CLDR).
 */
final class Locale
{
    /**
     * Returns the localized name of a language from its ISO 639 code.
     *
     * @param string $code language code (e.g. 'fr', 'en')
     * @param string|null $displayLocale locale of the returned name; null = current application locale
     * @return string the language name, or $code itself when it cannot be resolved
     */
    public static function getLanguageName(string $code, ?string $displayLocale = null): string
    {
        if ($code === '') {
            return '';
        }

        $displayLocale ??= self::getCurrentLanguage();

        try {
            return Languages::getName(strtolower($code), $displayLocale);
        } catch (MissingResourceException) {
            return $code;
        }
    }

    /**
     * Returns all localized language names keyed by ISO 639 code.
     *
     * @param string|null $displayLocale locale of the returned names; null = current application locale
     * @return array<string, string>
     */
    public static function getLanguageNames(?string $displayLocale = null): array
    {
        $displayLocale ??= self::getCurrentLanguage();

        try {
            return Languages::getNames($displayLocale);
        } catch (MissingResourceException) {
            return Languages::getNames('en');
        }
    }

    /**
     * Current application language: registry 'Zend_Locale' (set in web requests and by every
     * CLI entry point), 'en' when unavailable (tests).
     */
    private static function getCurrentLanguage(): string
    {
        if (Zend_Registry::isRegistered('Zend_Locale')) {
            $locale = Zend_Registry::get('Zend_Locale');
            if ($locale instanceof Zend_Locale) {
                return (string) $locale->getLanguage();
            }

            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        return 'en';
    }
}
