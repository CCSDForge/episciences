<?php

declare(strict_types=1);

namespace Episciences\Translation;

use Zend_Locale;
use Zend_Translate;
use Zend_Translate_Exception;

/**
 * Builds the application translator, shared by the web plugin and the CLI scripts.
 */
final class TranslatorFactory
{
    /**
     * E-mail templates (<locale>/emails/*.phtml) live next to the dictionaries but are not
     * translation arrays: they must never be loaded by Zend_Translate.
     */
    public const EMAIL_TEMPLATES_IGNORE_REGEX = '#/[a-z]{2,3}(_[A-Z]{2})?/emails(/|$)#';

    /**
     * Builds the translator from the application dictionaries, then the journal ones.
     *
     * Application files are loaded explicitly in a fixed (sorted) order because, on duplicate keys,
     * the last loaded file wins: a directory scan would depend on the filesystem order.
     * Journal dictionaries are loaded last so that they override the application ones.
     *
     * The 'scan' and 'ignore' options are kept by the adapter: later addTranslation() calls on a
     * languages directory (journal translations in OAI, TEI, reminders...) still detect the locale
     * from the sub-directory name and skip the e-mail templates.
     *
     * @param string|null $locale current locale; 'auto' detects it from the environment,
     *                            null leaves the last loaded one (callers are expected to set it)
     * @throws Zend_Translate_Exception when no dictionary is found
     */
    public static function create(string $applicationLanguagesPath, ?string $journalLanguagesPath = null, ?string $locale = null): Zend_Translate
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

        if ($locale !== null) {
            $translator->setLocale($locale);
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
}
