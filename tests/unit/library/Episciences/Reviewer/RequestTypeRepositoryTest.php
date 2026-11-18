<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\DefaultRequestType;
use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use LogicException;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Pdo_Sqlite;

require_once __DIR__ . '/RequestTypeTestDatabase.php';

/**
 * Review request types, run against an in-memory SQLite copy of the tables
 *
 * @covers \Episciences\Reviewer\RequestTypeRepository
 */
final class RequestTypeRepositoryTest extends TestCase
{
    private const RVID = 3;
    private const OTHER_RVID = 4;

    private Zend_Db_Adapter_Pdo_Sqlite $db;
    private RequestTypeRepository $repository;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $this->db = RequestTypeTestDatabase::create();
        $this->repository = new RequestTypeRepository($this->db);
    }

    private function addAssignment(int $requestTypeId): void
    {
        $this->db->insert('USER_ASSIGNMENT', [
            'RVID' => self::RVID, 'ITEMID' => 10, 'UID' => 20, 'ROLEID' => 'reviewer',
            'REQUEST_TYPE_ID' => $requestTypeId, 'STATUS' => 'pending',
        ]);
    }

    public function testCreateDefaultsIsIdempotent(): void
    {
        self::assertSame(2, $this->repository->createDefaults(self::RVID));
        self::assertSame(0, $this->repository->createDefaults(self::RVID));
        self::assertSame(2, $this->repository->createDefaults(self::OTHER_RVID));

        self::assertCount(2, $this->repository->findAllForJournal(self::RVID));
        self::assertNotNull($this->repository->findFullReport(self::RVID));
    }

    public function testCreateDefaultsOnlyAddsTheMissingOnes(): void
    {
        $this->repository->save(RequestType::defaults(self::RVID)[1]);

        self::assertSame(1, $this->repository->createDefaults(self::RVID));
    }

    public function testSaveInsertsThenUpdatesWithoutChangingTheCode(): void
    {
        $saved = $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Phase one'], false, null, 5));
        self::assertNotNull($saved->getId());

        $this->repository->save($saved->withSettings(['en' => 'Phase 1', 'fr' => 'Phase 1'], '2 week', 2, false));

        $reloaded = $this->repository->findById((int)$saved->getId());
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getDefaultType());
        self::assertSame(['en' => 'Phase 1', 'fr' => 'Phase 1'], $reloaded->getLabels());
        self::assertSame('2 week', $reloaded->getDefaultDeadline());
        self::assertSame(2, $reloaded->getPosition());
        self::assertFalse($reloaded->isEnabled());
    }

    public function testAJournalHasOneOfEachDefaultTypeButAnyNumberOfItsOwnTypes(): void
    {
        $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Phase one']));
        $again = $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Phase one']));
        self::assertNotNull($again->getId());

        $this->repository->save(new RequestType(null, self::OTHER_RVID, DefaultRequestType::QuickOpinion, ['en' => 'Quick']));
        $this->repository->save(new RequestType(null, self::RVID, DefaultRequestType::QuickOpinion, ['en' => 'Quick']));
        self::assertSame(DefaultRequestType::QuickOpinion, $this->repository->findDefault(self::RVID, DefaultRequestType::QuickOpinion)?->getDefaultType());

        $this->expectException(LogicException::class);
        $this->repository->save(new RequestType(null, self::RVID, DefaultRequestType::QuickOpinion, ['en' => 'Quick again']));
    }

    public function testUpdatingATypeOfAnotherJournalIsRefused(): void
    {
        $saved = $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Phase one']));
        $forged = new RequestType($saved->getId(), self::OTHER_RVID, null, ['en' => 'Hijacked']);

        try {
            $this->repository->save($forged);
            self::fail('A type of another journal must not be updated');
        } catch (LogicException) {
            self::assertSame('Phase one', $this->repository->findById((int)$saved->getId())?->getLabel('en'));
        }
    }

    public function testFindAllForJournalIsOrderedAndCanKeepOnlyEnabledTypes(): void
    {
        $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Third'], false, null, 3));
        $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'First'], false, null, 1));
        $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Disabled'], false, null, 2, false));
        $this->repository->save(new RequestType(null, self::OTHER_RVID, null, ['en' => 'Elsewhere']));

        $labels = static fn(array $types): array => array_map(static fn(RequestType $type): string => $type->getLabel('en'), $types);

        self::assertSame(['First', 'Disabled', 'Third'], $labels($this->repository->findAllForJournal(self::RVID)));
        self::assertSame(['First', 'Third'], $labels($this->repository->findAllForJournal(self::RVID, true)));
    }

    public function testFindUsableRequiresTheSameJournalAndAnEnabledType(): void
    {
        $enabled = $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Enabled']));
        $disabled = $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Disabled'], false, null, 0, false));

        self::assertNotNull($this->repository->findUsable((int)$enabled->getId(), self::RVID));
        self::assertNull($this->repository->findUsable((int)$enabled->getId(), self::OTHER_RVID));
        self::assertNull($this->repository->findUsable((int)$disabled->getId(), self::RVID));
        self::assertNull($this->repository->findUsable(999, self::RVID));
    }

    public function testOnlyAnUnusedTypeOfTheJournalCanBeDeleted(): void
    {
        $this->repository->createDefaults(self::RVID);
        $fullReport = $this->repository->findFullReport(self::RVID);
        $used = $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Used']));
        $unused = $this->repository->save(new RequestType(null, self::RVID, null, ['en' => 'Unused']));
        $this->addAssignment((int)$used->getId());

        self::assertTrue($this->repository->isUsed((int)$used->getId()));
        self::assertFalse($this->repository->isUsed((int)$unused->getId()));

        self::assertFalse($this->repository->delete((int)$fullReport?->getId(), self::RVID));
        // the default types stay available: never used, they still cannot be deleted
        self::assertFalse($this->repository->delete((int)$this->repository->findDefault(self::RVID, DefaultRequestType::QuickOpinion)?->getId(), self::RVID));
        self::assertFalse($this->repository->delete((int)$used->getId(), self::RVID));
        self::assertFalse($this->repository->delete((int)$unused->getId(), self::OTHER_RVID));
        self::assertTrue($this->repository->delete((int)$unused->getId(), self::RVID));

        self::assertNull($this->repository->findById((int)$unused->getId()));
        self::assertNotNull($this->repository->findById((int)$used->getId()));
    }
}
