<?php

namespace unit\library\Episciences;

use Episciences_ReviewersManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_ReviewersManager.
 *
 * DB-dependent methods (getList, getSuggestedReviewers, getUnwantedReviewers,
 * addReviewerToPool) and Zend_Form-dependent methods (acceptInvitationForm,
 * refuseInvitationForm, reviewer_answer_form) are covered via API-contract
 * and source-inspection tests only.
 *
 * Bugs documented/fixed:
 *   RM1 — getList(): $settings parameter is accepted but never used; the method
 *          always delegates to Episciences_Review::getReviewers() without filters.
 *          Documented only — changing would require a larger refactoring and
 *          aligning with callers.
 *   RM2 — addReviewerToPool(): DB exception was unhandled — the method always
 *          returned true regardless of success or failure.
 *          Fixed: wrapped in try/catch; returns false on exception.
 *   RM3 — getSuggestedReviewers() / getUnwantedReviewers(): literal string values
 *          were embedded in where() without parameterization
 *          (e.g. ->where("SETTING = 'suggestedReviewer'")).
 *          Fixed: use parameterized where('SETTING = ?', 'value').
 *
 * @covers Episciences_ReviewersManager
 */
final class Episciences_ReviewersManagerTest extends TestCase
{
    // =========================================================================
    // API contract — method signatures
    // =========================================================================

    public function testGetListAcceptsSettingsArray(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'getList');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertSame('settings', $params[0]->getName());
        self::assertTrue($params[0]->isOptional(), 'getList() $settings must be optional');
        self::assertSame([], $params[0]->getDefaultValue(), 'getList() $settings must default to []');
    }

    public function testGetSuggestedReviewersAcceptsDocid(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'getSuggestedReviewers');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertSame('docid', $params[0]->getName());
    }

    public function testGetUnwantedReviewersAcceptsDocid(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'getUnwantedReviewers');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertSame('docid', $params[0]->getName());
    }

    public function testAddReviewerToPoolReturnTypeIsBool(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');

        self::assertNotNull($method->getReturnType());
        self::assertSame('bool', $method->getReturnType()->getName());
    }

    public function testAddReviewerToPoolHasThreeParameters(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $params = $method->getParameters();

        self::assertCount(3, $params);
        self::assertSame('uid',  $params[0]->getName());
        self::assertSame('vid',  $params[1]->getName());
        self::assertSame('rvid', $params[2]->getName());
    }

    public function testAddReviewerToPoolSecondParamDefaultsToZero(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $params = $method->getParameters();

        self::assertTrue($params[1]->isOptional());
        self::assertSame(0, $params[1]->getDefaultValue());
    }

    // =========================================================================
    // Bug RM1 — getList() ignores $settings (documented, not fixed)
    // =========================================================================

    /**
     * Design issue RM1 (documented, not fixed): getList() accepts a $settings
     * parameter but passes it to nothing — it always delegates to
     * Episciences_Review::getReviewers() with no filter arguments.
     *
     * Callers that pass filters (e.g. volume id, role) silently get the full
     * reviewer list instead of the filtered one.
     *
     * This test documents the current behaviour by inspecting the source to
     * confirm the dead parameter, without breaking the existing API.
     */
    public function testGetListSourceIgnoresSettingsParameter(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getList');
        $source     = $this->getMethodSource($reflection);

        // The $settings variable is never used in the method body
        self::assertStringNotContainsString(
            '$settings',
            // Remove the parameter declaration line so we only check the body
            preg_replace('/function\s+getList[^{]*\{/', '', $source),
            'Design issue RM1: $settings is declared but never used inside getList() — filters are silently ignored'
        );

        // Always delegates to getReviewers() with no arguments
        self::assertStringContainsString(
            'getReviewers()',
            $source,
            'getList() must delegate to Episciences_Review::getReviewers()'
        );
    }

    // =========================================================================
    // Bug RM2 — addReviewerToPool() exception handling
    // =========================================================================

    /**
     * Fix RM2: the original method had no try/catch — any DB exception propagated
     * uncaught and the declared ": bool" return type was never false.
     * Fixed: wrapped in try/catch; logs the exception and returns false on failure.
     */
    public function testAddReviewerToPoolSourceHasTryCatch(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            'try {',
            $source,
            'Fix RM2: addReviewerToPool() must have a try block to catch DB exceptions'
        );

        self::assertStringContainsString(
            'catch (',
            $source,
            'Fix RM2: addReviewerToPool() must have a catch block'
        );

        self::assertStringContainsString(
            'return false;',
            $source,
            'Fix RM2: addReviewerToPool() must return false when an exception is caught'
        );

        self::assertStringContainsString(
            'return true;',
            $source,
            'Fix RM2: addReviewerToPool() must return true on success'
        );
    }

    public function testAddReviewerToPoolSourceLogsException(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            'error_log(',
            $source,
            'Fix RM2: addReviewerToPool() must log the exception message before returning false'
        );
    }

    // =========================================================================
    // Bug RM3 — getSuggestedReviewers() / getUnwantedReviewers() parameterization
    // =========================================================================

    /**
     * Fix RM3: the original where() calls embedded the setting name as a raw
     * string literal in the SQL fragment, bypassing Zend_Db quoting:
     *   ->where("SETTING = 'suggestedReviewer'")
     *   ->where("SETTING = 'unwantedReviewer'")
     *
     * Fixed: use the parameterized form:
     *   ->where('SETTING = ?', 'suggestedReviewer')
     *   ->where('SETTING = ?', 'unwantedReviewer')
     */
    public function testGetSuggestedReviewersSourceUsesParameterizedWhere(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getSuggestedReviewers');
        $source     = $this->getMethodSource($reflection);

        // Old pattern: literal string embedded in the where clause
        self::assertStringNotContainsString(
            "'SETTING = \\'suggestedReviewer\\''",
            $source,
            'Fix RM3: where() must not embed the setting name as a raw string literal'
        );

        // New pattern: parameterized placeholder
        self::assertStringContainsString(
            "where('SETTING = ?', 'suggestedReviewer')",
            $source,
            'Fix RM3: getSuggestedReviewers() must use parameterized where() for SETTING'
        );
    }

    public function testGetUnwantedReviewersSourceUsesParameterizedWhere(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getUnwantedReviewers');
        $source     = $this->getMethodSource($reflection);

        // Old pattern: literal string embedded in the where clause
        self::assertStringNotContainsString(
            "'SETTING = \\'unwantedReviewer\\''",
            $source,
            'Fix RM3: where() must not embed the setting name as a raw string literal'
        );

        // New pattern: parameterized placeholder
        self::assertStringContainsString(
            "where('SETTING = ?', 'unwantedReviewer')",
            $source,
            'Fix RM3: getUnwantedReviewers() must use parameterized where() for SETTING'
        );
    }

    public function testGetSuggestedReviewersAlsoParameterizesDocid(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getSuggestedReviewers');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            "where('DOCID = ?', \$docid)",
            $source,
            'getSuggestedReviewers() must use parameterized where() for DOCID'
        );
    }

    public function testGetUnwantedReviewersAlsoParameterizesDocid(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getUnwantedReviewers');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            "where('DOCID = ?', \$docid)",
            $source,
            'getUnwantedReviewers() must use parameterized where() for DOCID'
        );
    }

    // =========================================================================
    // refuseInvitationForm - input filtering and validation
    // =========================================================================

    public function testRefuseInvitationFormStripsTagsAndTrimsValues(): void
    {
        $form = $this->buildRefuseForm();

        $this->assertTrue($form->isValid([
            'suggestreviewer' => '  <b>Jane</b> Doe ',
            'comment' => '<script>alert(1)</script>No time',
        ]));

        $values = $form->getValues();
        $this->assertSame('Jane Doe', $values['suggestreviewer']);
        $this->assertStringNotContainsString('<', $values['comment']);
    }

    public function testRefuseInvitationFormRejectsOversizedValues(): void
    {
        $this->assertFalse($this->buildRefuseForm()->isValid([
            'suggestreviewer' => str_repeat('a', 256),
            'comment' => 'ok',
        ]));
        $this->assertFalse($this->buildRefuseForm()->isValid([
            'suggestreviewer' => 'ok',
            'comment' => str_repeat('a', 5001),
        ]));
    }

    public function testRefuseInvitationFormIsNamedByTheHeadingOfItsPanel(): void
    {
        $form = $this->renderedRefuseForm($this->buildRefuseForm())->query('//form')->item(0);

        self::assertSame(Episciences_ReviewersManager::REFUSE_FORM_TITLE_ID, $form?->getAttribute('aria-labelledby'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusalFieldProvider(): array
    {
        return [
            'suggested reviewer' => ['suggestreviewer', '255'],
            'refusal reason' => ['comment', '5000'],
        ];
    }

    /**
     * @dataProvider refusalFieldProvider
     */
    public function testRefuseInvitationFieldIsLimitedAndDescribedByItsHelpText(string $name, string $maxLength): void
    {
        $xpath = $this->renderedRefuseForm($this->buildRefuseForm());
        $field = $xpath->query(sprintf('//*[@name="%s"]', $name))->item(0);

        self::assertInstanceOf(\DOMElement::class, $field);
        self::assertSame($maxLength, $field->getAttribute('maxlength'), 'the browser stops at the limit the server validates');
        self::assertSame($name . '-description', $field->getAttribute('aria-describedby'));
        self::assertSame(1, $xpath->query(sprintf('//p[@id="%s-description"][@class="help-block"]', $name))->length);
        self::assertFalse($field->hasAttribute('placeholder'), 'the help text replaces the low-contrast placeholder');
        self::assertFalse($field->hasAttribute('style'), 'the width comes from the stylesheet');
        self::assertFalse($field->hasAttribute('aria-invalid'));
    }

    public function testRefuseInvitationFormConfirmIsThePrimaryActionAndCancelTheSecondary(): void
    {
        $xpath = $this->renderedRefuseForm($this->buildRefuseForm());

        $confirm = $xpath->query('//div[@class="form-actions text-center"]/button[@name="submitrefuse"]')->item(0);
        $cancel = $xpath->query('//div[@class="form-actions text-center"]/button[@name="cancelaccept"]')->item(0);

        self::assertInstanceOf(\DOMElement::class, $confirm);
        self::assertSame('submit', $confirm->getAttribute('type'));
        self::assertSame('btn btn-primary', $confirm->getAttribute('class'), 'the action of this step, not shown as a danger');
        self::assertInstanceOf(\DOMElement::class, $cancel);
        self::assertSame('button', $cancel->getAttribute('type'));
        self::assertSame('btn btn-default', $cancel->getAttribute('class'));
        self::assertTrue($cancel->hasAttribute('data-answer-cancel'));
        self::assertFalse($cancel->hasAttribute('onclick'));
    }

    public function testRefusalFormErrorsAreLinkedToTheInvalidFieldOnly(): void
    {
        $form = $this->buildRefuseForm();
        self::assertFalse($form->isValid(['suggestreviewer' => 'Jane Doe', 'comment' => str_repeat('a', 5001)]));

        Episciences_ReviewersManager::describeRefusalFormErrors($form);
        $xpath = $this->renderedRefuseForm($form);

        $comment = $xpath->query('//textarea[@name="comment"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $comment);
        self::assertSame('true', $comment->getAttribute('aria-invalid'));
        self::assertSame('comment-errors comment-description', $comment->getAttribute('aria-describedby'));
        $errors = $xpath->query('//ul[@id="comment-errors"]');
        self::assertSame(1, $errors->length, 'the error list the field refers to');
        self::assertSame(
            'Ce texte ne doit pas dépasser 5000 caractères.',
            trim((string) $errors->item(0)?->textContent),
            'the message does not repeat the value: a screen reader would read it out in full'
        );

        $suggested = $xpath->query('//input[@name="suggestreviewer"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $suggested);
        self::assertFalse($suggested->hasAttribute('aria-invalid'));
        self::assertSame('suggestreviewer-description', $suggested->getAttribute('aria-describedby'));
    }

    private function renderedRefuseForm(\Zend_Form $form): \DOMXPath
    {
        $form->setView(new \Zend_View());
        $html = $form->render();

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new \DOMXPath($document);
    }

    private function buildRefuseForm(): \Zend_Form
    {
        $hadTranslate = \Zend_Registry::isRegistered('Zend_Translate');
        $previous = $hadTranslate ? \Zend_Registry::get('Zend_Translate') : null;
        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'en']));

        try {
            return Episciences_ReviewersManager::refuseInvitationForm();
        } finally {
            if ($hadTranslate) {
                \Zend_Registry::set('Zend_Translate', $previous);
            }
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getMethodSource(ReflectionMethod $method): string
    {
        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
