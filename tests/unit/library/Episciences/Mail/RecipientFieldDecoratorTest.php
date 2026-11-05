<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Ccsd_Form;
use Episciences\Mail\RecipientFieldDecorator;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Rendering of a recipient field (To, Cc, Bcc) of the mail forms
 */
final class RecipientFieldDecoratorTest extends TestCase
{
    private function render(string $target = 'cc'): string
    {
        $form = new Ccsd_Form();
        $form->addElement('text', $target, ['id' => 'my-form-' . $target]);
        RecipientFieldDecorator::apply($form->getElement($target), $target, 'my-form-' . $target . '-tags', 'my-form-hidden_' . $target);

        return $form->getElement($target)->render(new Zend_View());
    }

    public function testLabelIsAPlainLabelOfTheInput(): void
    {
        $html = $this->render();

        self::assertSame(1, preg_match('#<label[^>]*for="my-form-cc"[^>]*>(.*?)</label>#s', $html, $matches));
        self::assertSame('Cc', trim(strip_tags($matches[1])));
        self::assertStringNotContainsString('<a', $matches[1], 'no link inside the label');
    }

    public function testPickerButtonIsARealButtonOutsideOfTheLabel(): void
    {
        $html = $this->render();

        self::assertSame(1, preg_match('#<button type="button" class="[^"]*show_contacts_button"[^>]*>(.*?)</button>#s', $html, $matches));
        self::assertStringContainsString('aria-expanded="false"', $matches[0]);
        self::assertStringContainsString('aria-controls="my-form-cc-picker"', $matches[0]);
        self::assertStringContainsString('data-target="cc"', $matches[0]);
        // The accessible name contains the visible text (WCAG 2.5.3)
        self::assertStringContainsString('Choisir<span class="sr-only"> des destinataires (Cc)</span>', $matches[1]);
    }

    public function testInputIsDescribedByTheHelpText(): void
    {
        $html = $this->render();

        self::assertSame(1, preg_match('#<input[^>]*id="my-form-cc"[^>]*>#', $html, $input));
        self::assertStringContainsString('aria-describedby="my-form-cc-help"', $input[0]);
        self::assertStringContainsString('autocomplete="off"', $input[0]);
        self::assertStringContainsString('data-recipient-target="cc"', $input[0]);
        self::assertStringContainsString('class="recipient-input', $input[0]);
        self::assertStringContainsString('<span id="my-form-cc-help" class="help-block recipient-help">', $html);
    }

    public function testRecipientsListPointsToItsHiddenInput(): void
    {
        $html = $this->render('bcc');

        self::assertSame(1, preg_match('#<ul id="my-form-bcc-tags"[^>]*>#', $html, $list));
        self::assertStringContainsString('data-hidden-input="my-form-hidden_bcc"', $list[0]);
        self::assertStringContainsString('data-recipient-target="bcc"', $list[0]);
        self::assertStringContainsString('aria-label="Destinataires (Bcc)"', $list[0]);
    }

    public function testUnknownFieldIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $form = new Ccsd_Form();
        $form->addElement('text', 'reply');
        RecipientFieldDecorator::apply($form->getElement('reply'), 'reply', 'x', 'y');
    }
}
