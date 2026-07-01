<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Behavioural tests for UserDefaultController's affiliation (de)serialization helpers:
 * processAffiliations(), disassembleAffiliation(), assembleAffiliation().
 *
 * These are pure methods (no $this state, no DB, no MVC stack), so we instantiate
 * UserDefaultController without its constructor and invoke the private methods by
 * reflection — same approach as DefaultControllerResolveSafePathTest.
 *
 * Canonical storage shape is `{"label": ..., "rorId": ...}` (produced by 'disassemble',
 * the default, and used everywhere data is persisted). The flat "Label #rorId" string
 * shape only exists transiently: as the raw value typed/selected in the affiliations
 * autocomplete widget (public/js/user/affiliations.js) before submission, and as the
 * 'assemble' output used solely to prefill the edit-profile form. If these methods ever
 * regress, either shape could leak into ADDITIONAL_PROFILE_INFORMATION and break any
 * consumer expecting a single, consistent format (see UserDefaultController::createAction()
 * bug fixed alongside this test).
 *
 * @covers UserDefaultController::processAffiliations
 * @covers UserDefaultController::disassembleAffiliation
 * @covers UserDefaultController::assembleAffiliation
 */
final class UserDefaultControllerAffiliationsTest extends TestCase
{
    private object $controller;
    private string $source;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

        $class = new ReflectionClass(\UserDefaultController::class);
        $this->controller = $class->newInstanceWithoutConstructor();

        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php'
        );
    }

    /**
     * Same extraction approach as UserDefaultControllerRequestGuardTest: ZF1 module
     * controllers can't be dispatched outside the full request stack, so action bodies
     * are asserted on via source inspection.
     */
    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in UserDefaultController");

        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        $end2 = strpos($this->source, "\n    private function ", (int) $start + 1);
        $end3 = strpos($this->source, "\n    protected function ", (int) $start + 1);
        $candidates = array_filter([$end, $end2, $end3], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($this->source);

        return substr($this->source, (int) $start, $stop - (int) $start);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod(\UserDefaultController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->controller, $args);
    }

    /**
     * $operationType is left out of the reflection call entirely (rather than passed
     * as null) when omitted here, so the method's own default value ('disassemble')
     * is exercised — matching how UserDefaultController's call sites invoke it.
     */
    private function processAffiliations(mixed $input, ?string $operationType = null): mixed
    {
        $args = $operationType === null ? [$input] : [$input, $operationType];

        return $this->invokePrivate('processAffiliations', $args);
    }

    private function disassembleAffiliation(mixed $value, string $separator = '#'): mixed
    {
        return $this->invokePrivate('disassembleAffiliation', [$value, $separator]);
    }

    private function assembleAffiliation(mixed $value, string $separator = '#'): mixed
    {
        return $this->invokePrivate('assembleAffiliation', [$value, $separator]);
    }

    // -----------------------------------------------------------------------
    // disassembleAffiliation() — string ("Label #rorId") -> {label, rorId}
    // -----------------------------------------------------------------------

    public function testDisassembleStringWithValidRorId(): void
    {
        $result = $this->disassembleAffiliation('Donders Centre for Cognitive Neuroimaging [DCCN] #https://ror.org/01jdz5g73');

        self::assertSame([
            'label' => 'Donders Centre for Cognitive Neuroimaging [DCCN]',
            'rorId' => 'https://ror.org/01jdz5g73',
        ], $result);
    }

    public function testDisassembleStringWithoutRorId(): void
    {
        self::assertSame(['label' => 'Just A Label', 'rorId' => ''], $this->disassembleAffiliation('Just A Label'));
    }

    public function testDisassembleStringWithInvalidRorIdIsDropped(): void
    {
        $result = $this->disassembleAffiliation('Some Lab #not-a-ror-id');

        self::assertSame('Some Lab', $result['label']);
        self::assertSame('', $result['rorId']);
    }

    public function testDisassembleAlreadyCorrectArrayIsReturnedAsIs(): void
    {
        $value = ['label' => 'Already Correct', 'rorId' => 'https://ror.org/01jdz5g73'];

        self::assertSame($value, $this->disassembleAffiliation($value));
    }

    public function testDisassembleNonStringNonArrayFallsBackToEmpty(): void
    {
        self::assertSame(['label' => '', 'rorId' => ''], $this->disassembleAffiliation(42));
    }

    // -----------------------------------------------------------------------
    // assembleAffiliation() — {label, rorId} -> "Label #rorId" (form display only)
    // -----------------------------------------------------------------------

    public function testAssembleWithRorId(): void
    {
        $result = $this->assembleAffiliation(['label' => 'Some Lab', 'rorId' => 'https://ror.org/01jdz5g73']);

        self::assertSame('Some Lab#https://ror.org/01jdz5g73', $result);
    }

    public function testAssembleWithoutRorIdReturnsLabelOnly(): void
    {
        self::assertSame('Some Lab', $this->assembleAffiliation(['label' => 'Some Lab', 'rorId' => '']));
    }

    public function testAssembleStringPassthrough(): void
    {
        self::assertSame('Raw String', $this->assembleAffiliation('Raw String'));
    }

    // -----------------------------------------------------------------------
    // processAffiliations() — batch behaviour, default operation, count-independence
    // -----------------------------------------------------------------------

    public function testProcessAffiliationsDefaultsToDisassemble(): void
    {
        // Regression guard: the default must stay 'disassemble' so that
        // createAction()/editAction() persist objects, not raw strings, when the
        // operation type is omitted at a call site.
        $result = $this->processAffiliations(['Some Lab #https://ror.org/01jdz5g73'], null);

        self::assertSame([
            ['label' => 'Some Lab', 'rorId' => 'https://ror.org/01jdz5g73'],
        ], $result);
    }

    public function testProcessAffiliationsSingleEntry(): void
    {
        $result = $this->processAffiliations(['Donders Centre [DCCN] #https://ror.org/01jdz5g73']);

        self::assertSame([
            ['label' => 'Donders Centre [DCCN]', 'rorId' => 'https://ror.org/01jdz5g73'],
        ], $result);
    }

    public function testProcessAffiliationsMultipleEntriesAllDisassembled(): void
    {
        // Regression guard: with several affiliations, every entry must go through
        // disassembleAffiliation() individually — none may be left as a raw string.
        $result = $this->processAffiliations([
            'Donders Centre for Cognitive Neuroimaging [DCCN] #https://ror.org/01jdz5g73',
            'Radboud University Nijmegen  #https://ror.org/016xsfp80',
        ]);

        // assertSame already proves every entry was disassembled into a {label, rorId}
        // array — none left as a raw "Label #rorId" string — for both positions.
        self::assertSame([
            ['label' => 'Donders Centre for Cognitive Neuroimaging [DCCN]', 'rorId' => 'https://ror.org/01jdz5g73'],
            ['label' => 'Radboud University Nijmegen', 'rorId' => 'https://ror.org/016xsfp80'],
        ], $result);
    }

    public function testProcessAffiliationsWrapsScalarInputIntoArray(): void
    {
        $result = $this->processAffiliations('Single Lab #https://ror.org/01jdz5g73');

        self::assertSame([
            ['label' => 'Single Lab', 'rorId' => 'https://ror.org/01jdz5g73'],
        ], $result);
    }

    public function testProcessAffiliationsEmptyInputReturnsEmptyArray(): void
    {
        self::assertSame([], $this->processAffiliations([]));
        self::assertSame([], $this->processAffiliations(null));
    }

    public function testProcessAffiliationsSkipsEmptyEntries(): void
    {
        $result = $this->processAffiliations(['', 'Some Lab #https://ror.org/01jdz5g73', '']);

        self::assertCount(1, $result);
    }

    public function testProcessAffiliationsAssembleRoundTrip(): void
    {
        $objects = [
            ['label' => 'Lab A', 'rorId' => 'https://ror.org/01jdz5g73'],
            ['label' => 'Lab B', 'rorId' => ''],
        ];

        $assembled = $this->processAffiliations($objects, 'assemble');

        self::assertSame([
            'Lab A#https://ror.org/01jdz5g73',
            'Lab B',
        ], $assembled);

        // Round-tripping back through disassemble must restore the original objects.
        $roundTripped = $this->processAffiliations($assembled, 'disassemble');
        self::assertSame($objects, $roundTripped);
    }

    // -----------------------------------------------------------------------
    // Regression guards — createAction()/editAction() must normalize AFFILIATIONS
    // before persisting a user, or raw "Label #rorId" strings leak into
    // ADDITIONAL_PROFILE_INFORMATION (the account-creation bug this suite guards).
    // -----------------------------------------------------------------------

    public function testCreateActionNormalizesAffiliationsBeforeConstructingUser(): void
    {
        $method = $this->extractMethod('createAction');

        self::assertStringContainsString(
            "\$this->processAffiliations(\$formValues['AFFILIATIONS']",
            $method,
            'createAction must run AFFILIATIONS through processAffiliations() before building the user, '
            . 'otherwise raw form strings ("Label #rorId") get persisted instead of {label, rorId} objects'
        );

        $processPos = strpos($method, 'processAffiliations(');
        // createAction() also builds a plain `new Episciences_User()` earlier, for the
        // "create from an existing CAS account" branch — target the specific
        // construction from the submitted form values instead.
        $constructPos = strpos($method, 'new Episciences_User($formValues)');

        self::assertNotFalse($processPos);
        self::assertNotFalse($constructPos);
        self::assertLessThan(
            $constructPos,
            $processPos,
            'AFFILIATIONS must be normalized before the Episciences_User is constructed from the form values'
        );
    }

    public function testCreateActionDoesNotDefaultToAssembleOperation(): void
    {
        // Passing 'assemble' here would persist flat strings again — the bug this
        // suite guards against. Only the display pre-fill (editAction, GET path)
        // may use 'assemble'.
        $method = $this->extractMethod('createAction');

        self::assertDoesNotMatchRegularExpression(
            "/processAffiliations\\([^)]*'assemble'/",
            $method,
            "createAction must not call processAffiliations() with 'assemble' — that would persist raw strings"
        );
    }

    public function testEditActionDisassemblesAffiliationsBeforePersisting(): void
    {
        $method = $this->extractMethod('editAction');

        self::assertMatchesRegularExpression(
            "/STR_AFFILIATIONS\\s*=>\\s*\\\$this->processAffiliations\\(/",
            $method,
            'editAction must run submitted AFFILIATIONS through processAffiliations() (disassemble) '
            . 'before encoding ADDITIONAL_PROFILE_INFORMATION'
        );
    }
}
