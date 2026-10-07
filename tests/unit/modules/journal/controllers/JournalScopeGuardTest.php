<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_SectionsManager;
use Episciences_VolumesAndSectionsManager;
use Episciences_VolumesManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * Journal scoping of volumes and sections.
 *
 * Volume and section ids come from request parameters, and several journals share the same
 * tables: a lookup, a deletion or a re-ordering must never reach an item of another journal.
 * The database adapter is a partial mock: queries are built for real and captured, nothing is
 * executed.
 */
final class JournalScopeGuardTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> SQL of the SELECT statements sent to the adapter */
    private array $selects = [];

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchCol', 'update', 'delete', 'insert'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        require_once APPLICATION_PATH . '/modules/journal/controllers/VolumeController.php';
        require_once APPLICATION_PATH . '/modules/journal/controllers/SectionController.php';
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private function captureSelects(string $method, mixed $result): void
    {
        $this->adapter->method($method)->willReturnCallback(function ($select) use ($result) {
            $this->selects[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return $result;
        });
    }

    // ------------------------------------------------------------------
    // Library: lookups and sort
    // ------------------------------------------------------------------

    public function testVolumeLookupIsRestrictedToTheJournal(): void
    {
        $this->captureSelects('fetchRow', false);

        self::assertFalse(Episciences_VolumesManager::find(7, 3));

        self::assertCount(1, $this->selects);
        self::assertStringContainsString('VID = 7', $this->selects[0]);
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $this->selects[0]);
    }

    public function testSectionLookupIsRestrictedToTheJournal(): void
    {
        $this->captureSelects('fetchRow', false);

        self::assertFalse(Episciences_SectionsManager::find(7, 3));

        self::assertCount(1, $this->selects);
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $this->selects[0]);
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
        $this->adapter->method('fetchCol')->willReturn(['1', '2', '3']);
        $updates = [];
        $this->adapter->method('update')->willReturnCallback(
            static function ($t, $data, $where) use (&$updates) {
                $updates[] = [$t, $data, $where];

                return 1;
            }
        );

        ob_start(); // sort() echoes the number of sorted items for the AJAX caller
        Episciences_VolumesAndSectionsManager::sort(['sorted' => [$prefix . '99', $prefix . '1']], $colId);
        ob_end_clean();

        // Two explicit updates (the unknown id 99 is still posted) and the two items left over
        self::assertCount(4, $updates);
        foreach ($updates as [$t, , $where]) {
            self::assertSame($table, $t);
            self::assertSame(RVID, $where['RVID = ?'] ?? null, 'every update must carry the journal id');
        }
    }

    // ------------------------------------------------------------------
    // Controllers: an item of another journal is neither found nor deleted
    // ------------------------------------------------------------------

    /**
     * @param class-string $class
     * @param array<string, mixed> $post
     */
    private function dispatchAction(string $class, string $action, array $post): string
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $response = new Zend_Controller_Response_HttpTestCase();

        $controller = new $class($request, $response);

        ob_start();
        try {
            $controller->$action();
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    public function testVolumeOfAnotherJournalIsNotDeleted(): void
    {
        $this->captureSelects('fetchRow', false);
        $this->adapter->expects(self::never())->method('delete');
        $this->adapter->expects(self::never())->method('update');

        $output = $this->dispatchAction(\VolumeController::class, 'deleteAction', ['params' => ['id' => '7']]);

        self::assertSame('', $output, 'a refused deletion answers false');
        self::assertCount(1, $this->selects, 'only the scoped lookup may reach the database');
        self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $this->selects[0]);
    }

    public function testSectionOfAnotherJournalIsNotDeleted(): void
    {
        $this->captureSelects('fetchRow', false);
        $this->adapter->expects(self::never())->method('delete');
        $this->adapter->expects(self::never())->method('update');

        $output = $this->dispatchAction(
            \SectionController::class,
            'deleteAction',
            ['ajax' => '1', 'params' => ['id' => '7']]
        );

        self::assertSame('', $output);
        self::assertCount(1, $this->selects);
        self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $this->selects[0]);
    }

    /**
     * @return array<string, array{class-string, string, string}>
     */
    public static function editorsActionsProvider(): array
    {
        return [
            'volume editors form' => [\VolumeController::class, 'editorsformAction', 'vid'],
            'volume display editors' => [\VolumeController::class, 'displayeditorsAction', 'vid'],
            'volume save editors' => [\VolumeController::class, 'saveeditorsAction', 'vid'],
            'section editors form' => [\SectionController::class, 'editorsformAction', 'sid'],
            'section display editors' => [\SectionController::class, 'displayeditorsAction', 'sid'],
            'section save editors' => [\SectionController::class, 'saveeditorsAction', 'sid'],
        ];
    }

    /**
     * An item of another journal must give an empty answer and no write, never its editors.
     *
     * @param class-string $class
     * @dataProvider editorsActionsProvider
     */
    public function testEditorsActionsRefuseAnItemOfAnotherJournal(string $class, string $action, string $key): void
    {
        $this->captureSelects('fetchRow', false);
        $this->adapter->expects(self::never())->method('delete');
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');

        $output = $this->dispatchAction($class, $action, [$key => '7', 'editors' => ['1']]);

        self::assertSame('', $output);
        self::assertNotEmpty($this->selects);
        foreach ($this->selects as $sql) {
            self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $sql);
        }
    }
}
