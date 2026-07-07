<?php

namespace unit\library\Episciences;

use Episciences_Paper_Logger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_Translate;

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

    // -------------------------------------------------------------------------
    // Activity timeline categories — getCategory()/$_category
    // -------------------------------------------------------------------------

    private const VALID_CATEGORIES = [
        Episciences_Paper_Logger::CATEGORY_SUBMISSION,
        Episciences_Paper_Logger::CATEGORY_EDITORIAL,
        Episciences_Paper_Logger::CATEGORY_REVIEW,
        Episciences_Paper_Logger::CATEGORY_COMMUNICATION,
    ];

    /**
     * Every CODE_ constant must be present as a key in $_category and map to one
     * of the four known CATEGORY_* constants — otherwise ActivityController's
     * timeline filters silently mis-file that action (see getCategory()'s
     * CATEGORY_EDITORIAL fallback for actions absent from this map).
     */
    public function testEveryCodeConstantHasACategoryEntry(): void
    {
        foreach (Episciences_Paper_Logger::getLogTypes() as $constantName => $codeValue) {
            self::assertArrayHasKey(
                $codeValue,
                Episciences_Paper_Logger::$_category,
                "Missing \$_category entry for constant $constantName ('$codeValue')"
            );

            self::assertContains(
                Episciences_Paper_Logger::$_category[$codeValue],
                self::VALID_CATEGORIES,
                "Invalid category value for constant $constantName ('$codeValue')"
            );
        }
    }

    public function testGetCategoryReturnsMappedCategory(): void
    {
        self::assertSame(
            Episciences_Paper_Logger::CATEGORY_REVIEW,
            Episciences_Paper_Logger::getCategory(Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT)
        );

        self::assertSame(
            Episciences_Paper_Logger::CATEGORY_COMMUNICATION,
            Episciences_Paper_Logger::getCategory(Episciences_Paper_Logger::CODE_MAIL_SENT)
        );

        self::assertSame(
            Episciences_Paper_Logger::CATEGORY_SUBMISSION,
            Episciences_Paper_Logger::getCategory(Episciences_Paper_Logger::CODE_DOCUMENT_IMPORTED)
        );
    }

    public function testGetCategoryFallsBackToEditorialForUnknownAction(): void
    {
        self::assertSame(
            Episciences_Paper_Logger::CATEGORY_EDITORIAL,
            Episciences_Paper_Logger::getCategory('some_future_action_not_yet_categorized')
        );
    }

    // -------------------------------------------------------------------------
    // getCategoryLabel() / getCategoryIcon()
    // -------------------------------------------------------------------------

    public function testGetCategoryLabelReturnsKnownLabels(): void
    {
        foreach (self::VALID_CATEGORIES as $category) {
            self::assertArrayHasKey($category, Episciences_Paper_Logger::$_categoryLabel);
            self::assertSame(
                Episciences_Paper_Logger::$_categoryLabel[$category],
                Episciences_Paper_Logger::getCategoryLabel($category)
            );
        }
    }

    public function testGetCategoryLabelFallsBackToRawCategoryForUnknownCategory(): void
    {
        self::assertSame('some_unknown_category', Episciences_Paper_Logger::getCategoryLabel('some_unknown_category'));
    }

    public function testGetCategoryIconReturnsKnownIcons(): void
    {
        foreach (self::VALID_CATEGORIES as $category) {
            self::assertArrayHasKey($category, Episciences_Paper_Logger::$_categoryIcon);
            self::assertSame(
                Episciences_Paper_Logger::$_categoryIcon[$category],
                Episciences_Paper_Logger::getCategoryIcon($category)
            );
        }
    }

    public function testGetCategoryIconFallsBackToGlyphiconRecordForUnknownCategory(): void
    {
        self::assertSame('glyphicon-record', Episciences_Paper_Logger::getCategoryIcon('some_unknown_category'));
    }

    // -------------------------------------------------------------------------
    // getActionIcon() / getActionIconClasses()
    // -------------------------------------------------------------------------

    public function testGetActionIconFallsBackToGlyphiconRecordForUnknownAction(): void
    {
        self::assertSame('glyphicon-record', Episciences_Paper_Logger::getActionIcon('some_future_action'));
    }

    public function testGetActionIconClassesPrependsGlyphiconBaseClass(): void
    {
        self::assertSame(
            'glyphicon glyphicon-log-in',
            Episciences_Paper_Logger::getActionIconClasses(Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT)
        );
    }

    public function testGetActionIconClassesUsesFontAwesomeClassesAsIs(): void
    {
        self::assertSame(
            'fa-regular fa-flag',
            Episciences_Paper_Logger::getActionIconClasses(Episciences_Paper_Logger::CODE_STATUS)
        );
    }

    public function testGetActionIconClassesFallsBackToGlyphiconRecordForUnknownAction(): void
    {
        self::assertSame('glyphicon glyphicon-record', Episciences_Paper_Logger::getActionIconClasses('some_future_action'));
    }

    // -------------------------------------------------------------------------
    // hasDetailModal()
    // -------------------------------------------------------------------------

    public function testHasDetailModalReturnsTrueForListedActions(): void
    {
        self::assertTrue(Episciences_Paper_Logger::hasDetailModal(Episciences_Paper_Logger::CODE_MAIL_SENT));
        self::assertTrue(Episciences_Paper_Logger::hasDetailModal(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION));
    }

    public function testHasDetailModalReturnsFalseForUnlistedActions(): void
    {
        self::assertFalse(Episciences_Paper_Logger::hasDetailModal(Episciences_Paper_Logger::CODE_STATUS));
        self::assertFalse(Episciences_Paper_Logger::hasDetailModal('some_future_action'));
    }

    // -------------------------------------------------------------------------
    // extractLogDisplayData()
    // -------------------------------------------------------------------------

    /** Snapshot of whatever was registered under 'Zend_Translate', restored in tearDown(). */
    private bool $hadTranslate = false;
    private mixed $savedTranslate = null;

    private function removeRegisteredTranslator(): void
    {
        $registry = Zend_Registry::getInstance();
        $this->hadTranslate = $registry->offsetExists('Zend_Translate');

        if ($this->hadTranslate) {
            $this->savedTranslate = $registry->offsetGet('Zend_Translate');
            $registry->offsetUnset('Zend_Translate');
        }
    }

    protected function tearDown(): void
    {
        if ($this->hadTranslate) {
            Zend_Registry::set('Zend_Translate', $this->savedTranslate);
            $this->hadTranslate = false;
        }
    }

    public function testExtractLogDisplayDataDecodesValidJsonDetail(): void
    {
        $log = [
            'ACTION' => Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT,
            'DETAIL' => json_encode(['user' => ['fullname' => 'Marie Dupont']]),
        ];

        $data = Episciences_Paper_Logger::extractLogDisplayData($log);

        self::assertSame(['user' => ['fullname' => 'Marie Dupont']], $data['detail']);
        self::assertSame('Marie Dupont', $data['fullName']);
    }

    public function testExtractLogDisplayDataHandlesEmptyDetail(): void
    {
        $data = Episciences_Paper_Logger::extractLogDisplayData(['ACTION' => Episciences_Paper_Logger::CODE_STATUS, 'DETAIL' => '']);

        self::assertSame([], $data['detail']);
        self::assertSame('undefined', $data['fullName']);
    }

    public function testExtractLogDisplayDataHandlesMalformedJsonWithoutThrowing(): void
    {
        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_STATUS,
            'DETAIL' => '{not valid json',
        ]);

        self::assertSame([], $data['detail']);
    }

    public function testExtractLogDisplayDataFallsBackToScreenNameWhenFullnameMissing(): void
    {
        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT,
            'DETAIL' => json_encode(['user' => ['SCREEN_NAME' => 'mdupont']]),
        ]);

        self::assertSame('mdupont', $data['fullName']);
    }

    public function testExtractLogDisplayDataDefaultsFullNameToUndefinedWhenMissing(): void
    {
        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_STATUS,
            'DETAIL' => json_encode(['status' => 1]),
        ]);

        self::assertSame('undefined', $data['fullName']);
    }

    public function testExtractLogDisplayDataExtractsTagWhenPresent(): void
    {
        $this->removeRegisteredTranslator();

        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT,
            'DETAIL' => json_encode(['user' => ['fullname' => 'Marie Dupont', 'tag' => 'editor']]),
        ]);

        self::assertSame(' [ editor ]', $data['tag']);
    }

    public function testExtractLogDisplayDataOmitsTagWhenAbsent(): void
    {
        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT,
            'DETAIL' => json_encode(['user' => ['fullname' => 'Marie Dupont']]),
        ]);

        self::assertSame('', $data['tag']);
    }

    public function testExtractLogDisplayDataResolvesKnownStatusCode(): void
    {
        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_STATUS,
            'DETAIL' => json_encode(['status' => \Episciences_Paper::STATUS_PUBLISHED]),
        ]);

        self::assertSame(\Episciences_Paper::STATUS_PUBLISHED, $data['statusCode']);
    }

    public function testExtractLogDisplayDataStatusCodeNullForMissingOrInvalidStatus(): void
    {
        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_STATUS,
            'DETAIL' => json_encode(['status' => 999999]),
        ]);

        self::assertNull($data['statusCode']);
        self::assertSame('undefined status', $data['status']);
    }

    /**
     * Guards the fix this PR made: extractLogDisplayData() must degrade to the raw
     * message id (like the Zend_View_Helper_Translate it replaces) instead of throwing
     * when no translator is bootstrapped in the registry — the normal state for console
     * scripts and PHPUnit tests.
     */
    public function testExtractLogDisplayDataDegradesGracefullyWithoutRegisteredTranslator(): void
    {
        $this->removeRegisteredTranslator();

        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_STATUS,
            'DETAIL' => json_encode(['status' => 999999]),
        ]);

        self::assertSame('undefined status', $data['status']);
    }

    public function testExtractLogDisplayDataUsesRegisteredTranslatorWhenPresent(): void
    {
        $this->removeRegisteredTranslator();

        Zend_Registry::set('Zend_Translate', new Zend_Translate([
            'adapter' => 'array',
            'content' => ['undefined status' => 'statut inconnu'],
            'locale' => 'fr',
        ]));

        $data = Episciences_Paper_Logger::extractLogDisplayData([
            'ACTION' => Episciences_Paper_Logger::CODE_STATUS,
            'DETAIL' => json_encode(['status' => 999999]),
        ]);

        self::assertSame('statut inconnu', $data['status']);

        Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
    }
}