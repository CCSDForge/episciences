<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test suite for Episciences_Paper::isAssignedEditorOrCopyEditor(), Episciences_Paper::isAssignedEditor()
 * Episciences_Paper::findLatestVersionForAssignmentCheck() and Episciences_Paper::isEditorForReportVisibility()
 */
final class Episciences_Paper_IsAssignedEditorOrCopyEditorTest extends TestCase
{
    private function createPaper(bool $isEditor, bool $isCopyEditor, ?Episciences_Paper $latestVersion = null): Episciences_Paper&MockObject
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getEditor',
            'getCopyEditor',
            'findLatestVersionForAssignmentCheck',
        ]);

        $paper->method('getEditor')->willReturn($isEditor);
        $paper->method('getCopyEditor')->willReturn($isCopyEditor);
        $paper->method('findLatestVersionForAssignmentCheck')->willReturn($latestVersion);

        return $paper;
    }

    private function createLatestVersion(bool $isEditor, bool $isCopyEditor): Episciences_Paper
    {
        $latestVersion = $this->createPartialMock(Episciences_Paper::class, ['getEditor', 'getCopyEditor']);
        $latestVersion->method('getEditor')->willReturn($isEditor);
        $latestVersion->method('getCopyEditor')->willReturn($isCopyEditor);

        return $latestVersion;
    }

    public function testEditorAssignedToThisVersionIsAssigned(): void
    {
        $paper = $this->createPaper(true, false);
        $paper->expects(self::never())->method('findLatestVersionForAssignmentCheck');

        self::assertTrue($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testCopyEditorAssignedToThisVersionIsAssigned(): void
    {
        $paper = $this->createPaper(false, true);
        $paper->expects(self::never())->method('findLatestVersionForAssignmentCheck');

        self::assertTrue($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testEditorAssignedToTheLatestVersionIsAssignedToAPreviousVersion(): void
    {
        $paper = $this->createPaper(false, false, $this->createLatestVersion(true, false));

        self::assertTrue($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testCopyEditorAssignedToTheLatestVersionIsAssignedToAPreviousVersion(): void
    {
        $paper = $this->createPaper(false, false, $this->createLatestVersion(false, true));

        self::assertTrue($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testUserNotAssignedToTheLatestVersionIsNotAssignedToAPreviousVersion(): void
    {
        $paper = $this->createPaper(false, false, $this->createLatestVersion(false, false));

        self::assertFalse($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testUnassignedUserOnTheLatestVersionIsNotAssigned(): void
    {
        self::assertFalse($this->createPaper(false, false)->isAssignedEditorOrCopyEditor(42));
    }

    public function testEditorAssignedToThisVersionIsAssignedEditor(): void
    {
        $paper = $this->createPaper(true, false);
        $paper->expects(self::never())->method('findLatestVersionForAssignmentCheck');

        self::assertTrue($paper->isAssignedEditor(42));
    }

    public function testEditorAssignedToTheLatestVersionIsAssignedEditorOfAPreviousVersion(): void
    {
        $paper = $this->createPaper(false, false, $this->createLatestVersion(true, false));

        self::assertTrue($paper->isAssignedEditor(42));
    }

    public function testCopyEditorIsNotAssignedEditor(): void
    {
        $paper = $this->createPaper(false, true, $this->createLatestVersion(false, true));

        self::assertFalse($paper->isAssignedEditor(42));
    }

    public function testUnassignedUserOnTheLatestVersionIsNotAssignedEditor(): void
    {
        self::assertFalse($this->createPaper(false, false)->isAssignedEditor(42));
    }

    /**
     * @dataProvider invalidUidProvider
     */
    public function testInvalidUidIsNeverAssigned(int $uid): void
    {
        $paper = $this->createPaper(true, true, $this->createLatestVersion(true, true));
        $paper->expects(self::never())->method('getEditor');
        $paper->expects(self::never())->method('getCopyEditor');
        $paper->expects(self::never())->method('findLatestVersionForAssignmentCheck');

        self::assertFalse($paper->isAssignedEditorOrCopyEditor($uid));
        self::assertFalse($paper->isAssignedEditor($uid));
    }

    public static function invalidUidProvider(): array
    {
        return [
            'anonymous visitor' => [0],
            'negative uid' => [-1],
        ];
    }

    private function isEditorForReportVisibility(Episciences_Paper $paper, bool $hasEditorRole, bool $isEditorsEncapsulated): bool
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn(42);
        $user->method('isEditor')->willReturn($hasEditorRole);

        return (new ReflectionMethod(Episciences_Paper::class, 'isEditorForReportVisibility'))
            ->invoke($paper, $user, $isEditorsEncapsulated);
    }

    /**
     * @dataProvider reportVisibilityProvider
     */
    public function testEditorForReportVisibility(
        bool $isEditorOfThisVersion,
        bool $isEditorOfTheLatestVersion,
        bool $isCopyEditorOfTheLatestVersion,
        bool $hasEditorRole,
        bool $isEditorsEncapsulated,
        bool $expected
    ): void {
        $paper = $this->createPaper(
            $isEditorOfThisVersion,
            false,
            $this->createLatestVersion($isEditorOfTheLatestVersion, $isCopyEditorOfTheLatestVersion)
        );

        self::assertSame($expected, $this->isEditorForReportVisibility($paper, $hasEditorRole, $isEditorsEncapsulated));
    }

    public static function reportVisibilityProvider(): array
    {
        // [editor of this version, editor of the latest version, copy editor of the latest version, editor role, encapsulated, expected]
        return [
            'encapsulated: editor of this version' => [true, false, false, true, true, true],
            'encapsulated: editor of the latest version only' => [false, true, false, true, true, true],
            'encapsulated: guest editor of the latest version only' => [false, true, false, false, true, true],
            'encapsulated: unassigned editor' => [false, false, false, true, true, false],
            'encapsulated: copy editor of the latest version' => [false, false, true, false, true, false],
            'not encapsulated: unassigned editor' => [false, false, false, true, false, true],
            'not encapsulated: guest editor of the latest version only' => [false, true, false, false, false, true],
            'not encapsulated: unassigned user without editor role' => [false, false, false, false, false, false],
        ];
    }

    public function testEditorRoleWithoutEncapsulationSkipsTheAssignmentChecks(): void
    {
        $paper = $this->createPaper(false, false);
        $paper->expects(self::never())->method('getEditor');
        $paper->expects(self::never())->method('findLatestVersionForAssignmentCheck');

        self::assertTrue($this->isEditorForReportVisibility($paper, true, false));
    }

    public function testFindLatestVersionForAssignmentCheckHoldsTheLatestVersionIdentifiers(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId', 'getDocid', 'getPaperid']);
        $paper->method('getLatestVersionId')->willReturn('12');
        $paper->method('getDocid')->willReturn(10);
        $paper->method('getPaperid')->willReturn(10);

        $latestVersion = $this->findLatestVersionForAssignmentCheck($paper);

        self::assertInstanceOf(Episciences_Paper::class, $latestVersion);
        self::assertSame(12, $latestVersion->getDocid());
        self::assertSame(10, $latestVersion->getPaperid());
    }

    private function findLatestVersionForAssignmentCheck(Episciences_Paper $paper): ?Episciences_Paper
    {
        return (new ReflectionMethod(Episciences_Paper::class, 'findLatestVersionForAssignmentCheck'))->invoke($paper);
    }

    /**
     * @dataProvider latestVersionIdProvider
     */
    public function testFindLatestVersionReturnsNullWithoutAnotherVersion(string|false $latestVersionId): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId', 'getDocid']);
        $paper->method('getLatestVersionId')->willReturn($latestVersionId);
        $paper->method('getDocid')->willReturn(10);

        self::assertNull($this->findLatestVersionForAssignmentCheck($paper));
    }

    public static function latestVersionIdProvider(): array
    {
        return [
            'this paper is the latest version' => ['10'],
            'latest version not found' => [false],
        ];
    }
}
