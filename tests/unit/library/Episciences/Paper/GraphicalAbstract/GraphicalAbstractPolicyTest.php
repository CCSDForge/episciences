<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\GraphicalAbstract;

use Episciences\Paper\GraphicalAbstract\GraphicalAbstractPolicy;
use Episciences_Paper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Paper\GraphicalAbstract\GraphicalAbstractPolicy
 */
final class GraphicalAbstractPolicyTest extends TestCase
{
    private const IN_PROGRESS = [
        Episciences_Paper::STATUS_SUBMITTED,
        Episciences_Paper::STATUS_BEING_REVIEWED,
        Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION,
        Episciences_Paper::STATUS_ACCEPTED,
        Episciences_Paper::STATUS_CE_READY_TO_PUBLISH,
        Episciences_Paper::STATUS_TMP_VERSION,
    ];

    private const CLOSED = [
        Episciences_Paper::STATUS_REFUSED,
        Episciences_Paper::STATUS_OBSOLETE,
        Episciences_Paper::STATUS_REMOVED,
        Episciences_Paper::STATUS_DELETED,
        Episciences_Paper::STATUS_ABANDONED,
    ];

    /**
     * @return array<string, array{int}>
     */
    public static function inProgressStatuses(): array
    {
        return array_combine(array_map(static fn(int $s) => "status $s", self::IN_PROGRESS), array_map(static fn(int $s) => [$s], self::IN_PROGRESS));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function closedStatuses(): array
    {
        return array_combine(array_map(static fn(int $s) => "status $s", self::CLOSED), array_map(static fn(int $s) => [$s], self::CLOSED));
    }

    #[DataProvider('inProgressStatuses')]
    public function testManagersAndAuthorsEditTheVersionInProgress(int $status): void
    {
        self::assertTrue(GraphicalAbstractPolicy::isAllowed($status, true, true, false, false, false), 'secretary');
        self::assertTrue(GraphicalAbstractPolicy::isAllowed($status, true, false, true, false, false), 'assigned editor');
        self::assertTrue(GraphicalAbstractPolicy::isAllowed($status, true, false, false, true, false), 'author');
        self::assertFalse(GraphicalAbstractPolicy::isAllowed($status, true, false, false, false, false), 'editor not assigned');
    }

    #[DataProvider('inProgressStatuses')]
    public function testNobodyEditsAVersionThatIsNotTheLatestOne(int $status): void
    {
        self::assertFalse(GraphicalAbstractPolicy::isAllowed($status, false, true, true, true, true));
    }

    #[DataProvider('closedStatuses')]
    public function testNobodyEditsAClosedVersion(int $status): void
    {
        self::assertFalse(GraphicalAbstractPolicy::isAllowed($status, true, true, true, true, true));
    }

    public function testOnlyTheChiefEditorCorrectsAPublishedVersion(): void
    {
        $published = Episciences_Paper::STATUS_PUBLISHED;

        self::assertTrue(GraphicalAbstractPolicy::isAllowed($published, true, true, true, true, true));
        self::assertFalse(GraphicalAbstractPolicy::isAllowed($published, true, false, true, false, false), 'assigned editor');
        self::assertFalse(GraphicalAbstractPolicy::isAllowed($published, true, false, false, true, false), 'author');
        self::assertFalse(GraphicalAbstractPolicy::isAllowed($published, false, true, true, true, true), 'not the latest version');
    }
}
