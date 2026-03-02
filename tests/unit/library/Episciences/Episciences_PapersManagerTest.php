<?php

namespace unit\library\Episciences;

use Episciences_Paper;
use Episciences_PapersManager;
use Episciences_User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_PapersManager static utility methods.
 *
 * Only methods that do not require a database connection are covered here.
 *
 * @covers Episciences_PapersManager
 */
final class Episciences_PapersManagerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makePaper(int $status, int $vid = 0): Episciences_Paper
    {
        $paper = new Episciences_Paper();
        $paper->setStatus($status);
        $paper->setVid($vid);
        return $paper;
    }

    // -----------------------------------------------------------------------
    // sortBy()
    // -----------------------------------------------------------------------

    public function testSortByReturnsFalseForEmptyList(): void
    {
        self::assertFalse(Episciences_PapersManager::sortBy([], 'vid'));
    }

    public function testSortByGroupsByKey(): void
    {
        $p1 = $this->makePaper(Episciences_Paper::STATUS_SUBMITTED, 10);
        $p2 = $this->makePaper(Episciences_Paper::STATUS_ACCEPTED, 20);

        $result = Episciences_PapersManager::sortBy(['a' => $p1, 'b' => $p2], 'vid');

        self::assertIsArray($result);
        self::assertArrayHasKey(10, $result);
        self::assertArrayHasKey(20, $result);
        self::assertSame($p1, $result[10]['a']);
        self::assertSame($p2, $result[20]['b']);
    }

    public function testSortByGroupsMultipleItemsUnderSameKey(): void
    {
        $p1 = $this->makePaper(Episciences_Paper::STATUS_SUBMITTED, 5);
        $p2 = $this->makePaper(Episciences_Paper::STATUS_ACCEPTED, 5);

        $result = Episciences_PapersManager::sortBy(['a' => $p1, 'b' => $p2], 'vid');

        self::assertIsArray($result);
        self::assertArrayHasKey(5, $result);
        self::assertCount(2, $result[5]);
    }

    public function testSortByWithUnknownKeyGroupsUnderZero(): void
    {
        // If the method 'getunknown' does not exist on Episciences_Paper,
        // $itemKey stays at the default 0 and the item is grouped under 0.
        $p1 = $this->makePaper(Episciences_Paper::STATUS_SUBMITTED);

        $result = Episciences_PapersManager::sortBy(['x' => $p1], 'unknownkey');

        self::assertIsArray($result);
        self::assertArrayHasKey(0, $result);
        self::assertSame($p1, $result[0]['x']);
    }

    public function testSortByDoesNotErrorOnFirstIteration(): void
    {
        // Regression for Fix 4: $result was used before initialisation on the
        // very first iteration of the foreach loop.
        $p1 = $this->makePaper(Episciences_Paper::STATUS_SUBMITTED, 1);

        // Must not emit any PHP notice/warning about undefined variable
        $result = Episciences_PapersManager::sortBy(['a' => $p1], 'vid');
        self::assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // sortByStatus()
    // -----------------------------------------------------------------------

    public function testSortByStatusReturnsEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], Episciences_PapersManager::sortByStatus([]));
    }

    /**
     * @dataProvider sortByStatusMappingProvider
     */
    public function testSortByStatusMapsStatusCorrectly(int $status, int $expectedKey): void
    {
        $paper = $this->makePaper($status);
        $result = Episciences_PapersManager::sortByStatus(['item' => $paper]);

        self::assertArrayHasKey(
            $expectedKey,
            $result,
            "Expected status $status to be grouped under key $expectedKey"
        );
        self::assertSame($paper, $result[$expectedKey]['item']);
    }

    public static function sortByStatusMappingProvider(): array
    {
        return [
            'submitted maps to STATUS_SUBMITTED'     => [Episciences_Paper::STATUS_SUBMITTED, Episciences_Paper::STATUS_SUBMITTED],
            'ok for reviewing maps to SUBMITTED'     => [Episciences_Paper::STATUS_OK_FOR_REVIEWING, Episciences_Paper::STATUS_SUBMITTED],
            'being reviewed maps to SUBMITTED'       => [Episciences_Paper::STATUS_BEING_REVIEWED, Episciences_Paper::STATUS_SUBMITTED],
            'reviewed maps to SUBMITTED'             => [Episciences_Paper::STATUS_REVIEWED, Episciences_Paper::STATUS_SUBMITTED],
            'published maps to itself'               => [Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper::STATUS_PUBLISHED],
            'accepted maps to itself'                => [Episciences_Paper::STATUS_ACCEPTED, Episciences_Paper::STATUS_ACCEPTED],
            'refused maps to itself'                 => [Episciences_Paper::STATUS_REFUSED, Episciences_Paper::STATUS_REFUSED],
        ];
    }

    // -----------------------------------------------------------------------
    // countByStatus()
    // -----------------------------------------------------------------------

    public function testCountByStatusReturnsZeroForEmptyList(): void
    {
        self::assertSame(0, Episciences_PapersManager::countByStatus([], Episciences_Paper::STATUS_SUBMITTED));
    }

    public function testCountByStatusReturnsZeroForNonArrayList(): void
    {
        self::assertSame(0, Episciences_PapersManager::countByStatus(null, Episciences_Paper::STATUS_SUBMITTED));
    }

    public function testCountByStatusCountsSingleStatus(): void
    {
        $list = [
            $this->makePaper(Episciences_Paper::STATUS_SUBMITTED),
            $this->makePaper(Episciences_Paper::STATUS_SUBMITTED),
            $this->makePaper(Episciences_Paper::STATUS_ACCEPTED),
        ];

        self::assertSame(2, Episciences_PapersManager::countByStatus($list, Episciences_Paper::STATUS_SUBMITTED));
    }

    public function testCountByStatusCountsStatusArray(): void
    {
        $list = [
            $this->makePaper(Episciences_Paper::STATUS_SUBMITTED),
            $this->makePaper(Episciences_Paper::STATUS_OK_FOR_REVIEWING),
            $this->makePaper(Episciences_Paper::STATUS_ACCEPTED),
        ];

        $count = Episciences_PapersManager::countByStatus(
            $list,
            [Episciences_Paper::STATUS_SUBMITTED, Episciences_Paper::STATUS_OK_FOR_REVIEWING]
        );

        self::assertSame(2, $count);
    }

    public function testCountByStatusReturnsZeroWhenNoMatch(): void
    {
        $list = [
            $this->makePaper(Episciences_Paper::STATUS_SUBMITTED),
            $this->makePaper(Episciences_Paper::STATUS_ACCEPTED),
        ];

        self::assertSame(0, Episciences_PapersManager::countByStatus($list, Episciences_Paper::STATUS_PUBLISHED));
    }

    // -----------------------------------------------------------------------
    // buildDocumentPath()
    // -----------------------------------------------------------------------

    public function testBuildDocumentPathIsString(): void
    {
        $path = Episciences_PapersManager::buildDocumentPath(42);
        self::assertIsString($path);
    }

    public function testBuildDocumentPathContainsDocId(): void
    {
        $path = Episciences_PapersManager::buildDocumentPath(99);
        self::assertStringEndsWith('99', $path);
    }

    public function testBuildDocumentPathDiffersForDifferentIds(): void
    {
        $path1 = Episciences_PapersManager::buildDocumentPath(10);
        $path2 = Episciences_PapersManager::buildDocumentPath(20);
        self::assertNotSame($path1, $path2);
    }

    // -----------------------------------------------------------------------
    // getCoAuthorsMails()
    // -----------------------------------------------------------------------

    public function testGetCoAuthorsMailsReturnsEmptyStringForEmptyArray(): void
    {
        self::assertSame('', Episciences_PapersManager::getCoAuthorsMails([]));
    }

    public function testGetCoAuthorsMailsFormatsSingleEmail(): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getEmail')->willReturn('alice@example.com');

        $result = Episciences_PapersManager::getCoAuthorsMails([$user]);

        self::assertStringContainsString('alice@example.com', $result);
        self::assertStringContainsString('<', $result);
        self::assertStringContainsString('>', $result);
    }

    public function testGetCoAuthorsMailsAlwaysEndsWithSemicolon(): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getEmail')->willReturn('bob@example.com');

        $result = Episciences_PapersManager::getCoAuthorsMails([$user]);

        self::assertStringEndsWith(';', $result);
    }

    public function testGetCoAuthorsMailsFormatsMultipleEmails(): void
    {
        $alice = $this->createMock(Episciences_User::class);
        $alice->method('getEmail')->willReturn('alice@example.com');

        $bob = $this->createMock(Episciences_User::class);
        $bob->method('getEmail')->willReturn('bob@example.com');

        $result = Episciences_PapersManager::getCoAuthorsMails([$alice, $bob]);

        self::assertStringContainsString('alice@example.com', $result);
        self::assertStringContainsString('bob@example.com', $result);
    }

    // -----------------------------------------------------------------------
    // getStatusLabel()
    // -----------------------------------------------------------------------

    /**
     * @dataProvider statusLabelProvider
     */
    public function testGetStatusLabelReturnsKnownLabel(int $status, string $expectedLabel): void
    {
        self::assertSame($expectedLabel, Episciences_PapersManager::getStatusLabel($status));
    }

    public static function statusLabelProvider(): array
    {
        return [
            'submitted'  => [Episciences_Paper::STATUS_SUBMITTED, 'soumis'],
            'accepted'   => [Episciences_Paper::STATUS_ACCEPTED, 'accepté'],
            'published'  => [Episciences_Paper::STATUS_PUBLISHED, 'publié'],
            'refused'    => [Episciences_Paper::STATUS_REFUSED, 'refusé'],
        ];
    }

    public function testGetStatusLabelReturnsStatusCodeWhenUnknown(): void
    {
        // Unknown status → the input $status is returned as-is
        $unknown = 9999;
        self::assertSame($unknown, Episciences_PapersManager::getStatusLabel($unknown));
    }

    // -----------------------------------------------------------------------
    // getByDocIds()
    // -----------------------------------------------------------------------

    /**
     * An empty input array must return an empty map immediately, without
     * touching the database.
     */
    public function testGetByDocIdsReturnsEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], Episciences_PapersManager::getByDocIds([]));
    }

    /**
     * When none of the requested docIds exist in the DB, the result must be
     * an empty map (not false, not null).
     *
     * Uses docId 0 which is never a valid paper identifier.
     */
    public function testGetByDocIdsReturnsEmptyArrayWhenNoDocumentFound(): void
    {
        $result = Episciences_PapersManager::getByDocIds([0]);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    /**
     * The returned map must be keyed by integer docId.
     * We load a paper that is known to exist in the test DB (any published paper)
     * and verify the key type and the object type.
     *
     * If no published paper is available the test is skipped automatically.
     */
    public function testGetByDocIdsReturnsMapKeyedByIntDocId(): void
    {
        // Load one existing published paper via the standard API to get a real docId.
        $existing = Episciences_PapersManager::getList([
            'is'    => ['STATUS' => [Episciences_Paper::STATUS_PUBLISHED]],
            'limit' => 1,
        ]);

        if (empty($existing)) {
            self::markTestSkipped('No published paper available in test DB.');
        }

        /** @var Episciences_Paper $reference */
        $reference = reset($existing);
        $docId     = (int) $reference->getDocid();

        $result = Episciences_PapersManager::getByDocIds([$docId]);

        self::assertArrayHasKey($docId, $result, 'Result must be keyed by integer docId');
        self::assertInstanceOf(Episciences_Paper::class, $result[$docId]);
        self::assertSame($docId, (int) $result[$docId]->getDocid());
    }

    /**
     * When multiple docIds are requested, all found papers must be present in
     * the returned map; docIds that do not exist are silently omitted.
     */
    public function testGetByDocIdsReturnsManyPapersInSingleCall(): void
    {
        $existing = Episciences_PapersManager::getList([
            'is'    => ['STATUS' => [Episciences_Paper::STATUS_PUBLISHED]],
            'limit' => 3,
        ]);

        if (count($existing) < 2) {
            self::markTestSkipped('Need at least 2 published papers in test DB.');
        }

        $docIds = array_map(static fn(Episciences_Paper $p) => (int) $p->getDocid(), $existing);

        // Add a non-existent docId to verify it is silently omitted.
        $docIds[] = 0;

        $result = Episciences_PapersManager::getByDocIds($docIds);

        self::assertGreaterThanOrEqual(2, count($result));
        self::assertArrayNotHasKey(0, $result, 'Non-existent docId must be omitted from the map');

        foreach ($result as $key => $paper) {
            self::assertIsInt($key);
            self::assertInstanceOf(Episciences_Paper::class, $paper);
        }
    }

    /**
     * getByDocIds() must not include revision deadline or conflict data since
     * those are editorial-workflow artefacts not needed for metadata export.
     *
     * We verify that the returned Paper objects expose a null/empty conflicts
     * collection (getConflicts() returns [] by default, not a DB-populated set).
     */
    public function testGetByDocIdsOmitsWorkflowData(): void
    {
        $existing = Episciences_PapersManager::getList([
            'is'    => ['STATUS' => [Episciences_Paper::STATUS_PUBLISHED]],
            'limit' => 1,
        ]);

        if (empty($existing)) {
            self::markTestSkipped('No published paper available in test DB.');
        }

        /** @var Episciences_Paper $reference */
        $reference = reset($existing);
        $result    = Episciences_PapersManager::getByDocIds([(int) $reference->getDocid()]);
        $paper     = reset($result);

        // Conflicts default to an empty array when not explicitly loaded.
        self::assertIsArray($paper->getConflicts());
        self::assertEmpty($paper->getConflicts(), 'getByDocIds() must not load conflict data');
    }
}
