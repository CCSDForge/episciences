<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Csrf_Helper;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Form;
use Zend_View;

/**
 * The submission and new version forms carry the session request token that their
 * actions check. Both forms build their "submitDoc" group through the same helper,
 * which is what guarantees the token is present.
 */
final class SubmitCsrfTokenTest extends TestCase
{
    private function buildGroup(Zend_Form $form, array $elementNames): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'addSubmitDocGroup');
        $method->setAccessible(true);
        $method->invoke(null, $form, $elementNames);
    }

    public function testSubmitDocGroupHoldsTheSessionToken(): void
    {
        $form = new Zend_Form();
        $form->addElement('hidden', 'xml');

        $this->buildGroup($form, ['xml']);

        $element = $form->getElement(Episciences_Submit::CSRF_TOKEN_ELEMENT_NAME);
        self::assertNotNull($element);
        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $element->getValue());

        $group = $form->getDisplayGroup('submitDoc');
        self::assertNotNull($group);
        self::assertNotNull($group->getElement(Episciences_Submit::CSRF_TOKEN_ELEMENT_NAME));
        self::assertNotNull($group->getElement('xml'));
    }

    public function testTokenIsRenderedAsASubmittedHiddenField(): void
    {
        $form = new Zend_Form();
        $form->addElement('hidden', 'xml');
        $this->buildGroup($form, ['xml']);

        $html = $form->setView(new Zend_View())->render();

        self::assertMatchesRegularExpression(
            '/<input[^>]*type="hidden"[^>]*name="csrf_token"[^>]*value="' . preg_quote(Episciences_Csrf_Helper::getSessionToken(), '/') . '"/',
            $html
        );
    }
}
