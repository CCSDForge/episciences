<?php
namespace Episciences\Tools;
use Symfony\Component\Intl\Languages;
class Translations
{
    /**
     * Find the ISO language code (e.g., 'en') from a language name.
     * // Example usage:
     * $code = findLanguageCode('English', ['en', 'fr', 'de']);
     * var_dump($code); // string(2) "en"
     *
     * $code = findLanguageCode('Anglais', ['fr']);
     * var_dump($code); // string(2) "en"
     *
     * $code = findLanguageCode('Englisch', ['de']);
     * var_dump($code); // string(2) "en"
     *
     * @param string $name The language name to search for (case-insensitive).
     * @param array $locales Locales to search through (default ['en']).
     *
     * @return string|null    The language code or null if not found.
     */
    public static function findLanguageCodeByLanguageName(string $name, array $locales = ['en']): ?string
    {
        $nameLower = mb_strtolower(trim($name));

        foreach ($locales as $locale) {
            $languages = Languages::getNames($locale);

            // Lowercase keys to allow case-insensitive search
            $lowerMap = array_map('mb_strtolower', $languages);

            $code = array_search($nameLower, $lowerMap, true);
            if ($code !== false) {
                return $code;
            }
        }

        return null;
    }

}