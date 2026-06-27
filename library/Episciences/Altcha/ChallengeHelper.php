<?php

declare(strict_types=1);

namespace Episciences\Altcha;

use AltchaOrg\Altcha\V1\Altcha;
use AltchaOrg\Altcha\V1\Challenge;
use AltchaOrg\Altcha\V1\ChallengeOptions;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class ChallengeHelper
{
    private const EXPIRY_SECONDS = 120;

    public static function createAltcha(): Altcha
    {
        return new Altcha(ALTCHA_HMAC_SECRET);
    }

    public static function createChallenge(): Challenge
    {
        return self::createAltcha()->createChallenge(
            new ChallengeOptions(expires: new \DateTimeImmutable('+' . self::EXPIRY_SECONDS . ' seconds'))
        );
    }

    public static function isReplay(string $salt, CacheItemPoolInterface $cache): bool
    {
        return $cache->getItem('s_' . hash('sha256', $salt))->isHit();
    }

    public static function markUsed(string $salt, CacheItemPoolInterface $cache): void
    {
        $item = $cache->getItem('s_' . hash('sha256', $salt));
        $item->set(true)->expiresAfter(self::EXPIRY_SECONDS);
        $cache->save($item);
    }

    public static function getCache(): CacheItemPoolInterface
    {
        return new FilesystemAdapter('altcha', 0, APPLICATION_PATH . '/../cache');
    }
}
