<?php

namespace unit\library\Episciences;

use Episciences_Paper_Logger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_LoggerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // CSS class constants
    // -------------------------------------------------------------------------

    public function testCssClassConstantsHaveExpectedValues(): void
    {
        self::assertSame('warning', Episciences_Paper_Logger::WARNING);
        self::assertSame('info', Episciences_Paper_Logger::INFO);
        self::assertSame('violet', Episciences_Paper_Logger::VIOLET);
        self::assertSame('success', Episciences_Paper_Logger::SUCCESS);
        self::assertSame('danger', Episciences_Paper_Logger::DANGER);
        self::assertSame('primary', Episciences_Paper_Logger::PRIMARY);
    }

    // -------------------------------------------------------------------------
    // $_css completeness — every CODE_ constant must have a CSS entry
    // -------------------------------------------------------------------------

    /**
     * Every CODE_ constant must be present as a key in $_css and map to a valid
     * CSS class constant. Detects missing mappings when new constants are added.
     */
    public function testEveryCodeConstantHasACssEntry(): void
    {
        $validCssClasses = [
            Episciences_Paper_Logger::WARNING,
            Episciences_Paper_Logger::INFO,
            Episciences_Paper_Logger::VIOLET,
            Episciences_Paper_Logger::SUCCESS,
            Episciences_Paper_Logger::DANGER,
            Episciences_Paper_Logger::PRIMARY,
        ];

        foreach (Episciences_Paper_Logger::getLogTypes() as $constantName => $codeValue) {
            self::assertArrayHasKey(
                $codeValue,
                Episciences_Paper_Logger::$_css,
                "Missing \$_css entry for constant $constantName ('$codeValue')"
            );

            self::assertContains(
                Episciences_Paper_Logger::$_css[$codeValue],
                $validCssClasses,
                "Invalid CSS class value for constant $constantName ('$codeValue')"
            );
        }
    }

    // -------------------------------------------------------------------------
    // $_label completeness — every CODE_ constant must have a label entry
    // -------------------------------------------------------------------------

    /**
     * Every CODE_ constant must be present as a key in $_label.
     */
    public function testEveryCodeConstantHasALabelEntry(): void
    {
        foreach (Episciences_Paper_Logger::getLogTypes() as $constantName => $codeValue) {
            self::assertArrayHasKey(
                $codeValue,
                Episciences_Paper_Logger::$_label,
                "Missing \$_label entry for constant $constantName ('$codeValue')"
            );
        }
    }

    public function testMonitoringRefusedLabelSpellingIsCorrect(): void
    {
        self::assertArrayHasKey(
            Episciences_Paper_Logger::CODE_MONITORING_REFUSED,
            Episciences_Paper_Logger::$_label
        );

        self::assertStringContainsString(
            'article',
            Episciences_Paper_Logger::$_label[Episciences_Paper_Logger::CODE_MONITORING_REFUSED]
        );
    }

    // -------------------------------------------------------------------------
    // Constant values
    // -------------------------------------------------------------------------

    /**
     * CODE_LD_REMOVED is intentionally stored as 'ld_remove' (no trailing 'd')
     * in the database. The value must not be changed without a DB migration.
     */
    public function testCodeLdRemovedConstantValueMatchesLegacyDbValue(): void
    {
        self::assertSame('ld_remove', Episciences_Paper_Logger::CODE_LD_REMOVED);
    }

    /**
     * Spot-check a selection of code constant string values to guard against
     * accidental renames that would break stored log records in the database.
     */
    public function testSelectedCodeConstantValuesAreStable(): void
    {
        self::assertSame('status', Episciences_Paper_Logger::CODE_STATUS);
        self::assertSame('mail_sent', Episciences_Paper_Logger::CODE_MAIL_SENT);
        self::assertSame('editor_assignment', Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT);
        self::assertSame('reviewer_invitation', Episciences_Paper_Logger::CODE_REVIEWER_INVITATION);
        self::assertSame('doi_assigned', Episciences_Paper_Logger::CODE_DOI_ASSIGNED);
        self::assertSame('doi_updated', Episciences_Paper_Logger::CODE_DOI_UPDATED);
        self::assertSame('doi_canceled', Episciences_Paper_Logger::CODE_DOI_CANCELED);
        self::assertSame('paper_updated', Episciences_Paper_Logger::CODE_PAPER_UPDATED);
        self::assertSame('paper_imported', Episciences_Paper_Logger::CODE_DOCUMENT_IMPORTED);
        self::assertSame('ld_remove', Episciences_Paper_Logger::CODE_LD_REMOVED);
        self::assertSame('revision_deadline_updated', Episciences_Paper_Logger::CODE_REVISION_DEADLINE_UPDATED);
    }

    // -------------------------------------------------------------------------
    // getLogTypes()
    // -------------------------------------------------------------------------

    public function testGetLogTypesReturnsArray(): void
    {
        self::assertIsArray(Episciences_Paper_Logger::getLogTypes());
    }

    public function testGetLogTypesIsNotEmpty(): void
    {
        self::assertNotEmpty(Episciences_Paper_Logger::getLogTypes());
    }

    public function testGetLogTypesReturnsOnlyCodePrefixedKeys(): void
    {
        foreach (array_keys(Episciences_Paper_Logger::getLogTypes()) as $constantName) {
            self::assertStringStartsWith(
                'CODE_',
                $constantName,
                "getLogTypes() returned a non-CODE_ key: $constantName"
            );
        }
    }

    public function testGetLogTypesExcludesCssAndAlertConstants(): void
    {
        $logTypes = Episciences_Paper_Logger::getLogTypes();

        self::assertArrayNotHasKey('WARNING', $logTypes);
        self::assertArrayNotHasKey('INFO', $logTypes);
        self::assertArrayNotHasKey('VIOLET', $logTypes);
        self::assertArrayNotHasKey('SUCCESS', $logTypes);
        self::assertArrayNotHasKey('DANGER', $logTypes);
        self::assertArrayNotHasKey('PRIMARY', $logTypes);
    }

    public function testGetLogTypesValuesAreAllStrings(): void
    {
        foreach (Episciences_Paper_Logger::getLogTypes() as $value) {
            self::assertIsString($value);
        }
    }

    public function testGetLogTypesValueMatchesConstant(): void
    {
        $logTypes = Episciences_Paper_Logger::getLogTypes();

        self::assertArrayHasKey('CODE_STATUS', $logTypes);
        self::assertSame(Episciences_Paper_Logger::CODE_STATUS, $logTypes['CODE_STATUS']);

        self::assertArrayHasKey('CODE_MAIL_SENT', $logTypes);
        self::assertSame(Episciences_Paper_Logger::CODE_MAIL_SENT, $logTypes['CODE_MAIL_SENT']);

        self::assertArrayHasKey('CODE_DOI_ASSIGNED', $logTypes);
        self::assertSame(Episciences_Paper_Logger::CODE_DOI_ASSIGNED, $logTypes['CODE_DOI_ASSIGNED']);
    }

    // -------------------------------------------------------------------------
    // getLogTypes() — static cache
    // -------------------------------------------------------------------------

    public function testGetLogTypesReturnsSameResultOnMultipleCalls(): void
    {
        $first  = Episciences_Paper_Logger::getLogTypes();
        $second = Episciences_Paper_Logger::getLogTypes();

        self::assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // log() — action validation (no DB required: exception thrown before DB access)
    // -------------------------------------------------------------------------

    public function testLogThrowsOnUnknownAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unknown_action_xyz/');

        Episciences_Paper_Logger::log(1, 1, 'unknown_action_xyz');
    }

    public function testLogThrowsOnEmptyAction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Episciences_Paper_Logger::log(1, 1, '');
    }

    /**
     * The number of CODE_ constants must equal the number of entries in $_css
     * and $_label, ensuring neither array drifts ahead of the others.
     */
    public function testCssAndLabelCountsMatchCodeConstantCount(): void
    {
        $codeCount  = count(Episciences_Paper_Logger::getLogTypes());
        $cssCount   = count(Episciences_Paper_Logger::$_css);
        $labelCount = count(Episciences_Paper_Logger::$_label);

        self::assertSame(
            $codeCount,
            $cssCount,
            "Count mismatch: $codeCount CODE_ constants but $cssCount entries in \$_css"
        );

        self::assertSame(
            $codeCount,
            $labelCount,
            "Count mismatch: $codeCount CODE_ constants but $labelCount entries in \$_label"
        );
    }

    // -------------------------------------------------------------------------
    // updateUid() — guard conditions (no DB required)
    // -------------------------------------------------------------------------

    public function testUpdateUidReturnsZeroWhenOldUidIsZero(): void
    {
        self::assertSame(0, Episciences_Paper_Logger::updateUid(0, 42));
    }

    public function testUpdateUidReturnsZeroWhenNewUidIsZero(): void
    {
        self::assertSame(0, Episciences_Paper_Logger::updateUid(42, 0));
    }

    public function testUpdateUidReturnsZeroWhenBothUidsAreZero(): void
    {
        self::assertSame(0, Episciences_Paper_Logger::updateUid(0, 0));
    }

    public function testUpdateUidReturnsZeroWithDefaultArguments(): void
    {
        self::assertSame(0, Episciences_Paper_Logger::updateUid());
    }
}