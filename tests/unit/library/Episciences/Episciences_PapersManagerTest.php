<?php

namespace unit\library\Episciences;

use Episciences_CommentsManager;
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
     * getByDocIds() must not eagerly load revision deadline or conflict data since
     * those are editorial-workflow artefacts not needed for metadata export.
     *
     * We read the private _conflicts property directly instead of calling
     * getConflicts(), which lazily loads conflicts from the DB on first access and
     * would therefore reflect the test DB content rather than what getByDocIds built.
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

        self::assertInstanceOf(Episciences_Paper::class, $paper);

        // Read the raw property without triggering the lazy DB load in getConflicts().
        $conflictsProperty = new \ReflectionProperty(Episciences_Paper::class, '_conflicts');
        $conflictsProperty->setAccessible(true);

        self::assertSame(
            [],
            $conflictsProperty->getValue($paper),
            'getByDocIds() must not eagerly populate conflict data'
        );
    }

    // -----------------------------------------------------------------------
    // Decision suggestion filter helpers (git #1011)
    // -----------------------------------------------------------------------

    /**
     * @param array<int, mixed> $args
     * @return mixed
     */
    private function invokePrivateStatic(string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke(null, ...$args);
    }

    public function testGetFinalizedStatusForSuggestionsListsTerminalStatuses(): void
    {
        $result = $this->invokePrivateStatic('getFinalizedStatusForSuggestions');

        self::assertIsArray($result);
        self::assertContains(Episciences_Paper::STATUS_PUBLISHED, $result);
        self::assertContains(Episciences_Paper::STATUS_REFUSED, $result);
        self::assertContains(Episciences_Paper::STATUS_DELETED, $result);
        self::assertContains(Episciences_Paper::STATUS_REMOVED, $result);
        self::assertContains(Episciences_Paper::STATUS_OBSOLETE, $result);
        self::assertContains(Episciences_Paper::STATUS_ABANDONED, $result);

        // A paper still under review must not be considered "finalized".
        self::assertNotContains(Episciences_Paper::STATUS_SUBMITTED, $result);
        self::assertNotContains(Episciences_Paper::STATUS_REVIEWED, $result);
    }

    public function testGetPostAcceptanceStatusesIncludesAcceptedSubmissionsAndValidationSteps(): void
    {
        $result = $this->invokePrivateStatic('getPostAcceptanceStatuses');

        foreach (Episciences_Paper::ACCEPTED_SUBMISSIONS as $status) {
            self::assertContains($status, $result);
        }
        self::assertContains(Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION, $result);
        self::assertContains(Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION, $result);

        // A paper still awaiting an editorial decision has not been accepted yet.
        self::assertNotContains(Episciences_Paper::STATUS_REVIEWED, $result);
    }

    /**
     * @param int[] $mustContain
     * @param int[] $mustNotContain
     * @dataProvider actedUponSuggestionTypeProvider
     */
    public function testGetActedUponStatusForSuggestionType(int $type, array $mustContain, array $mustNotContain): void
    {
        $result = $this->invokePrivateStatic('getActedUponStatusForSuggestionType', [$type]);

        self::assertIsArray($result);
        foreach ($mustContain as $status) {
            self::assertContains($status, $result, "Status $status must be considered 'acted upon' for suggestion type $type");
        }
        foreach ($mustNotContain as $status) {
            self::assertNotContains($status, $result, "Status $status must NOT be considered 'acted upon' for suggestion type $type");
        }
    }

    /**
     * @return array<string, array{int, int[], int[]}>
     */
    public static function actedUponSuggestionTypeProvider(): array
    {
        // Any editorial decision settles a suggestion, including a revision request that
        // contradicts it: the editor in chief has ruled, the suggestion is no longer pending.
        $settledByDecision = [
            Episciences_Paper::STATUS_ACCEPTED,
            Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION,
            Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION,
            Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION,
        ];

        return [
            'acceptation: settled by acceptance or by a revision request' => [
                Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION,
                $settledByDecision,
                [Episciences_Paper::STATUS_REVIEWED, Episciences_Paper::STATUS_SUBMITTED],
            ],
            'refus: settled by acceptance or by a revision request' => [
                Episciences_CommentsManager::TYPE_SUGGESTION_REFUS,
                $settledByDecision,
                [Episciences_Paper::STATUS_REVIEWED, Episciences_Paper::STATUS_SUBMITTED],
            ],
            'new version: settled by acceptance or by a revision request' => [
                Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION,
                $settledByDecision,
                [Episciences_Paper::STATUS_REVIEWED, Episciences_Paper::STATUS_SUBMITTED],
            ],
            'unknown type: nothing is considered acted upon' => [
                9999,
                [],
                [Episciences_Paper::STATUS_ACCEPTED, Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // isSuggestionFilterAllowed(): the filter is confined to the management lists
    // -----------------------------------------------------------------------

    /**
     * Runs $callback with the front controller pointed at $controller, then restores it.
     */
    private function withCurrentController(?string $controller, callable $callback): mixed
    {
        $front = \Zend_Controller_Front::getInstance();
        $previousRequest = $front->getRequest();

        try {
            if ($controller !== null) {
                $request = new \Zend_Controller_Request_Http('http://localhost/');
                $request->setControllerName($controller);
                $front->setRequest($request);
            } else {
                $property = new \ReflectionProperty(\Zend_Controller_Front::class, '_request');
                $property->setAccessible(true);
                $property->setValue($front, null);
            }

            return $callback();
        } finally {
            $property = new \ReflectionProperty(\Zend_Controller_Front::class, '_request');
            $property->setAccessible(true);
            $property->setValue($front, $previousRequest);
        }
    }

    /**
     * @dataProvider disallowedSuggestionFilterControllerProvider
     */
    public function testSuggestionFilterIsNotAllowedOutsideTheManagementLists(?string $controller): void
    {
        // Short-circuits before any authentication check: the controller alone rules it out.
        $allowed = $this->withCurrentController(
            $controller,
            static fn(): bool => Episciences_PapersManager::isSuggestionFilterAllowed()
        );

        self::assertFalse($allowed);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function disallowedSuggestionFilterControllerProvider(): array
    {
        return [
            'author paper list (paper/submitted)' => ['paper'],
            'unrelated controller' => ['volume'],
            'no dispatched request (CLI)' => [null],
        ];
    }

    /**
     * The suggestion filter must be ignored when it reaches applyFilters() from a list that does
     * not offer it, e.g. a hand-crafted /paper/submitted?suggestion=8 request.
     */
    public function testApplyFiltersIgnoresSuggestionOutsideTheManagementLists(): void
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, 'applyFilters');
        $reflection->setAccessible(true);

        $sql = $this->withCurrentController('paper', function () use ($reflection): string {
            /** @var \Zend_Db_Select $select */
            $select = $reflection->invoke(
                null,
                $this->newPapersSelect(),
                ['is' => ['suggestion' => (string)Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION]]
            );

            return $select->assemble();
        });

        self::assertStringNotContainsString('DOCID IN', $sql);
        self::assertStringNotContainsString(T_PAPER_COMMENTS, $sql);
    }

    // -----------------------------------------------------------------------
    // getVolumesQuery() / volumesFilter() / applyFilters(): the 'vid' filter must scope
    // its subquery to the rvid being filtered on, not to the global RVID constant.
    //
    // RVID is defined once per PHP process. A long-running CLI process that loops over
    // several journals (e.g. zbjats:zip) defines it for the first journal only, then every
    // following journal keeps querying under the first journal's rvid and silently gets
    // zero results.
    // -----------------------------------------------------------------------

    private function newPapersSelect(): \Zend_Db_Select
    {
        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->select()->from(['papers' => T_PAPERS], ['DOCID']);
    }

    public function testGetVolumesQueryFallsBackToGlobalRvidConstantWhenNoneGiven(): void
    {
        $sql = Episciences_PapersManager::getVolumesQuery()->assemble();

        self::assertStringContainsString('st.RVID = ' . RVID, $sql);
    }

    public function testGetVolumesQueryUsesTheExplicitRvidOverTheGlobalConstant(): void
    {
        $otherRvid = RVID + 41;

        $sql = Episciences_PapersManager::getVolumesQuery(['DOCID'], $otherRvid)->assemble();

        self::assertStringContainsString('st.RVID = ' . $otherRvid, $sql);
        self::assertStringNotContainsString('st.RVID = ' . RVID . ' ', $sql . ' ');
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivateVolumesFilter(array $args): \Zend_Db_Select
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, 'volumesFilter');
        $reflection->setAccessible(true);

        /** @var \Zend_Db_Select $result */
        $result = $reflection->invoke(null, ...$args);

        return $result;
    }

    public function testVolumesFilterScopesItsSubqueryToTheProvidedRvid(): void
    {
        $otherRvid = RVID + 41;

        $sql = $this->invokePrivateVolumesFilter([$this->newPapersSelect(), [5], false, $otherRvid])->assemble();

        self::assertStringContainsString('st.RVID = ' . $otherRvid, $sql);
        self::assertStringNotContainsString('st.RVID = ' . RVID . ' ', $sql . ' ');
    }

    public function testVolumesFilterFallsBackToGlobalRvidConstantWhenNoneGiven(): void
    {
        $sql = $this->invokePrivateVolumesFilter([$this->newPapersSelect(), [5]])->assemble();

        self::assertStringContainsString('st.RVID = ' . RVID, $sql);
    }

    /**
     * Reproduces Episciences_Volume::getPaperListFromVolume(), which filters papers by
     * ['is' => ['rvid' => ..., 'vid' => [...]]] for one specific journal.
     */
    public function testApplyFiltersThreadsTheRvidFilterIntoTheVidSubquery(): void
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, 'applyFilters');
        $reflection->setAccessible(true);

        $otherRvid = RVID + 41;

        /** @var \Zend_Db_Select $select */
        $select = $reflection->invoke(
            null,
            $this->newPapersSelect(),
            ['is' => ['rvid' => $otherRvid, 'vid' => [5]]]
        );

        $sql = $select->assemble();

        self::assertStringContainsString('RVID = ' . $otherRvid, $sql);
        self::assertStringContainsString('st.RVID = ' . $otherRvid, $sql, 'The VID subquery must be scoped to the requested rvid, not the global RVID constant');
        self::assertStringNotContainsString('st.RVID = ' . RVID . ' ', $sql . ' ');
    }

    public function testApplyFiltersFallsBackToGlobalRvidConstantWhenNoRvidFilterIsGiven(): void
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, 'applyFilters');
        $reflection->setAccessible(true);

        /** @var \Zend_Db_Select $select */
        $select = $reflection->invoke(
            null,
            $this->newPapersSelect(),
            ['is' => ['vid' => [5]]]
        );

        $sql = $select->assemble();

        self::assertStringContainsString('st.RVID = ' . RVID, $sql);
    }

    /**
     * Reproduces callers using the uppercase 'RVID' filter key convention (e.g. getList()),
     * combined with a 'vid' filter, to ensure the rvid lookup is not case-sensitive.
     */
    public function testApplyFiltersThreadsUppercaseRvidFilterKeyIntoTheVidSubquery(): void
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, 'applyFilters');
        $reflection->setAccessible(true);

        $otherRvid = RVID + 41;

        /** @var \Zend_Db_Select $select */
        $select = $reflection->invoke(
            null,
            $this->newPapersSelect(),
            ['is' => ['RVID' => $otherRvid, 'vid' => [5]]]
        );

        $sql = $select->assemble();

        self::assertStringContainsString(
            'st.RVID = ' . $otherRvid,
            $sql,
            'The VID subquery must be scoped to the requested rvid regardless of the filter key casing'
        );
        self::assertStringNotContainsString('st.RVID = ' . RVID . ' ', $sql . ' ');
    }

    // -----------------------------------------------------------------------
    // dataTableSearchQuery(): the DataTable "search" box must scope its secondary-volume
    // subquery to the rvid being filtered on, not to the global RVID constant.
    // -----------------------------------------------------------------------

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivateDataTableSearchQuery(array $args): \Zend_Db_Select
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, 'dataTableSearchQuery');
        $reflection->setAccessible(true);

        /** @var \Zend_Db_Select $result */
        $result = $reflection->invoke(null, ...$args);

        return $result;
    }

    public function testDataTableSearchQueryScopesSecondaryVolumeSubqueryToTheProvidedRvid(): void
    {
        $otherRvid = RVID + 41;

        $sql = $this->invokePrivateDataTableSearchQuery(
            [$this->newPapersSelect(), '', [5 => 'Volume A'], [], $otherRvid]
        )->assemble();

        self::assertStringContainsString('st.RVID = ' . $otherRvid, $sql);
        self::assertStringNotContainsString('st.RVID = ' . RVID . ' ', $sql . ' ');
    }

    public function testDataTableSearchQueryFallsBackToGlobalRvidConstantWhenNoneGiven(): void
    {
        $sql = $this->invokePrivateDataTableSearchQuery(
            [$this->newPapersSelect(), '', [5 => 'Volume A'], []]
        )->assemble();

        self::assertStringContainsString('st.RVID = ' . RVID, $sql);
    }

    // -----------------------------------------------------------------------
    // applySuggestionFilter() / getPapersWithPendingSuggestionQuery()
    // -----------------------------------------------------------------------

    /**
     * @param array<int, int|string>|string $values
     */
    private function assembleSuggestionFilter(array|string $values): string
    {
        $reflection = new \ReflectionMethod(Episciences_PapersManager::class, 'applySuggestionFilter');
        $reflection->setAccessible(true);

        /** @var \Zend_Db_Select $result */
        $result = $reflection->invoke(null, $this->newPapersSelect(), $values);

        return $result->assemble();
    }

    public function testApplySuggestionFilterBuildsAssemblableQueryForSingleType(): void
    {
        $sql = $this->assembleSuggestionFilter((string)Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION);

        self::assertStringContainsString('DOCID IN', $sql);
        self::assertStringContainsString('c.TYPE = ' . Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION, $sql);
        // A single type must not produce an OR of several type conditions.
        self::assertStringNotContainsString(' OR ', $sql);
    }

    public function testApplySuggestionFilterExpandsAnyToAllSuggestionTypes(): void
    {
        $sql = $this->assembleSuggestionFilter([Episciences_PapersManager::ANY_SUGGESTION_FILTER]);

        // 'any' must expand into a condition per known suggestion type, joined with OR.
        foreach (Episciences_CommentsManager::$suggestionTypes as $type) {
            self::assertStringContainsString('c.TYPE = ' . $type, $sql);
        }
        self::assertSame(
            count(Episciences_CommentsManager::$suggestionTypes) - 1,
            substr_count($sql, ' OR ')
        );
    }

    public function testApplySuggestionFilterCombinesSeveralExplicitTypes(): void
    {
        $types = [
            Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION,
            Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION,
        ];

        $sql = $this->assembleSuggestionFilter($types);

        foreach ($types as $type) {
            self::assertStringContainsString('c.TYPE = ' . $type, $sql);
        }
        self::assertStringNotContainsString(
            'c.TYPE = ' . Episciences_CommentsManager::TYPE_SUGGESTION_REFUS,
            $sql,
            'A type that was not requested must not be filtered on'
        );
        // One exclusion list per requested type, plus the shared "finalized statuses" one.
        self::assertSame(3, substr_count($sql, 'p.STATUS NOT IN'));
        self::assertStringContainsString(' OR ', $sql);
    }

    /**
     * @dataProvider unknownSuggestionFilterValueProvider
     * @param array<int, int|string>|string $values
     */
    public function testApplySuggestionFilterMatchesNothingForUnknownTypes(array|string $values): void
    {
        $sql = $this->assembleSuggestionFilter($values);

        // Unknown types must exclude everything, never degrade into "any comment at all".
        self::assertStringContainsString('1 = 0', $sql);
        self::assertStringNotContainsString('c.TYPE =', $sql);
    }

    /**
     * @return array<string, array{array<int, int|string>|string}>
     */
    public static function unknownSuggestionFilterValueProvider(): array
    {
        return [
            'unrelated comment type' => [['3']],
            'non numeric value' => [["' OR 1=1 --"]],
            'empty list' => [[]],
            'zero' => ['0'],
        ];
    }

    public function testCountPendingSuggestionsByTypeReturnsEveryTypeForReviewWithNoPapers(): void
    {
        // RVID 0 never matches a real review's papers.
        $counts = Episciences_PapersManager::countPendingSuggestionsByType(0);

        self::assertSame(Episciences_CommentsManager::$suggestionTypes, array_keys($counts));
        self::assertSame([0, 0, 0], array_values($counts));
    }

    public function testCountPapersWithPendingSuggestionsReturnsZeroForReviewWithNoPapers(): void
    {
        // RVID 0 never matches a real review's papers.
        $count = Episciences_PapersManager::countPapersWithPendingSuggestions(0, Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION);

        self::assertSame(0, $count);
    }

    public function testCountPapersWithPendingSuggestionsReturnsZeroForUnknownType(): void
    {
        self::assertSame(0, Episciences_PapersManager::countPapersWithPendingSuggestions(0, 9999));
    }
}
