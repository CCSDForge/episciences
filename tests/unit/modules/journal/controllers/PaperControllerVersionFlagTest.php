<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Source-level regression guards for the FLAG of new and temporary versions.
 *
 * PaperController::initializeNewPaper() and PaperController::savetmpversionAction()
 * build the next version by cloning the previous one. An earlier version kept the
 * cloned FLAG, so every version of an imported paper was saved as 'imported'.
 * Both methods must reset the FLAG to 'submitted' on the clone.
 *
 * ZF1 controllers are not instantiable in isolation, so — like the other
 * controller source-analysis tests in this suite — these assertions check the
 * source rather than executing the actions against a real request/DB.
 */
final class PaperControllerVersionFlagTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/PaperController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in PaperController");

        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        $end2 = strpos($this->source, "\n    protected function ", (int) $start + 1);
        $end3 = strpos($this->source, "\n    private function ", (int) $start + 1);
        $candidates = array_filter([$end, $end2, $end3], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($this->source);

        return substr($this->source, (int) $start, $stop - (int) $start);
    }

    // =========================================================================
    // initializeNewPaper()
    // =========================================================================

    public function testNewVersionFlagIsResetToSubmitted(): void
    {
        $method = $this->extractMethod('initializeNewPaper');

        self::assertStringContainsString(
            "\$newPaper->setFlag('submitted')",
            $method,
            'initializeNewPaper() must reset the FLAG of the new version to submitted.'
        );
    }

    public function testNewVersionFlagIsResetAfterCloneAndBeforeReturn(): void
    {
        $method = $this->extractMethod('initializeNewPaper');

        $clonePos = strpos($method, 'clone $paper');
        $flagPos = strpos($method, "\$newPaper->setFlag('submitted')");
        $returnPos = strpos($method, 'return $newPaper');

        self::assertNotFalse($clonePos);
        self::assertNotFalse($flagPos);
        self::assertNotFalse($returnPos);
        self::assertLessThan($flagPos, $clonePos, 'The FLAG must be reset after the previous version is cloned.');
        self::assertLessThan($returnPos, $flagPos, 'The FLAG must be reset before the new version is returned.');
    }

    // =========================================================================
    // savetmpversionAction()
    // =========================================================================

    public function testTmpVersionFlagIsResetToSubmitted(): void
    {
        $method = $this->extractMethod('savetmpversionAction');

        self::assertStringContainsString(
            "\$tmpPaper->setFlag('submitted')",
            $method,
            'savetmpversionAction() must reset the FLAG of the temporary version to submitted.'
        );
    }

    public function testTmpVersionFlagIsResetAfterCloneAndBeforeSave(): void
    {
        $method = $this->extractMethod('savetmpversionAction');

        $clonePos = strpos($method, 'clone($paper)');
        $flagPos = strpos($method, "\$tmpPaper->setFlag('submitted')");
        $savePos = strpos($method, '$tmpPaper->save()');

        self::assertNotFalse($clonePos);
        self::assertNotFalse($flagPos);
        self::assertNotFalse($savePos);
        self::assertLessThan($flagPos, $clonePos, 'The FLAG must be reset after the previous version is cloned.');
        self::assertLessThan($savePos, $flagPos, 'The FLAG must be reset before the temporary version is saved.');
    }
}
