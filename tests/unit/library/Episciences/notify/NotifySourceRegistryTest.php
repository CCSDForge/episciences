<?php

declare(strict_types=1);

namespace unit\library\Episciences\notify;

use Episciences\Notify\NotifySourceConfig;
use Episciences\Notify\NotifySourceRegistry;
use PHPUnit\Framework\TestCase;

class NotifySourceRegistryTest extends TestCase
{
    private const HAL_INBOX = 'https://inbox.hal.science/';

    // -------------------------------------------------------------------------
    // findByOriginInbox — known inbox
    // -------------------------------------------------------------------------

    public function testFindByOriginInboxReturnsConfigWhenFound(): void
    {
        $config   = $this->buildHalConfig();
        $registry = new NotifySourceRegistry([self::HAL_INBOX => $config]);

        self::assertSame($config, $registry->findByOriginInbox(self::HAL_INBOX));
    }

    // -------------------------------------------------------------------------
    // findByOriginInbox — unknown inbox
    // -------------------------------------------------------------------------

    public function testFindByOriginInboxReturnsNullForUnknownInbox(): void
    {
        $registry = new NotifySourceRegistry([self::HAL_INBOX => $this->buildHalConfig()]);

        self::assertNull($registry->findByOriginInbox('https://unknown.org/'));
    }

    public function testFindByOriginInboxReturnsNullForEmptyInbox(): void
    {
        $registry = new NotifySourceRegistry([self::HAL_INBOX => $this->buildHalConfig()]);

        self::assertNull($registry->findByOriginInbox(''));
    }

    public function testFindByOriginInboxReturnsNullWhenRegistryIsEmpty(): void
    {
        $registry = new NotifySourceRegistry([]);

        self::assertNull($registry->findByOriginInbox(self::HAL_INBOX));
    }

    // -------------------------------------------------------------------------
    // Multiple sources
    // -------------------------------------------------------------------------

    public function testRegistryCanHoldMultipleSources(): void
    {
        $halConfig    = $this->buildHalConfig();
        $zenodoInbox  = 'https://inbox.zenodo.org/';
        $zenodoConfig = new NotifySourceConfig(2, 'Zenodo', 'https://zenodo.org/', $zenodoInbox);

        $registry = new NotifySourceRegistry([
            self::HAL_INBOX => $halConfig,
            $zenodoInbox    => $zenodoConfig,
        ]);

        self::assertSame($halConfig, $registry->findByOriginInbox(self::HAL_INBOX));
        self::assertSame($zenodoConfig, $registry->findByOriginInbox($zenodoInbox));
        self::assertNull($registry->findByOriginInbox('https://other.org/'));
    }

    // -------------------------------------------------------------------------
    // createFromConstants — test environment has empty NOTIFY_TARGET_HAL_INBOX
    // -------------------------------------------------------------------------

    public function testCreateFromConstantsReturnsEmptyRegistryWhenHalInboxIsEmpty(): void
    {
        // In the test environment NOTIFY_TARGET_HAL_INBOX is defined as '' (see public/const.php).
        $registry = NotifySourceRegistry::createFromConstants();

        self::assertNull($registry->findByOriginInbox(''));
        self::assertNull($registry->findByOriginInbox(self::HAL_INBOX));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildHalConfig(): NotifySourceConfig
    {
        return new NotifySourceConfig(
            repoId:       1,
            label:        'HAL',
            originId:     'https://hal.science/',
            originInbox:  self::HAL_INBOX,
            acceptedTypes: ['Offer', 'coar-notify:ReviewAction'],
        );
    }
}
