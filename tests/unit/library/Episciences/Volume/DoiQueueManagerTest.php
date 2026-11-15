<?php

declare(strict_types=1);

namespace unit\library\Episciences\Volume;

use Episciences_Volume;
use Episciences_Volume_DoiQueue;
use Episciences\Volume\DoiQueueManager;
use PHPUnit\Framework\TestCase;
use unit\modules\support\RecordingDbAdapter;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * DOI queue of the proceedings volumes (doi_queue_volumes).
 *
 * @covers \Episciences\Volume\DoiQueueManager
 */
final class DoiQueueManagerTest extends TestCase
{
    private const RVID = 42;

    private RecordingDbAdapter $db;

    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->db = new RecordingDbAdapter();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testUpdateStoresTheRawStatus(): void
    {
        $doiQueue = new Episciences_Volume_DoiQueue(['id' => 7, 'vid' => 1134, 'doi_status' => Episciences_Volume_DoiQueue::STATUS_REQUESTED]);

        self::assertSame(1, DoiQueueManager::update($doiQueue));

        $updates = $this->db->updates();
        self::assertCount(1, $updates);
        self::assertSame(T_DOI_QUEUE_VOLUMES, $updates[0]['table']);
        self::assertSame('requested', $updates[0]['values']['doi_status'], 'The ENUM refuses a quoted value');
        self::assertSame(['id = ?' => 7], $updates[0]['where']);
    }

    public function testFindByStatusFiltersByJournalAndStatus(): void
    {
        $this->db
            ->respondTo('/FROM `doi_queue_volumes`/', [
                ['id' => '3', 'vid' => '1134', 'doi_status' => 'assigned', 'date_init' => '2026-11-01 10:00:00', 'date_updated' => '2026-11-01 10:00:00'],
            ])
            ->respondTo('/FROM `VOLUME` WHERE \(VID = 1134\)/', [
                ['VID' => '1134', 'RVID' => (string)self::RVID, 'POSITION' => '1', 'titles' => '{"en":"Proceedings"}'],
            ])
            ->respondTo('/FROM `VOLUME_SETTING`/', [
                ['SETTING' => Episciences_Volume::VOLUME_IS_PROCEEDING, 'VALUE' => '1'],
            ]);

        $result = DoiQueueManager::findByStatus(self::RVID, Episciences_Volume_DoiQueue::STATUS_ASSIGNED);

        $queueQuery = $this->db->selects()[0];
        self::assertStringContainsString('INNER JOIN `VOLUME` AS `V` ON V.VID = DQ.vid', $queueQuery);
        self::assertStringContainsString('(V.RVID = 42)', $queueQuery);
        self::assertStringContainsString("(DQ.doi_status = 'assigned')", $queueQuery);
        self::assertStringContainsString('(VID = 1134) AND (RVID = 42)', $this->db->selects()[1], 'The volume is looked up in the same journal');

        self::assertCount(1, $result);
        self::assertInstanceOf(Episciences_Volume::class, $result[0]['volume']);
        self::assertSame(1134, (int)$result[0]['volume']->getVid());
        self::assertSame(1, $result[0]['volume']->isProceeding());
        self::assertSame(3, $result[0]['doiq']->getId());
        self::assertSame(Episciences_Volume_DoiQueue::STATUS_ASSIGNED, $result[0]['doiq']->getDoi_status());
    }

    public function testFindByStatusSkipsAVolumeThatNoLongerExists(): void
    {
        $this->db->respondTo('/FROM `doi_queue_volumes`/', [
            ['id' => '3', 'vid' => '999', 'doi_status' => 'requested', 'date_init' => '2026-11-01 10:00:00', 'date_updated' => '2026-11-01 10:00:00'],
        ]);

        self::assertSame([], DoiQueueManager::findByStatus(self::RVID, Episciences_Volume_DoiQueue::STATUS_REQUESTED));
    }

    public function testAddNeverChangesARequestedOrRegisteredStatus(): void
    {
        DoiQueueManager::add(new Episciences_Volume_DoiQueue(['vid' => 1134, 'doi_status' => Episciences_Volume_DoiQueue::STATUS_ASSIGNED]));

        $sql = $this->db->queries()[0]['sql'];
        self::assertStringStartsWith('INSERT INTO `doi_queue_volumes`', $sql);
        self::assertStringContainsString("VALUES (1134, 'assigned', NOW(), NOW()) AS new_row", $sql);
        self::assertStringContainsString(
            "IF(`doi_queue_volumes`.`doi_status` IN ('requested', 'public'), `doi_queue_volumes`.`doi_status`, new_row.`doi_status`)",
            $sql
        );
    }

    /**
     * @dataProvider lockedStatusProvider
     */
    public function testFindByVidAndLockedStatus(?string $status, bool $locked): void
    {
        if ($status !== null) {
            $this->db->respondTo('/FROM `doi_queue_volumes` WHERE \(vid = 1134\)/', [['id' => '1', 'vid' => '1134', 'doi_status' => $status]]);
        }

        self::assertSame($status ?? Episciences_Volume_DoiQueue::STATUS_NOT_ASSIGNED, DoiQueueManager::findByVid(1134)->getDoi_status());
        self::assertSame($locked, DoiQueueManager::isDoiLocked(1134));
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function lockedStatusProvider(): array
    {
        return [
            'no entry' => [null, false],
            'assigned' => [Episciences_Volume_DoiQueue::STATUS_ASSIGNED, false],
            'requested' => [Episciences_Volume_DoiQueue::STATUS_REQUESTED, true],
            'public' => [Episciences_Volume_DoiQueue::STATUS_PUBLIC, true],
        ];
    }

    public function testRemoveAssignedOnlyDeletesAnAssignedEntry(): void
    {
        DoiQueueManager::removeAssigned(1134);

        self::assertSame(
            [['table' => T_DOI_QUEUE_VOLUMES, 'where' => ['vid = ?' => 1134, 'doi_status = ?' => Episciences_Volume_DoiQueue::STATUS_ASSIGNED]]],
            $this->db->deletes()
        );
    }
}
