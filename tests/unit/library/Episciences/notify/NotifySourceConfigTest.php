<?php

declare(strict_types=1);

namespace unit\library\Episciences\notify;

use Episciences\Notify\NotifySourceConfig;
use PHPUnit\Framework\TestCase;

class NotifySourceConfigTest extends TestCase
{
    private const REPO_ID       = 1;
    private const LABEL         = 'HAL';
    private const ORIGIN_ID     = 'https://hal.science/';
    private const ORIGIN_INBOX  = 'https://inbox.hal.science/';
    private const ACCEPTED_TYPES = ['Offer', 'coar-notify:ReviewAction'];

    // -------------------------------------------------------------------------
    // Getters return constructor values
    // -------------------------------------------------------------------------

    public function testGetRepoId(): void
    {
        self::assertSame(self::REPO_ID, $this->buildConfig()->getRepoId());
    }

    public function testGetLabel(): void
    {
        self::assertSame(self::LABEL, $this->buildConfig()->getLabel());
    }

    public function testGetOriginId(): void
    {
        self::assertSame(self::ORIGIN_ID, $this->buildConfig()->getOriginId());
    }

    public function testGetOriginInbox(): void
    {
        self::assertSame(self::ORIGIN_INBOX, $this->buildConfig()->getOriginInbox());
    }

    public function testGetAcceptedTypes(): void
    {
        self::assertSame(self::ACCEPTED_TYPES, $this->buildConfig()->getAcceptedTypes());
    }

    // -------------------------------------------------------------------------
    // Default accepted types
    // -------------------------------------------------------------------------

    public function testDefaultAcceptedTypesAreReviewAction(): void
    {
        $config = new NotifySourceConfig(
            repoId:      self::REPO_ID,
            label:       self::LABEL,
            originId:    self::ORIGIN_ID,
            originInbox: self::ORIGIN_INBOX,
        );

        self::assertSame(['Offer', 'coar-notify:ReviewAction'], $config->getAcceptedTypes());
    }

    // -------------------------------------------------------------------------
    // Custom accepted types
    // -------------------------------------------------------------------------

    public function testCustomAcceptedTypes(): void
    {
        $types  = ['Offer', 'coar-notify:EndorsementAction'];
        $config = new NotifySourceConfig(
            repoId:        self::REPO_ID,
            label:         self::LABEL,
            originId:      self::ORIGIN_ID,
            originInbox:   self::ORIGIN_INBOX,
            acceptedTypes: $types,
        );

        self::assertSame($types, $config->getAcceptedTypes());
    }

    // -------------------------------------------------------------------------
    // Immutability: two instances with same data are independent
    // -------------------------------------------------------------------------

    public function testTwoInstancesWithSameDataAreNotSameObject(): void
    {
        $a = $this->buildConfig();
        $b = $this->buildConfig();

        self::assertNotSame($a, $b);
        self::assertSame($a->getRepoId(), $b->getRepoId());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildConfig(): NotifySourceConfig
    {
        return new NotifySourceConfig(
            repoId:        self::REPO_ID,
            label:         self::LABEL,
            originId:      self::ORIGIN_ID,
            originInbox:   self::ORIGIN_INBOX,
            acceptedTypes: self::ACCEPTED_TYPES,
        );
    }
}
