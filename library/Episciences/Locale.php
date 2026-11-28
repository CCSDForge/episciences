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
     * Languages always listed first, in this order, ahead of the alphabetical remainder
     * (callers such as Episciences_Review::getDefaultLanguage() rely on this order).
     */
    private const array TOP_LANGUAGES =['en', 'fr', 'de', 'it', 'es'];

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
     * Returns all localized language names keyed by ISO 639 code: the TOP_LANGUAGES first,
     * in that order, then the others sorted by name.
     *
     * @param string|null $displayLocale locale of the returned names; null = current application locale
     * @return array<string, string>
     */
    public static function getLanguageNames(?string $displayLocale = null): array
    {
        $displayLocale ??= self::getCurrentLanguage();

        try {
            $names = Languages::getNames($displayLocale);
        } catch (MissingResourceException) {
            $names = Languages::getNames('en');
        }

        // array_replace() keeps the key order of its first argument (TOP_LANGUAGES order)
        // and appends the remaining keys in their original (alphabetical by name) order
        $topKeys = array_intersect_key(array_flip(self::TOP_LANGUAGES), $names);

        return array_replace($topKeys, $names);
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
