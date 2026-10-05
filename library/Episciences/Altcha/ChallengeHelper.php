<?php

declare(strict_types=1);

namespace Episciences\Altcha;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Algorithm\Argon2id;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\CreateChallengeOptions;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class ChallengeHelper
{
    private const EXPIRY_SECONDS = 120;

    public static function createAltcha(): Altcha
    {
        return new Altcha(hmacSignatureSecret: ALTCHA_HMAC_SECRET);
    }

    public static function createChallenge(): Challenge
    {
        // cost=2 / memoryCost=16384 (16 MiB) targets ~1-3 s on mobile, <1 s on desktop.
        // Primary users are desktop (journal editors/authors), so increase cost/memoryCost
        // if bot pressure rises, or lower them if mobile UX degrades.
        return self::createAltcha()->createChallenge(
            new CreateChallengeOptions(
                algorithm: new Argon2id(),
                cost: 2,
                memoryCost: 16384,
                expiresAt: time() + self::EXPIRY_SECONDS,
            )
        );
    }

    public static function isReplay(string $nonce, CacheItemPoolInterface $cache): bool
    {
        return $cache->getItem('n_' . hash('sha256', $nonce))->isHit();
    }

    public static function markUsed(string $nonce, CacheItemPoolInterface $cache): void
    {
        $item = $cache->getItem('n_' . hash('sha256', $nonce));
        $item->set(true)->expiresAfter(self::EXPIRY_SECONDS);
        $cache->save($item);
    }

    public static function getCache(): CacheItemPoolInterface
    {
        return new FilesystemAdapter('altcha', 0, APPLICATION_PATH . '/../cache');
    }
}
