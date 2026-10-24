<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper;
use PaperController;
use ReflectionClass;
use ReflectionMethod;
use unit\modules\ControllerTestCase;

/**
 * FLAG of new and temporary versions.
 *
 * PaperController::initializeNewPaper() and PaperController::initializeTmpPaper() build the next
 * version by cloning the previous one. An earlier version kept the cloned FLAG, so every version
 * of an imported paper was saved as 'imported'.
 */
final class PaperControllerVersionFlagTest extends ControllerTestCase
{
    private const DOCID = 12;
    private const PAPERID = 10;

    private PaperController $controller;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'PaperController');
    }

    protected function setUp(): void
    {
        $this->controller = (new ReflectionClass(PaperController::class))->newInstanceWithoutConstructor();
    }

    public function testNewVersionOfAnImportedPaperIsSubmitted(): void
    {
        $paper = self::importedPaper();
        $post = [PaperController::SEARCH_DOC_STR => ['h_docId' => ' 2401.00001 ', 'h_repoId' => '2']];

        $newPaper = $this->invoke('initializeNewPaper', $paper, $post, 3.0, false, false, '<record/>');

        self::assertSame('submitted', $newPaper->getFlag());
        self::assertSame('imported', $paper->getFlag(), 'the previous version is left unchanged');
        self::assertEmpty($newPaper->getDocid(), 'the new version is not saved yet');
        self::assertSame(self::PAPERID, (int)$newPaper->getPaperid());
        self::assertSame(Episciences_Paper::STATUS_SUBMITTED, $newPaper->getStatus());
        self::assertSame('2401.00001', $newPaper->getIdentifier());
    }

    public function testTemporaryVersionOfAnImportedPaperIsSubmitted(): void
    {
        $paper = self::importedPaper();

        $tmpPaper = $this->invoke('initializeTmpPaper', $paper, Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION, '["answer.pdf"]');

        self::assertSame('submitted', $tmpPaper->getFlag());
        self::assertSame('imported', $paper->getFlag(), 'the previous version is left unchanged');
        self::assertEmpty($tmpPaper->getDocid(), 'the temporary version is not saved yet');
        self::assertSame(self::PAPERID, (int)$tmpPaper->getPaperid());
        self::assertSame(Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION, $tmpPaper->getStatus());
        self::assertSame(self::PAPERID . '/["answer.pdf"]', $tmpPaper->getIdentifier());
        self::assertEqualsWithDelta(2.01, (float)$tmpPaper->getVersion(), 0.0001);
        self::assertSame(0, (int)$tmpPaper->getRepoid());
        self::assertSame('<record/>', $tmpPaper->getRecord());
    }

    private static function importedPaper(): Episciences_Paper
    {
        $paper = new Episciences_Paper();
        $paper->setDocid(self::DOCID);
        $paper->setPaperid(self::PAPERID);
        $paper->setFlag('imported');
        $paper->setVersion(2);
        $paper->setStatus(Episciences_Paper::STATUS_OBSOLETE);
        $paper->setRecord('<record/>');

        return $paper;
    }

    private function invoke(string $method, mixed ...$arguments): Episciences_Paper
    {
        return (new ReflectionMethod(PaperController::class, $method))->invoke($this->controller, ...$arguments);
    }
}
