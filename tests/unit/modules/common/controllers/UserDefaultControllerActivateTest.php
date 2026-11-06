<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_View;

/**
 * UserDefaultController::activateAction(): an activation link without a usable token
 * (truncated link, crawler) shows the activation error page instead of raising an application error.
 */
final class UserDefaultControllerActivateTest extends TestCase
{
    /** @var \UserDefaultController&object{renderedScript: string|null} */
    private \UserDefaultController $controller;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

        $this->controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        ) extends \UserDefaultController {
            public ?string $renderedScript = null;

            /**
             * @param string $script
             * @param string|null $name
             */
            public function renderScript($script, $name = null): void
            {
                $this->renderedScript = $script;
            }
        };
        $this->controller->view = new Zend_View();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableTokenProvider(): array
    {
        return [
            'no token' => [null],
            'empty token' => [''],
            'truncated token' => [str_repeat('a', 39)],
            'too long token' => [str_repeat('a', 41)],
            'array instead of a string' => [[str_repeat('a', 40)]],
        ];
    }

    /**
     * @dataProvider unusableTokenProvider
     */
    public function testUnusableTokenShowsTheActivationErrorPage(mixed $token): void
    {
        if ($token !== null) {
            $this->controller->getRequest()->setParam('token', $token);
        }

        $this->controller->activateAction();

        self::assertSame('error/error.phtml', $this->controller->renderedScript);
        self::assertSame("Erreur lors de l'activation du compte", $this->controller->view->message);
        self::assertSame("Erreur le jeton d'activation de ce compte n'est pas valable", $this->controller->view->description);
    }
}
