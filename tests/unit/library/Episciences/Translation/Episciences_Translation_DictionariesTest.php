<?php

namespace unit\library\Episciences\Translation;

use PHPUnit\Framework\TestCase;

/**
 * Guards the application dictionaries (application/languages/<lang>/*.php) against silent conflicts:
 * when two files define the same key with different values, only the last loaded one is used.
 *
 * js.php is also served alone to the browser (public/js/translation.php): a few keys intentionally
 * differ from views.php (plain text for JS, HTML icon for server-side views).
 */
final class Episciences_Translation_DictionariesTest extends TestCase
{
    /**
     * Keys allowed to differ between js.php and views.php.
     */
    private const JS_VIEWS_ALLOWED_CONFLICTS = [
        'en' => [
            'editorial_board', 'technical_board', 'scientific_advisory_board', 'advisory_board',
            'managing_editor', 'handling_editor', 'former_member',
            'Note globale', 'Ma photo', 'Créer un utilisateur', 'Afficher',
        ],
        'fr' => [
            'editorial_board', 'technical_board', 'scientific_advisory_board', 'advisory_board',
            'managing_editor', 'handling_editor', 'former_member',
        ],
    ];

    /**
     * @dataProvider languageProvider
     */
    public function testNoConflictingKeysBetweenDictionaries(string $lang): void
    {
        $conflicts = $this->findConflicts($lang);
        $allowed = self::JS_VIEWS_ALLOWED_CONFLICTS[$lang];

        $unexpected = array_filter(
            $conflicts,
            static fn(array $c): bool => !($c['files'] === ['js.php', 'views.php'] && in_array($c['key'], $allowed, true))
        );

        self::assertSame([], array_values($unexpected), "Conflicting translations in application/languages/$lang");
    }

    /**
     * Keeps the allow-list tight: remove an entry once the conflict is resolved.
     *
     * @dataProvider languageProvider
     */
    public function testAllowedConflictsStillExist(string $lang): void
    {
        $conflictingKeys = array_column($this->findConflicts($lang), 'key');

        self::assertSame(
            [],
            array_values(array_diff(self::JS_VIEWS_ALLOWED_CONFLICTS[$lang], $conflictingKeys)),
            'Stale entries in JS_VIEWS_ALLOWED_CONFLICTS'
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function languageProvider(): array
    {
        return ['en' => ['en'], 'fr' => ['fr']];
    }

    /**
     * @return list<array{key: string, files: array{string, string}}>
     */
    private function findConflicts(string $lang): array
    {
        $files = glob(PATH_TRANSLATION . '/' . $lang . '/*.php');
        sort($files, SORT_STRING);

        $seen = [];
        $conflicts = [];

        foreach ($files as $file) {
            $name = basename($file);
            foreach (include $file as $key => $value) {
                $key = (string)$key;
                if (isset($seen[$key]) && $seen[$key]['value'] !== $value) {
                    $conflicts[] = ['key' => $key, 'files' => [$seen[$key]['file'], $name]];
                }
                $seen[$key] = ['file' => $name, 'value' => $value];
            }
        }

        return $conflicts;
    }
}
