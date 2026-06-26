<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioural tests for ErrorDefaultController::redactSensitiveParams().
 *
 * The method masks credential-like request parameters before they are written to
 * the application log. It is pure (array walk, no $this state, no DB, no MVC stack),
 * so the controller is instantiated without its constructor and the public method is
 * called directly.
 *
 * @covers ErrorDefaultController::redactSensitiveParams
 */
final class ErrorDefaultControllerTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/ErrorDefaultController.php';
        $class = new ReflectionClass(\ErrorDefaultController::class);
        $this->controller = $class->newInstanceWithoutConstructor();
    }

    public function testCredentialLikeKeysAreMasked(): void
    {
        $in = [
            'username' => 'alice',
            'PASSWORD' => 'secret',
            'previous_password' => 'old',
            'API_PASSWORD' => 'k',
            'token' => 't',
        ];

        $out = $this->controller->redactSensitiveParams($in);

        self::assertSame('alice', $out['username'], 'non-sensitive values are kept');
        self::assertSame('***', $out['PASSWORD']);
        self::assertSame('***', $out['previous_password']);
        self::assertSame('***', $out['API_PASSWORD']);
        self::assertSame('***', $out['token']);
    }

    public function testMaskingIsCaseInsensitive(): void
    {
        $out = $this->controller->redactSensitiveParams(['PwD' => 'x', 'Secret' => 'y']);
        self::assertSame('***', $out['PwD']);
        self::assertSame('***', $out['Secret']);
    }

    public function testNestedParametersAreMasked(): void
    {
        $in = ['form' => ['email' => 'a@b.c', 'password' => 'secret']];
        $out = $this->controller->redactSensitiveParams($in);

        self::assertSame('a@b.c', $out['form']['email']);
        self::assertSame('***', $out['form']['password']);
    }

    public function testNonSensitiveArrayIsUnchanged(): void
    {
        $in = ['controller' => 'user', 'action' => 'login', 'id' => '42'];
        self::assertSame($in, $this->controller->redactSensitiveParams($in));
    }
}
