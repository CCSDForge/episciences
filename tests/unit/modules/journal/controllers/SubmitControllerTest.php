<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for SubmitController.
 *
 * Strategy: source-code pattern analysis (static analysis via PHP string inspection).
 * No database or HTTP dispatch needed — tests are fast and run without side effects.
 *
 */
final class SubmitControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string)file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/SubmitController.php'
        );
    }

    // -----------------------------------------------------------------------
    // Helper: extract a method body from the source by its name
    // -----------------------------------------------------------------------

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName);
        self::assertNotFalse($start, "Method $methodName not found in SubmitController");

        $end = strpos($this->source, 'function ', $start + strlen('function ' . $methodName));
        if ($end === false) {
            return substr($this->source, $start);
        }

        return substr($this->source, $start, $end - $start);
    }

    // -----------------------------------------------------------------------
    // Bug (fixed): missing-file error attached to the wrong form element
    // -----------------------------------------------------------------------

    /**
     * handleCoverLetterValidation() reports "cover letter file is required" when
     * Episciences_Submit::validateCoverLetterRequirement() rejects the submission.
     * That error is about the missing *file*, so it must be attached to
     * COVER_LETTER_FILE_ELEMENT_NAME. The original code attached it to
     * COVER_LETTER_COMMENT_ELEMENT_NAME (the always-optional comment textarea)
     * instead, so the message rendered next to the wrong field and the actual
     * required file input showed no inline error.
     */
    public function testHandleCoverLetterValidationAttachesErrorToFileElement(): void
    {
        $method = $this->extractMethod('handleCoverLetterValidation');

        self::assertStringContainsString(
            'Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME',
            $method,
            'handleCoverLetterValidation() must attach the missing-file error to the file element'
        );
    }

    public function testHandleCoverLetterValidationDoesNotAttachErrorToCommentElement(): void
    {
        $method = $this->extractMethod('handleCoverLetterValidation');

        self::assertStringNotContainsString(
            'Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME',
            $method,
            'handleCoverLetterValidation() must not attach the missing-file error to the comment element'
        );
    }
}