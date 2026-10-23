<?php

declare(strict_types=1);

namespace unit\library\Episciences\Security;

use Episciences\Security\ParamRedactor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Security\ParamRedactor
 */
final class ParamRedactorTest extends TestCase
{
    public function testCredentialLikeKeysAreMaskedWhateverTheirCase(): void
    {
        $out = ParamRedactor::redact([
            'username' => 'alice',
            'PASSWORD_VERIFY' => 'a',
            'paperPassword' => 'b',
            'authToken' => 'c',
            'Secret' => 'd',
            'csrf' => 'e',
        ]);

        self::assertSame('alice', $out['username']);
        foreach (['PASSWORD_VERIFY', 'paperPassword', 'authToken', 'Secret', 'csrf'] as $key) {
            self::assertSame(ParamRedactor::MASK, $out[$key], $key);
        }
    }

    public function testNestedParametersAreMasked(): void
    {
        $out = ParamRedactor::redact(['form' => ['email' => 'a@b.c', 'password' => 'secret']]);

        self::assertSame(['email' => 'a@b.c', 'password' => ParamRedactor::MASK], $out['form']);
    }

    public function testSensitiveKeyHoldingAnArrayIsMaskedEntirely(): void
    {
        self::assertSame(['password' => ParamRedactor::MASK], ParamRedactor::redact(['password' => ['a', 'b']]));
    }

    public function testGenericNamesAreMaskedOnExactMatchOnly(): void
    {
        $out = ParamRedactor::redact([
            'code' => '5f0c1a2b3d4e5',
            'ACCESS_CODE' => '5f0c1a2b3d4e5',
            'language_code' => 'fr',
        ]);

        self::assertSame(ParamRedactor::MASK, $out['code']);
        self::assertSame(ParamRedactor::MASK, $out['ACCESS_CODE']);
        self::assertSame('fr', $out['language_code']);
    }

    public function testRoleNamesContainingAFragmentAreNotMasked(): void
    {
        $out = ParamRedactor::redact(['roles' => ['secretary' => '1', 'secretaries' => ['12']]]);

        self::assertSame(['secretary' => '1', 'secretaries' => ['12']], $out['roles']);
    }

    public function testNonSensitiveParametersAreUnchanged(): void
    {
        $in = ['controller' => 'user', 'action' => 'login', 'id' => '42'];

        self::assertSame($in, ParamRedactor::redact($in));
    }
}
