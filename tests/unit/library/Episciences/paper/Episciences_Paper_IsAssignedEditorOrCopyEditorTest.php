<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Episciences_Paper::isAssignedEditorOrCopyEditor()
 * (the lookup on the latest version needs the database and is not covered here)
 */
final class Episciences_Paper_IsAssignedEditorOrCopyEditorTest extends TestCase
{
    private function createPaper(bool $isEditor, bool $isCopyEditor, string|false $latestVersionId): Episciences_Paper&MockObject
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getEditor',
            'getCopyEditor',
            'getLatestVersionId',
            'getDocid',
        ]);

        $paper->method('getEditor')->willReturn($isEditor);
        $paper->method('getCopyEditor')->willReturn($isCopyEditor);
        $paper->method('getLatestVersionId')->willReturn($latestVersionId);
        $paper->method('getDocid')->willReturn(10);

        return $paper;
    }

    public function testEditorAssignedToThisVersionIsAssigned(): void
    {
        $paper = $this->createPaper(true, false, '10');
        $paper->expects(self::never())->method('getLatestVersionId');

        self::assertTrue($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testCopyEditorAssignedToThisVersionIsAssigned(): void
    {
        $paper = $this->createPaper(false, true, '10');
        $paper->expects(self::never())->method('getLatestVersionId');

        self::assertTrue($paper->isAssignedEditorOrCopyEditor(42));
    }

    public function testUnassignedUserOnTheLatestVersionIsNotAssigned(): void
    {
        self::assertFalse($this->createPaper(false, false, '10')->isAssignedEditorOrCopyEditor(42));
    }

    public function testUnassignedUserIsNotAssignedWhenTheLatestVersionIsUnknown(): void
    {
        self::assertFalse($this->createPaper(false, false, false)->isAssignedEditorOrCopyEditor(42));
    }
}
