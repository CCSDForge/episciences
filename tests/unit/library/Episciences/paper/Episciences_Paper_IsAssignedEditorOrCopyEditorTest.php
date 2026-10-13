<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Episciences_Paper::isAssignedEditorOrCopyEditor() and Episciences_Paper::findLatestVersion()
 */
final class Episciences_Paper_IsAssignedEditorOrCopyEditorTest extends TestCase
{
    private function createPaper(bool $isEditor, bool $isCopyEditor, ?Episciences_Paper $latestVersion = null): Episciences_Paper&MockObject
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getEditor',
            'getCopyEditor',
            'findLatestVersion',
        ]);

        $paper->method('getEditor')->willReturn($isEditor);
        $paper->method('getCopyEditor')->willReturn($isCopyEditor);
        $paper->method('findLatestVersion')->willReturn($latestVersion);

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
        $paper->expects(self::never())->method('findLatestVersion');

        self::assertTrue($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testCopyEditorAssignedToThisVersionIsAssigned(): void
    {
        $paper = $this->createPaper(false, true);
        $paper->expects(self::never())->method('findLatestVersion');

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

    /**
     * @dataProvider latestVersionIdProvider
     */
    public function testFindLatestVersionReturnsNullWithoutAnotherVersion(string|false $latestVersionId): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId', 'getDocid']);
        $paper->method('getLatestVersionId')->willReturn($latestVersionId);
        $paper->method('getDocid')->willReturn(10);

        self::assertNull($paper->findLatestVersion());
    }

    public static function latestVersionIdProvider(): array
    {
        return [
            'this paper is the latest version' => ['10'],
            'latest version not found' => [false],
        ];
    }
}
