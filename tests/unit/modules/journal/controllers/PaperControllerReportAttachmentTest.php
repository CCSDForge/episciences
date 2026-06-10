<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the file-name handling of PaperController::deleteattachmentreportAction().
 *
 * The action only accepts a plain file name (a report attachment), validated with
 * a character-class pattern before the path is built. ZF1 controllers are not
 * instantiable in isolation, so we:
 *   1. assert (source-level) the validation guard stays in place and runs before
 *      the file is removed;
 *   2. extract the actual pattern used in the source and check its behaviour, so
 *      the test follows the real validation rule rather than a copy of it.
 *
 * @covers PaperController::deleteattachmentreportAction
 */
final class PaperControllerReportAttachmentTest extends TestCase
{
    private string $source;
    private string $method;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/PaperController.php'
        );
        $this->method = $this->extractMethod('deleteattachmentreportAction');
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

    /** Pull the file-name validation pattern straight from the action body. */
    private function extractFileNamePattern(): string
    {
        if (!preg_match('/preg_match\(\s*([\'"])(.*?)\1\s*,\s*\$file\s*\)/', $this->method, $m)) {
            self::fail('Could not find the file-name validation pattern in deleteattachmentreportAction()');
        }
        return $m[2];
    }

    // -----------------------------------------------------------------------
    // Source-level guards
    // -----------------------------------------------------------------------

    public function testFileParamIsReadAsString(): void
    {
        self::assertStringContainsString("(string)\$request->getParam('file')", $this->method,
            'The file parameter must be read as a string');
    }

    public function testFileNameIsValidatedWithPregMatch(): void
    {
        self::assertMatchesRegularExpression('/preg_match\([\'"].*[\'"]\s*,\s*\$file\s*\)/', $this->method,
            'deleteattachmentreportAction() must validate $file with a character-class pattern');
    }

    public function testValidationRunsBeforeFileRemoval(): void
    {
        $validationPos = strpos($this->method, 'preg_match(');
        $unlinkPos     = strpos($this->method, 'unlink(');

        self::assertNotFalse($validationPos, 'Validation must be present');
        self::assertNotFalse($unlinkPos, 'The action must call unlink()');
        self::assertLessThan($unlinkPos, $validationPos,
            'The file name must be validated before unlink() is reached');
    }

    public function testInvalidNameShortCircuitsTheAction(): void
    {
        // The guard must return early when the name is invalid.
        $guardPos  = strpos($this->method, 'preg_match(');
        $returnPos = strpos($this->method, 'return;', (int) $guardPos);
        self::assertNotFalse($returnPos,
            'An invalid file name must short-circuit the action with an early return');
    }

    // -----------------------------------------------------------------------
    // Behaviour of the actual pattern used in the source
    // -----------------------------------------------------------------------

    /**
     * @dataProvider acceptedNames
     */
    public function testPatternAcceptsPlainFileNames(string $name): void
    {
        $pattern = $this->extractFileNamePattern();
        self::assertSame(1, preg_match($pattern, $name),
            "Plain file name '$name' must be accepted");
    }

    public static function acceptedNames(): array
    {
        return [
            ['report.pdf'],
            ['criterion_1-final.txt'],
            ['a.b.c'],
            ['Rapport-2024_v2.PDF'],
        ];
    }

    /**
     * @dataProvider rejectedNames
     */
    public function testPatternRejectsNamesWithSeparatorsOrControlChars(string $name, string $why): void
    {
        $pattern = $this->extractFileNamePattern();
        self::assertSame(0, preg_match($pattern, $name), $why);
    }

    public static function rejectedNames(): array
    {
        return [
            ['../report.pdf',     'a forward slash must be rejected'],
            ['sub/report.pdf',    'a forward slash must be rejected'],
            ['..\\report.pdf',    'a backslash must be rejected'],
            ["a\nb.pdf",          'an embedded newline must be rejected'],
            ['',                  'an empty name must be rejected'],
            ['report .pdf',       'a space must be rejected'],
        ];
    }
}
