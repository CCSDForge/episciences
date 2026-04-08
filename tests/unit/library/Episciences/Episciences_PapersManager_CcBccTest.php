<?php

namespace unit\library\Episciences;

use Ccsd_Form;
use Episciences_PapersManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for the CC/BCC tag-UI helpers introduced in PapersManager.
 *
 * Both methods are private static — accessed via ReflectionMethod.
 * Neither requires a database connection.
 *
 * Bugs discovered during review are documented inline.
 *
 * @covers Episciences_PapersManager
 */
final class Episciences_PapersManager_CcBccTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Reflection helpers
    // -----------------------------------------------------------------------

    private function callRecipientJson(string $input): string
    {
        $m = new ReflectionMethod(Episciences_PapersManager::class, 'recipientHiddenJsonFromSemicolonString');
        $m->setAccessible(true);
        return (string) $m->invoke(null, $input);
    }

    /** @param Ccsd_Form|\Zend_Form_SubForm $form */
    private function callAddCcBcc(
        $form,
        string $formId,
        string $cc,
        string $bcc,
        ?Ccsd_Form $hiddenTarget = null
    ): void {
        $m = new ReflectionMethod(Episciences_PapersManager::class, 'addMailModalCcBccWithTags');
        $m->setAccessible(true);
        $m->invoke(null, $form, $formId, $cc, $bcc, $hiddenTarget);
    }

    private function makeFormWithCcBcc(string $id, bool $withCc = true, bool $withBcc = true): Ccsd_Form
    {
        $form = new Ccsd_Form();
        if ($withCc) {
            $form->addElement('text', 'cc', ['id' => $id . '-cc', 'value' => 'existing-cc']);
        }
        if ($withBcc) {
            $form->addElement('text', 'bcc', ['id' => $id . '-bcc', 'value' => 'existing-bcc']);
        }
        return $form;
    }

    // -----------------------------------------------------------------------
    // recipientHiddenJsonFromSemicolonString — basic output
    // -----------------------------------------------------------------------

    public function testEmptyStringReturnsEmptyJsonArray(): void
    {
        self::assertSame('[]', $this->callRecipientJson(''));
    }

    public function testWhitespaceOnlyReturnsEmptyJsonArray(): void
    {
        self::assertSame('[]', $this->callRecipientJson('   '));
    }

    public function testSingleEmailProducesOneEntry(): void
    {
        $rows = json_decode($this->callRecipientJson('alice@example.com'), true);

        self::assertCount(1, $rows);
        self::assertSame('alice@example.com', $rows[0]['value']);
        self::assertSame('paper-modal-init-0', $rows[0]['key']);
        self::assertNull($rows[0]['uid']);
    }

    public function testTwoEmailsSemicolonSeparated(): void
    {
        $rows = json_decode($this->callRecipientJson('alice@example.com;bob@example.com'), true);

        self::assertCount(2, $rows);
        self::assertSame('alice@example.com', $rows[0]['value']);
        self::assertSame('bob@example.com', $rows[1]['value']);
    }

    public function testEmailsAreTrimmedBeforeEncoding(): void
    {
        $rows = json_decode($this->callRecipientJson('  alice@example.com ;  bob@example.com  '), true);

        self::assertCount(2, $rows);
        self::assertSame('alice@example.com', $rows[0]['value']);
        self::assertSame('bob@example.com', $rows[1]['value']);
    }

    public function testTrailingSemicolonProducesNoExtraEntry(): void
    {
        $rows = json_decode($this->callRecipientJson('alice@example.com;'), true);
        self::assertCount(1, $rows);
    }

    public function testOutputIsValidJson(): void
    {
        $json = $this->callRecipientJson('a@b.com;c@d.com');
        json_decode($json);
        self::assertSame(JSON_ERROR_NONE, json_last_error(), 'Output must be valid JSON');
    }

    public function testUnicodeIsNotEscapedInOutput(): void
    {
        $rows = json_decode($this->callRecipientJson('tëst@example.com'), true);
        self::assertSame('tëst@example.com', $rows[0]['value']);
    }

    // -----------------------------------------------------------------------
    // recipientHiddenJsonFromSemicolonString — bugs found
    // -----------------------------------------------------------------------

    /**
     * Fixed (BUG-1 + BUG-2): array_values() re-indexes after array_filter() so keys
     * are always sequential (0, 1, 2…) even when consecutive separators are present.
     * The dead `if ($p === '') continue;` guard was removed at the same time.
     */
    public function testConsecutiveSemicolonsProduceSequentialKeys(): void
    {
        $rows = json_decode($this->callRecipientJson('alice@example.com;;bob@example.com'), true);

        self::assertCount(2, $rows);
        self::assertSame('alice@example.com', $rows[0]['value']);
        self::assertSame('bob@example.com', $rows[1]['value']);

        // Keys must be sequential after the fix
        self::assertSame('paper-modal-init-0', $rows[0]['key']);
        self::assertSame('paper-modal-init-1', $rows[1]['key']);
    }

    /**
     * Fixed (BUG-2): the dead `if ($p === '') continue;` guard has been removed.
     * array_values(array_filter(...)) is now the sole empty-string filter.
     */
    public function testDeadCodeGuardHasBeenRemoved(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/library/Episciences/PapersManager.php'
        );
        $start = strpos($source, 'function recipientHiddenJsonFromSemicolonString');
        $end   = strpos($source, 'function applyRecipientTagDecorators');
        $body  = substr($source, (int) $start, (int) $end - (int) $start);

        self::assertStringContainsString('array_values', $body,
            'array_values() must wrap array_filter() to guarantee sequential indices'
        );
        self::assertStringNotContainsString("if (\$p === '') {", $body,
            'The dead-code guard must be absent — array_values(array_filter()) handles empty strings'
        );
    }

    // -----------------------------------------------------------------------
    // addMailModalCcBccWithTags — normal behaviour
    // -----------------------------------------------------------------------

    public function testAddsHiddenCcAndBccToForm(): void
    {
        $form = $this->makeFormWithCcBcc('my-form');
        $this->callAddCcBcc($form, 'my-form', '', '');

        self::assertNotNull($form->getElement('hidden_cc'), 'hidden_cc must be created');
        self::assertNotNull($form->getElement('hidden_bcc'), 'hidden_bcc must be created');
    }

    public function testHiddenElementIdContainsFormIdPrefix(): void
    {
        $form = $this->makeFormWithCcBcc('acceptance-form');
        $this->callAddCcBcc($form, 'acceptance-form', '', '');

        self::assertSame('acceptance-form-hidden_cc', $form->getElement('hidden_cc')->getId());
        self::assertSame('acceptance-form-hidden_bcc', $form->getElement('hidden_bcc')->getId());
    }

    public function testEmptyDefaultsProduceEmptyJsonInHiddenField(): void
    {
        $form = $this->makeFormWithCcBcc('my-form');
        $this->callAddCcBcc($form, 'my-form', '', '');

        self::assertSame('[]', $form->getElement('hidden_cc')->getValue());
        self::assertSame('[]', $form->getElement('hidden_bcc')->getValue());
    }

    public function testSemicolonDefaultsAreEncodedInHiddenCc(): void
    {
        $form = $this->makeFormWithCcBcc('my-form');
        $this->callAddCcBcc($form, 'my-form', 'alice@example.com;bob@example.com', '');

        $rows = json_decode($form->getElement('hidden_cc')->getValue(), true);
        self::assertCount(2, $rows);
        self::assertSame('alice@example.com', $rows[0]['value']);
        self::assertSame('bob@example.com', $rows[1]['value']);
    }

    public function testTextInputValueIsClearedAfterSetup(): void
    {
        $form = $this->makeFormWithCcBcc('my-form');
        // cc and bcc text elements had 'existing-*' values before the call
        $this->callAddCcBcc($form, 'my-form', '', '');

        self::assertSame('', $form->getElement('cc')->getValue(),
            'cc text input must be cleared — value is now managed by the hidden JSON field'
        );
        self::assertSame('', $form->getElement('bcc')->getValue(),
            'bcc text input must be cleared — value is now managed by the hidden JSON field'
        );
    }

    public function testTagSpanDecoratorIsAddedToCcElement(): void
    {
        $form = $this->makeFormWithCcBcc('my-form');
        $this->callAddCcBcc($form, 'my-form', '', '');

        $decoratorKeys = array_keys($form->getElement('cc')->getDecorators());
        $hasHtmlTag = false;
        foreach ($decoratorKeys as $key) {
            if (stripos($key, 'HtmlTag') !== false) {
                $hasHtmlTag = true;
                break;
            }
        }
        self::assertTrue($hasHtmlTag, 'An HtmlTag decorator (span container) must be added to the cc element');
    }

    public function testSkipsCcWhenElementMissing(): void
    {
        $form = $this->makeFormWithCcBcc('my-form', withCc: false, withBcc: true);
        $this->callAddCcBcc($form, 'my-form', '', '');

        self::assertNull($form->getElement('hidden_cc'),
            'hidden_cc must not be added when the cc text element is absent'
        );
        self::assertNotNull($form->getElement('hidden_bcc'));
    }

    public function testSkipsBccWhenElementMissing(): void
    {
        $form = $this->makeFormWithCcBcc('my-form', withCc: true, withBcc: false);
        $this->callAddCcBcc($form, 'my-form', '', '');

        self::assertNotNull($form->getElement('hidden_cc'));
        self::assertNull($form->getElement('hidden_bcc'),
            'hidden_bcc must not be added when the bcc text element is absent'
        );
    }

    public function testHiddenFieldsGoToSeparateTargetForm(): void
    {
        $subForm  = $this->makeFormWithCcBcc('ask-other-editors-form');
        $mainForm = new Ccsd_Form();

        $this->callAddCcBcc($subForm, 'ask-other-editors-form', '', '', $mainForm);

        // Hidden fields must be on the parent form, not on the subform
        self::assertNotNull($mainForm->getElement('hidden_cc'));
        self::assertNotNull($mainForm->getElement('hidden_bcc'));
        self::assertNull($subForm->getElement('hidden_cc'),
            'hidden_cc must not appear on the subform when a separate target form is passed'
        );
    }

    /**
     * Calling addMailModalCcBccWithTags() twice on the same form silently overwrites
     * the hidden_cc/hidden_bcc elements. Zend_Form::addElement() replaces any existing
     * element with the same name without raising an error.
     *
     * Impact: low — the method is not intended to be called twice per form, but
     * there is no guard preventing it.
     */
    public function testCalledTwiceOverwritesSilently(): void
    {
        $form = $this->makeFormWithCcBcc('my-form');
        $this->callAddCcBcc($form, 'my-form', 'alice@example.com', '');
        $this->callAddCcBcc($form, 'my-form', 'bob@example.com', '');

        $rows = json_decode($form->getElement('hidden_cc')->getValue(), true);
        self::assertSame('bob@example.com', $rows[0]['value'],
            'Second call overwrites the first — no duplicate-call guard exists'
        );
    }
}