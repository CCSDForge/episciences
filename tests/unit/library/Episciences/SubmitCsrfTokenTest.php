<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Csrf_Helper;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Form;

/**
 * The submission and new version forms carry the session request token that their
 * actions check.
 */
final class SubmitCsrfTokenTest extends TestCase
{
    public function testHiddenTokenElementHoldsTheSessionToken(): void
    {
        $form = new Zend_Form();
        $group = ['xml'];

        $method = new ReflectionMethod(Episciences_Submit::class, 'addCsrfElement');
        $method->setAccessible(true);
        $method->invokeArgs(null, [$form, &$group]);

        $element = $form->getElement(Episciences_Submit::CSRF_TOKEN_ELEMENT_NAME);

        self::assertNotNull($element);
        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $element->getValue());
        self::assertSame(['xml', 'csrf_token'], $group);
    }

    public function testBothFormsAddTheTokenAndBothActionsCheckIt(): void
    {
        $submit = (string) file_get_contents(APPLICATION_PATH . '/../library/Episciences/Submit.php');
        $submitController = (string) file_get_contents(APPLICATION_PATH . '/modules/journal/controllers/SubmitController.php');
        $paperController = (string) file_get_contents(APPLICATION_PATH . '/modules/journal/controllers/PaperController.php');

        self::assertSame(2, substr_count($submit, 'self::addCsrfElement($form, $group);'));
        self::assertStringContainsString('Episciences_Csrf_Helper::validateRequestToken($request)', $submitController);
        self::assertStringContainsString('Episciences_Csrf_Helper::validateRequestToken($request)', $paperController);
    }
}
