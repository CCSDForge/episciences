<?php

namespace unit\library\Episciences\Paper;

use Episciences_Paper_DoiQueue;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper_DoiQueue.
 *
 * All tests are DB-free: DoiQueue is a pure value object with no I/O.
 *
 * Changes covered by these tests:
 *   - STATUS_UPDATE_PENDING added to $statusList and $htmlStatus (May 2026)
 *   - Typed properties: uninitialized getters must return safe defaults (0 / '')
 *   - getDoi_status() lazily defaults to STATUS_NOT_ASSIGNED when unset or empty
 *   - getStatusHtmlTemplate() falls back to DEFAULT_STATUS for unknown input
 *   - setOptions() populates properties via convention-based method dispatch
 *
 * @covers Episciences_Paper_DoiQueue
 */
final class Episciences_Paper_DoiQueueTest extends TestCase
{
    // =========================================================================
    // Constants and $statusList / $htmlStatus consistency
    // =========================================================================

    public function testStatusUpdatePendingConstantExists(): void
    {
        self::assertSame('update-pending', Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING);
    }

    public function testStatusUpdatePendingIsInStatusList(): void
    {
        self::assertContains(
            Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING,
            Episciences_Paper_DoiQueue::$statusList
        );
    }

    public function testStatusUpdatePendingIsInHtmlStatus(): void
    {
        self::assertArrayHasKey(
            Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING,
            Episciences_Paper_DoiQueue::$htmlStatus
        );
    }

    public function testAllStatusListEntriesHaveHtmlTemplate(): void
    {
        foreach (Episciences_Paper_DoiQueue::$statusList as $status) {
            self::assertArrayHasKey(
                $status,
                Episciences_Paper_DoiQueue::$htmlStatus,
                "Status '{$status}' is in \$statusList but has no entry in \$htmlStatus."
            );
        }
    }

    public function testAllHtmlStatusEntriesAreInStatusList(): void
    {
        foreach (array_keys(Episciences_Paper_DoiQueue::$htmlStatus) as $status) {
            self::assertContains(
                $status,
                Episciences_Paper_DoiQueue::$statusList,
                "Status '{$status}' is in \$htmlStatus but missing from \$statusList."
            );
        }
    }

    // =========================================================================
    // getStatusHtmlTemplate()
    // =========================================================================

    public function testGetStatusHtmlTemplateReturnsTemplateForNotAssigned(): void
    {
        $tpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::STATUS_NOT_ASSIGNED);
        self::assertStringContainsString('%s', $tpl);
        self::assertStringContainsString('label-default', $tpl);
    }

    public function testGetStatusHtmlTemplateReturnsTemplateForAssigned(): void
    {
        $tpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::STATUS_ASSIGNED);
        self::assertStringContainsString('label-primary', $tpl);
    }

    public function testGetStatusHtmlTemplateReturnsTemplateForRequested(): void
    {
        $tpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::STATUS_REQUESTED);
        self::assertStringContainsString('label-warning', $tpl);
    }

    public function testGetStatusHtmlTemplateReturnsTemplateForUpdatePending(): void
    {
        $tpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING);
        self::assertStringContainsString('label-info', $tpl);
    }

    public function testGetStatusHtmlTemplateReturnsTemplateForPublic(): void
    {
        $tpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::STATUS_PUBLIC);
        self::assertStringContainsString('label-success', $tpl);
    }

    public function testGetStatusHtmlTemplateReturnsTemplateForManual(): void
    {
        $tpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::STATUS_MANUAL);
        self::assertStringContainsString('label-default', $tpl);
    }

    /**
     * Unknown status must fall back to DEFAULT_STATUS (not throw / not return empty).
     */
    public function testGetStatusHtmlTemplateUnknownStatusFallsBackToDefault(): void
    {
        $defaultTpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::DEFAULT_STATUS);
        $unknownTpl = Episciences_Paper_DoiQueue::getStatusHtmlTemplate('completely-unknown');

        self::assertSame($defaultTpl, $unknownTpl);
    }

    // =========================================================================
    // getHtmlStatus()
    // =========================================================================

    public function testGetHtmlStatusReturnsArray(): void
    {
        self::assertIsArray(Episciences_Paper_DoiQueue::getHtmlStatus());
    }

    public function testGetHtmlStatusIsSameAsStaticProperty(): void
    {
        self::assertSame(Episciences_Paper_DoiQueue::$htmlStatus, Episciences_Paper_DoiQueue::getHtmlStatus());
    }

    // =========================================================================
    // Uninitialized-property safe defaults
    // =========================================================================

    /**
     * getId_doi_queue() must return 0 when the object was constructed without options.
     */
    public function testGetIdDoiQueueReturnsZeroWhenUninitialized(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        self::assertSame(0, $q->getId_doi_queue());
    }

    /**
     * getPaperid() must return 0 when the object was constructed without options.
     */
    public function testGetPaperidReturnsZeroWhenUninitialized(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        self::assertSame(0, $q->getPaperid());
    }

    /**
     * getDate_init() must return '' when unset.
     */
    public function testGetDateInitReturnsEmptyStringWhenUninitialized(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        self::assertSame('', $q->getDate_init());
    }

    /**
     * getDate_updated() must return '' when unset.
     */
    public function testGetDateUpdatedReturnsEmptyStringWhenUninitialized(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        self::assertSame('', $q->getDate_updated());
    }

    /**
     * getDoi_status() must default to STATUS_NOT_ASSIGNED when unset.
     */
    public function testGetDoiStatusDefaultsToNotAssignedWhenUninitialized(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        self::assertSame(Episciences_Paper_DoiQueue::STATUS_NOT_ASSIGNED, $q->getDoi_status());
    }

    /**
     * getDoi_status() must default to STATUS_NOT_ASSIGNED when explicitly set to ''.
     */
    public function testGetDoiStatusDefaultsToNotAssignedWhenSetToEmptyString(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        $q->setDoi_status('');
        self::assertSame(Episciences_Paper_DoiQueue::STATUS_NOT_ASSIGNED, $q->getDoi_status());
    }

    // =========================================================================
    // Setters and getters round-trip
    // =========================================================================

    public function testSetAndGetId(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        $q->setId_doi_queue(99);
        self::assertSame(99, $q->getId_doi_queue());
    }

    public function testSetAndGetPaperid(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        $q->setPaperid(42);
        self::assertSame(42, $q->getPaperid());
    }

    public function testSetAndGetDoiStatus(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        $q->setDoi_status(Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING);
        self::assertSame(Episciences_Paper_DoiQueue::STATUS_UPDATE_PENDING, $q->getDoi_status());
    }

    public function testSetAndGetDateInit(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        $q->setDate_init('2026-01-15 10:00:00');
        self::assertSame('2026-01-15 10:00:00', $q->getDate_init());
    }

    public function testSetAndGetDateUpdated(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        $q->setDate_updated('2026-05-01 12:30:00');
        self::assertSame('2026-05-01 12:30:00', $q->getDate_updated());
    }

    // =========================================================================
    // setOptions() — convention-based hydration
    // =========================================================================

    public function testSetOptionsPopulatesAllFields(): void
    {
        $options = [
            'id_doi_queue' => 7,
            'paperid'      => 123,
            'doi_status'   => Episciences_Paper_DoiQueue::STATUS_ASSIGNED,
            'date_init'    => '2026-01-01 00:00:00',
            'date_updated' => '2026-04-01 00:00:00',
        ];

        $q = new Episciences_Paper_DoiQueue($options);

        self::assertSame(7, $q->getId_doi_queue());
        self::assertSame(123, $q->getPaperid());
        self::assertSame(Episciences_Paper_DoiQueue::STATUS_ASSIGNED, $q->getDoi_status());
        self::assertSame('2026-01-01 00:00:00', $q->getDate_init());
        self::assertSame('2026-04-01 00:00:00', $q->getDate_updated());
    }

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        $q = new Episciences_Paper_DoiQueue(['unknown_key' => 'value', 'paperid' => 5]);
        self::assertSame(5, $q->getPaperid());
    }

    public function testSetOptionsIsCaseInsensitiveForKeys(): void
    {
        $q = new Episciences_Paper_DoiQueue(['PAPERID' => 10]);
        self::assertSame(10, $q->getPaperid());
    }

    public function testConstructWithNullOptionsLeavesPropertiesUninitialized(): void
    {
        $q = new Episciences_Paper_DoiQueue(null);
        self::assertSame(0, $q->getId_doi_queue());
        self::assertSame(0, $q->getPaperid());
    }

    // =========================================================================
    // toArray()
    // =========================================================================

    public function testToArrayHasExpectedKeys(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        $keys = array_keys($q->toArray());

        self::assertSame(['id_doi_queue', 'paperid', 'doi_status', 'date_init', 'date_updated'], $keys);
    }

    public function testToArrayReflectsSetValues(): void
    {
        $q = new Episciences_Paper_DoiQueue([
            'id_doi_queue' => 1,
            'paperid'      => 2,
            'doi_status'   => Episciences_Paper_DoiQueue::STATUS_PUBLIC,
            'date_init'    => '2026-01-01 00:00:00',
            'date_updated' => '2026-05-01 00:00:00',
        ]);

        self::assertSame([
            'id_doi_queue' => 1,
            'paperid'      => 2,
            'doi_status'   => Episciences_Paper_DoiQueue::STATUS_PUBLIC,
            'date_init'    => '2026-01-01 00:00:00',
            'date_updated' => '2026-05-01 00:00:00',
        ], $q->toArray());
    }

    public function testToArrayDefaultStatusIsNotAssigned(): void
    {
        $q = new Episciences_Paper_DoiQueue();
        self::assertSame(Episciences_Paper_DoiQueue::STATUS_NOT_ASSIGNED, $q->toArray()['doi_status']);
    }
}
