<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Episciences_Paper::isAssignedEditorOrCopyEditor(), Episciences_Paper::isAssignedEditor()
 * and Episciences_Paper::findLatestVersionForAssignmentCheck()
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

    public function testFindLatestVersionForAssignmentCheckHoldsTheLatestVersionIdentifiers(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId', 'getDocid', 'getPaperid']);
        $paper->method('getLatestVersionId')->willReturn('12');
        $paper->method('getDocid')->willReturn(10);
        $paper->method('getPaperid')->willReturn(10);

        $latestVersion = $paper->findLatestVersionForAssignmentCheck();

        self::assertInstanceOf(Episciences_Paper::class, $latestVersion);
        self::assertSame(12, $latestVersion->getDocid());
        self::assertSame(10, $latestVersion->getPaperid());
    }

    /**
     * @dataProvider latestVersionIdProvider
     */
    public function testFindLatestVersionReturnsNullWithoutAnotherVersion(string|false $latestVersionId): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId', 'getDocid']);
        $paper->method('getLatestVersionId')->willReturn($latestVersionId);
        $paper->method('getDocid')->willReturn(10);

        self::assertNull($paper->findLatestVersionForAssignmentCheck());
    }

    public static function latestVersionIdProvider(): array
    {
        return [
            'this paper is the latest version' => ['10'],
            'latest version not found' => [false],
        ];
    }
}
