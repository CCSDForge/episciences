<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the journal scoping of volume and section actions.
 *
 * Volume and section ids come from request parameters, and several journals share the
 * same tables: every lookup that starts from such an id must be restricted to the current
 * journal (RVID). Source-analysis tests (ZF1 controllers are not instantiable in isolation)
 * assert that the restriction stays in place.
 */
final class JournalScopeGuardTest extends TestCase
{
    private const CONTROLLERS = APPLICATION_PATH . '/modules/journal/controllers/';

    private function extractMethod(string $file, string $methodName): string
    {
        $source = (string) file_get_contents($file);
        $start = strpos($source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in " . basename($file));

        $candidates = array_filter([
            strpos($source, "\n    public function ", $start + 1),
            strpos($source, "\n    protected function ", $start + 1),
            strpos($source, "\n    private function ", $start + 1),
        ], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($source);

        return substr($source, $start, $stop - $start);
    }

    /**
     * Every find() on a volume or a section must receive RVID as second argument.
     */
    private function assertEveryLookupIsScoped(string $method, string $label): void
    {
        $count = preg_match_all('/(?:Volumes|Sections)Manager::find\(([^;]*);/', $method, $matches);
        self::assertGreaterThan(0, $count, "$label must look the volume/section up");

        foreach ($matches[1] as $arguments) {
            self::assertMatchesRegularExpression('/,\s*RVID\s*\)/', $arguments,
                "$label must restrict the volume/section lookup to the current journal");
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function scopedActions(): array
    {
        $actions = [];

        foreach (['VolumeController', 'SectionController'] as $controller) {
            foreach (['deleteAction', 'editorsformAction', 'saveeditorsAction', 'displayeditorsAction'] as $action) {
                $actions["$controller::$action"] = [$controller, $action];
            }
        }

        foreach ([
            'savemastervolumeAction',
            'saveothervolumesAction',
            'savesectionAction',
            'refreshmastervolumeAction',
            'editorsformAction',
            'copyeditorsformAction',
        ] as $action) {
            $actions["AdministratepaperController::$action"] = ['AdministratepaperController', $action];
        }

        return $actions;
    }

    /**
     * @dataProvider scopedActions
     */
    public function testActionScopesVolumeOrSectionLookupToTheCurrentJournal(string $controller, string $action): void
    {
        $method = $this->extractMethod(self::CONTROLLERS . $controller . '.php', $action);

        $this->assertEveryLookupIsScoped($method, "$controller::$action");
    }

    /**
     * @dataProvider unknownItemActions
     */
    public function testActionRefusesAnItemOfAnotherJournal(string $controller, string $action): void
    {
        $method = $this->extractMethod(self::CONTROLLERS . $controller . '.php', $action);

        self::assertMatchesRegularExpression('/if \(!\$(?:volume|section)\)|\?\s*Episciences_(?:Volumes|Sections)Manager::delete|!Episciences_(?:Volumes|Sections)Manager::find\(/', $method,
            "$controller::$action must stop when the item does not belong to the current journal");
    }

    /**
     * The actions rendering a view must switch the rendering off before refusing an item of
     * another journal, otherwise ZF1 renders a default script (or a full layout) instead of
     * an empty answer.
     *
     * @dataProvider renderingActions
     */
    public function testRefusalStopsTheRenderingOfActionsWithAView(string $controller, string $action, string $variable): void
    {
        $method = $this->extractMethod(self::CONTROLLERS . $controller . '.php', $action);

        $found = preg_match(
            '/if \(!\$' . $variable . '\) \{(?<body>[^}]*)\}/',
            $method,
            $matches
        );
        self::assertSame(1, $found, "$controller::$action must refuse an unknown $variable");
        self::assertStringContainsString('disableLayout()', $matches['body'], "$controller::$action must disable the layout");
        self::assertStringContainsString('setNoRender()', $matches['body'], "$controller::$action must disable the rendering");
        self::assertMatchesRegularExpression('/\breturn\b/', $matches['body'], "$controller::$action must stop");
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function renderingActions(): array
    {
        return [
            'volume editors form' => ['VolumeController', 'editorsformAction', 'volume'],
            'volume display editors' => ['VolumeController', 'displayeditorsAction', 'volume'],
            'section editors form' => ['SectionController', 'editorsformAction', 'section'],
            'section display editors' => ['SectionController', 'displayeditorsAction', 'section'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unknownItemActions(): array
    {
        return [
            'volume delete' => ['VolumeController', 'deleteAction'],
            'volume editors form' => ['VolumeController', 'editorsformAction'],
            'volume save editors' => ['VolumeController', 'saveeditorsAction'],
            'volume display editors' => ['VolumeController', 'displayeditorsAction'],
            'section delete' => ['SectionController', 'deleteAction'],
            'section editors form' => ['SectionController', 'editorsformAction'],
            'section save editors' => ['SectionController', 'saveeditorsAction'],
            'section display editors' => ['SectionController', 'displayeditorsAction'],
            'paper master volume' => ['AdministratepaperController', 'savemastervolumeAction'],
            'paper other volumes' => ['AdministratepaperController', 'saveothervolumesAction'],
            'paper section' => ['AdministratepaperController', 'savesectionAction'],
        ];
    }

    /**
     * Both POSITION updates (explicit sort and re-sort of the remaining items) must stay
     * limited to the current journal.
     */
    public function testSortRestrictsEveryPositionUpdateToTheCurrentJournal(): void
    {
        $source = (string) file_get_contents(
            dirname(APPLICATION_PATH) . '/library/Episciences/VolumesAndSectionsManager.php'
        );

        $count = preg_match_all('/->update\(\$table,[^;]*;/', $source, $matches);
        self::assertSame(2, $count, 'sort() is expected to update POSITION in two places');

        foreach ($matches[0] as $update) {
            self::assertStringContainsString("'RVID = ?' => RVID", $update,
                'POSITION updates must be restricted to the current journal');
        }
    }
}
