<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::isEligibleForMasterFileChoice.
 * The method determines whether the selection of a main file
 * is appropriate based on several criteria:
 * - The type of the submission,
 * - The number of available files and the permissions,
 * - The original repository: only those mirroring their files into PAPER_FILES
 *   are eligible, i.e. those whose hooks class declares FilesEnrichmentInterface.
 *
 * @covers Episciences_Paper::isEligibleForMasterFileChoice
 */
final class Episciences_Paper_isEligibleForMasterFileChoiceTest extends TestCase
{

    private Episciences_Paper&MockObject $paper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paper = $this->createPartialMock(
            Episciences_Paper::class,
            [
                'hasFilesEnrichment',
                'isDataSetOrSoftware',
                'getFiles',
                'isAllowedToEditMasterFile'
            ]
        );
    }

    public function testThatWhenAllCriteriaAreMet(): void
    {
        $this->paper->method('hasFilesEnrichment')->willReturn(true);
        $this->paper->method('isDataSetOrSoftware')->willReturn(false);
        $this->paper->method('getFiles')->willReturn(['file1.pdf', 'file2.md']);
        $this->paper->method('isAllowedToEditMasterFile')->willReturn(true);

        $result = $this->paper->isEligibleForMasterFileChoice();
        self::assertTrue($result);
    }


    public function testReturnsFalseWhenRepositoryHasNoFilesEnrichment(): void
    {

        $this->paper->method('hasFilesEnrichment')->willReturn(false);

        //the other methods must NOT be called
        $this->paper->expects(self::never())->method('isDataSetOrSoftware');
        $this->paper->expects(self::never())->method('getFiles');
        $this->paper->expects(self::never())->method('isAllowedToEditMasterFile');

        $result = $this->paper->isEligibleForMasterFileChoice();

        self::assertFalse($result);
    }


    public function testReturnsFalseWhenIsDataSetOrSoftwareIsFalse(): void
    {
        $this->paper->method('hasFilesEnrichment')->willReturn(true);
        $this->paper->method('isDataSetOrSoftware')->willReturn(true);

        // the other methods must NOT be called

        $this->paper->expects(self::never())->method('getFiles');
        $this->paper->expects(self::never())->method('isAllowedToEditMasterFile');

        $result = $this->paper->isEligibleForMasterFileChoice();

        self::assertFalse($result);
    }


    public function testReturnsFalseWhenEmptyFiles(): void
    {
        $this->paper->method('hasFilesEnrichment')->willReturn(true);
        $this->paper->method('isDataSetOrSoftware')->willReturn(false);
        $this->paper->method('getFiles')->willReturn([]);

        // isAllowedToEditMasterFile should not be called
        $this->paper->expects(self::never())->method('isAllowedToEditMasterFile');
        $result = $this->paper->isEligibleForMasterFileChoice();
        self::assertFalse($result);
    }


    public function testReturnsFalseWhenNotAllowedToChangeMainFile(): void
    {
        $this->paper->method('hasFilesEnrichment')->willReturn(true);
        $this->paper->method('isDataSetOrSoftware')->willReturn(false);
        $this->paper->method('getFiles')->willReturn(['file1.txt', 'file2.txt']);
        $this->paper->method('isAllowedToEditMasterFile')->willReturn(false);

        $result = $this->paper->isEligibleForMasterFileChoice();

        self::assertFalse($result);
    }
}