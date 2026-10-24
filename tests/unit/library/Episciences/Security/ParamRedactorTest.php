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
            'PwD' => 'f',
            'previous_password' => 'g',
            'API_PASSWORD' => 'h',
            'token' => 'i',
            'client_secret' => 'j',
            'keywords' => 'kept',
        ]);

        self::assertSame('alice', $out['username']);
        self::assertSame('kept', $out['keywords']);
        foreach (['PASSWORD_VERIFY', 'paperPassword', 'authToken', 'Secret', 'csrf', 'PwD', 'previous_password', 'API_PASSWORD', 'token', 'client_secret'] as $key) {
            self::assertSame(ParamRedactor::MASK, $out[$key], $key);
        }
    }

    public function testNestedParametersAreMasked(): void
    {
        $out = ParamRedactor::redact([
            'form' => ['email' => 'a@b.c', 'password' => 'secret'],
            'search_doc' => ['paperPassword' => 'd', 'title' => 'T'],
        ]);

        self::assertSame(['email' => 'a@b.c', 'password' => ParamRedactor::MASK], $out['form']);
        self::assertSame(['paperPassword' => ParamRedactor::MASK, 'title' => 'T'], $out['search_doc']);
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
            'user_access_code' => 'kept',
            'language_code' => 'fr',
        ]);

        self::assertSame(ParamRedactor::MASK, $out['code']);
        self::assertSame(ParamRedactor::MASK, $out['ACCESS_CODE']);
        self::assertSame('kept', $out['user_access_code']);
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
