<?php

declare(strict_types=1);

namespace unit\library\Episciences\Altcha;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Algorithm\Argon2id;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\Solution;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Episciences\Altcha\ChallengeHelper;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @covers \Episciences\Altcha\ChallengeHelper
 */
class ChallengeHelperTest extends TestCase
{
    private function skipIfNoSodium(): void
    {
        if (!\function_exists('sodium_crypto_pwhash')) {
            $this->markTestSkipped('ext-sodium is required for Argon2id.');
        }
    }

    // =========================================================================
    // createAltcha()
    // =========================================================================

    public function testCreateAltchaReturnsAltchaInstance(): void
    {
        $this->assertInstanceOf(Altcha::class, ChallengeHelper::createAltcha());
    }

    // =========================================================================
    // createChallenge()
    // =========================================================================

    public function testCreateChallengeReturnsChallenge(): void
    {
        $challenge = ChallengeHelper::createChallenge();

        $this->assertInstanceOf(Challenge::class, $challenge);
    }

    public function testCreateChallengeUsesArgon2idAlgorithm(): void
    {
        $challenge = ChallengeHelper::createChallenge();

        $this->assertSame('ARGON2ID', $challenge->parameters->algorithm);
    }

    public function testCreateChallengeHasNonEmptyNonceAndSalt(): void
    {
        $challenge = ChallengeHelper::createChallenge();

        $this->assertNotEmpty($challenge->parameters->nonce);
        $this->assertNotEmpty($challenge->parameters->salt);
    }

    public function testCreateChallengeParametersMatchConfig(): void
    {
        $challenge = ChallengeHelper::createChallenge();

        $this->assertSame(2, $challenge->parameters->cost);
        $this->assertSame(16384, $challenge->parameters->memoryCost);
    }

    public function testCreateChallengeExpiresInFuture(): void
    {
        $challenge = ChallengeHelper::createChallenge();

        $this->assertNotNull($challenge->parameters->expiresAt);
        $this->assertGreaterThan(time(), $challenge->parameters->expiresAt);
    }

    public function testCreateChallengeHasSignatureWhenSecretSet(): void
    {
        if (ALTCHA_HMAC_SECRET === '') {
            $this->markTestSkipped('ALTCHA_HMAC_SECRET not configured.');
        }

        $challenge = ChallengeHelper::createChallenge();

        $this->assertNotNull($challenge->signature);
        $this->assertNotEmpty($challenge->signature);
    }

    public function testCreateChallengeProducesUniqueNoncesEachCall(): void
    {
        $a = ChallengeHelper::createChallenge();
        $b = ChallengeHelper::createChallenge();

        $this->assertNotSame($a->parameters->nonce, $b->parameters->nonce);
    }

    public function testCreateChallengeSerializesToJson(): void
    {
        $challenge = ChallengeHelper::createChallenge();
        $json = $challenge->toJson();

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('parameters', $decoded);
        $this->assertSame('ARGON2ID', $decoded['parameters']['algorithm']);
    }

    // =========================================================================
    // isReplay() / markUsed()
    // =========================================================================

    public function testIsReplayReturnsFalseForFreshNonce(): void
    {
        $cache = new ArrayAdapter(0, false);
        $nonce = bin2hex(random_bytes(16));

        $this->assertFalse(ChallengeHelper::isReplay($nonce, $cache));
    }

    public function testIsReplayReturnsTrueAfterMarkUsed(): void
    {
        $cache = new ArrayAdapter(0, false);
        $nonce = bin2hex(random_bytes(16));

        ChallengeHelper::markUsed($nonce, $cache);

        $this->assertTrue(ChallengeHelper::isReplay($nonce, $cache));
    }

    public function testDifferentNoncesAreTrackedIndependently(): void
    {
        $cache = new ArrayAdapter(0, false);
        $nonce1 = bin2hex(random_bytes(16));
        $nonce2 = bin2hex(random_bytes(16));

        ChallengeHelper::markUsed($nonce1, $cache);

        $this->assertTrue(ChallengeHelper::isReplay($nonce1, $cache));
        $this->assertFalse(ChallengeHelper::isReplay($nonce2, $cache));
    }

    public function testMarkUsedIsIdempotent(): void
    {
        $cache = new ArrayAdapter(0, false);
        $nonce = bin2hex(random_bytes(16));

        ChallengeHelper::markUsed($nonce, $cache);
        ChallengeHelper::markUsed($nonce, $cache);

        $this->assertTrue(ChallengeHelper::isReplay($nonce, $cache));
    }

    // =========================================================================
    // getCache()
    // =========================================================================

    public function testGetCacheReturnsCacheItemPool(): void
    {
        $this->assertInstanceOf(CacheItemPoolInterface::class, ChallengeHelper::getCache());
    }

    // =========================================================================
    // End-to-end: challenge creation → verifySolution (no KDF needed)
    // =========================================================================

    public function testVerifySolutionRejectsInvalidDerivedKey(): void
    {
        $this->skipIfNoSodium();

        $challenge = ChallengeHelper::createChallenge();
        $solution = new Solution(counter: 0, derivedKey: str_repeat('00', 32));

        $result = ChallengeHelper::createAltcha()->verifySolution(
            new VerifySolutionOptions(
                payload: new Payload($challenge, $solution),
                algorithm: new Argon2id(),
            )
        );

        $this->assertFalse($result->verified);
        $this->assertTrue($result->invalidSolution);
    }

    public function testVerifySolutionRejectsExpiredChallenge(): void
    {
        $this->skipIfNoSodium();

        $altcha = ChallengeHelper::createAltcha();
        $expiredChallenge = $altcha->createChallenge(
            new \AltchaOrg\Altcha\CreateChallengeOptions(
                algorithm: new Argon2id(),
                cost: 3,
                memoryCost: 32768,
                expiresAt: time() - 1,
            )
        );
        $solution = new Solution(counter: 0, derivedKey: str_repeat('00', 32));

        $result = $altcha->verifySolution(
            new VerifySolutionOptions(
                payload: new Payload($expiredChallenge, $solution),
                algorithm: new Argon2id(),
            )
        );

        $this->assertFalse($result->verified);
        $this->assertTrue($result->expired);
    }

    public function testChallengeParametersRoundTripFromArray(): void
    {
        $challenge = ChallengeHelper::createChallenge();
        $decoded = json_decode($challenge->toJson(), true);

        $params = ChallengeParameters::fromArray($decoded['parameters']);

        $this->assertSame('ARGON2ID', $params->algorithm);
        $this->assertSame($challenge->parameters->nonce, $params->nonce);
        $this->assertSame($challenge->parameters->salt, $params->salt);
        $this->assertSame($challenge->parameters->cost, $params->cost);
        $this->assertSame($challenge->parameters->memoryCost, $params->memoryCost);
    }
}
