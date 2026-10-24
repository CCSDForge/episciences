<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_SectionsManager;
use Episciences_VolumesAndSectionsManager;
use Episciences_VolumesManager;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;

/**
 * Journal scoping of volumes and sections.
 *
 * Volume and section ids come from request parameters, and several journals share the same
 * tables: a lookup, a deletion or a re-ordering must never reach an item of another journal.
 * Queries are built for real and recorded, nothing is executed.
 */
final class JournalScopeGuardTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        self::loadController('journal', 'VolumeController');
        self::loadController('journal', 'SectionController');
    }

    // ------------------------------------------------------------------
    // Library: lookups and sort
    // ------------------------------------------------------------------

    public function testVolumeLookupIsRestrictedToTheJournal(): void
    {
        self::assertFalse(Episciences_VolumesManager::find(7, 3));

        self::assertCount(1, $this->db->selects());
        self::assertStringContainsString('VID = 7', $this->db->selects()[0]);
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $this->db->selects()[0]);
    }

    public function testSectionLookupIsRestrictedToTheJournal(): void
    {
        self::assertFalse(Episciences_SectionsManager::find(7, 3));

        self::assertCount(1, $this->db->selects());
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $this->db->selects()[0]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sortProvider(): array
    {
        return [
            'volumes' => ['VID', 'volume_', T_VOLUMES],
            'sections' => ['SID', 'section_', T_SECTIONS],
        ];
    }

    /**
     * Both POSITION updates (explicit order, then the remaining items) must be limited to the
     * current journal, even when the posted ids are those of another journal.
     *
     * @dataProvider sortProvider
     */
    public function testSortOnlyUpdatesItemsOfTheCurrentJournal(string $colId, string $prefix, string $table): void
    {
        $this->db->respondTo('/./', [['ID' => '1'], ['ID' => '2'], ['ID' => '3']]);

        ob_start(); // sort() echoes the number of sorted items for the AJAX caller
        Episciences_VolumesAndSectionsManager::sort(['sorted' => [$prefix . '99', $prefix . '1']], $colId);
        ob_end_clean();

        // Two explicit updates (the unknown id 99 is still posted) and the two items left over
        self::assertCount(4, $this->db->updates());
        foreach ($this->db->updates() as $update) {
            self::assertSame($table, $update['table']);
            self::assertSame(RVID, $update['where']['RVID = ?'] ?? null, 'every update must carry the journal id');
        }
    }

    // ------------------------------------------------------------------
    // Controllers: an item of another journal is neither found nor deleted
    // ------------------------------------------------------------------

    /**
     * Run $action with $post, along with the valid request token unless $withToken is false: the
     * scope checks come after the token one.
     *
     * @param class-string<\Zend_Controller_Action> $class
     * @param array<string, mixed> $post
     */
    private function dispatchAction(string $class, string $action, array $post, bool $withToken = true): DispatchResult
    {
        return $this->dispatch($class, $action, $this->postRequest($post, $withToken));
    }

    private function assertNothingWritten(): void
    {
        self::assertSame([], $this->db->inserts());
        self::assertSame([], $this->db->updates());
        self::assertSame([], $this->db->deletes());
    }

    public function testVolumeOfAnotherJournalIsNotDeleted(): void
    {
        $output = $this->dispatchAction(\VolumeController::class, 'delete', ['params' => ['id' => '7']])->output;

        self::assertSame('', $output, 'a refused deletion answers false');
        $this->assertNothingWritten();
        self::assertCount(1, $this->db->selects(), 'only the scoped lookup may reach the database');
        self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $this->db->selects()[0]);
    }

    public function testSectionOfAnotherJournalIsNotDeleted(): void
    {
        $output = $this->dispatchAction(
            \SectionController::class,
            'delete',
            ['ajax' => '1', 'params' => ['id' => '7']]
        )->output;

        self::assertSame('', $output);
        $this->assertNothingWritten();
        self::assertCount(1, $this->db->selects());
        self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $this->db->selects()[0]);
    }

    /**
     * @return array<string, array{class-string<\Zend_Controller_Action>, string, string}>
     */
    public static function editorsActionsProvider(): array
    {
        return [
            'volume editors form' => [\VolumeController::class, 'editorsform', 'vid'],
            'volume display editors' => [\VolumeController::class, 'displayeditors', 'vid'],
            'volume save editors' => [\VolumeController::class, 'saveeditors', 'vid'],
            'section editors form' => [\SectionController::class, 'editorsform', 'sid'],
            'section display editors' => [\SectionController::class, 'displayeditors', 'sid'],
            'section save editors' => [\SectionController::class, 'saveeditors', 'sid'],
        ];
    }

    /**
     * An item of another journal must give an empty answer and no write, never its editors.
     *
     * @param class-string<\Zend_Controller_Action> $class
     * @dataProvider editorsActionsProvider
     */
    public function testEditorsActionsRefuseAnItemOfAnotherJournal(string $class, string $action, string $key): void
    {
        $output = $this->dispatchAction($class, $action, [$key => '7', 'editors' => ['1']])->output;

        self::assertSame('', $output);
        $this->assertNothingWritten();
        self::assertNotEmpty($this->db->selects());
        foreach ($this->db->selects() as $sql) {
            self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $sql);
        }
    }

    /**
     * @return array<string, array{class-string<\Zend_Controller_Action>, string, array<string, mixed>, string}>
     */
    public static function writeActionsProvider(): array
    {
        // The delete buttons show the answer as the reason of the failure
        $expired = 'Votre session a expiré, veuillez réessayer.';

        return [
            'volume delete' => [\VolumeController::class, 'delete', ['params' => ['id' => '7']], $expired],
            'section delete' => [\SectionController::class, 'delete', ['ajax' => '1', 'params' => ['id' => '7']], $expired],
            'volume save editors' => [\VolumeController::class, 'saveeditors', ['vid' => '7', 'editors' => ['1']], ''],
            'section save editors' => [\SectionController::class, 'saveeditors', ['sid' => '7', 'editors' => ['1']], ''],
        ];
    }

    /**
     * Without the form token nothing is looked up, written or deleted.
     *
     * @param class-string<\Zend_Controller_Action> $class
     * @param array<string, mixed> $post
     * @dataProvider writeActionsProvider
     */
    public function testWriteActionsRefuseARequestWithoutToken(string $class, string $action, array $post, string $expectedOutput): void
    {
        $this->db->respondTo('/./', [['VID' => 7, 'SID' => 7, 'RVID' => RVID]]);

        $output = $this->dispatchAction($class, $action, $post, false)->output;

        self::assertSame($expectedOutput, $output);
        self::assertSame([], $this->db->queries());
        $this->assertNothingWritten();
    }

    /**
     * @return array<string, array{class-string<\Zend_Controller_Action>}>
     */
    public static function sortActionsProvider(): array
    {
        return [
            'volumes' => [\VolumeController::class],
            'sections' => [\SectionController::class],
        ];
    }

    /**
     * A re-ordering without the form token is refused with a 403, nothing is updated.
     *
     * @param class-string<\Zend_Controller_Action> $class
     * @dataProvider sortActionsProvider
     */
    public function testSortActionsRefuseARequestWithoutToken(string $class): void
    {
        $result = $this->dispatch($class, 'sort', $this->xhrPostRequest(['sorted' => ['item_1', 'item_2']]));

        self::assertSame('', $result->output);
        self::assertSame(403, $result->statusCode());
        self::assertSame([], $this->db->queries());
        self::assertSame([], $this->db->updates());
    }
}
