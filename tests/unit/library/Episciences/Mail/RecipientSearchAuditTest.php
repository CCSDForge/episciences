<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences\Mail\RecipientSearchAudit;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RecipientSearchAuditTest extends TestCase
{
    private const NOW = 1_700_000_400; // start of a 600 seconds window

    private RecipientSearchAuditTestLogger $logger;

    /**
     * @return list<array{level: mixed, message: string, context: array<mixed>}>
     */
    private function logs(): array
    {
        return $this->logger->records;
    }

    private function audit(int $threshold = 2): RecipientSearchAudit
    {
        $this->logger = new RecipientSearchAuditTestLogger();

        return new RecipientSearchAudit(new ArrayAdapter(0, false), $this->logger, $threshold, 600);
    }

    public function testSearchesAreCountedPerWindow(): void
    {
        $audit = $this->audit();

        self::assertSame(1, $audit->record(5, 3, self::NOW));
        self::assertSame(2, $audit->record(5, 3, self::NOW + 10));
        self::assertSame(1, $audit->record(5, 3, self::NOW + 600), 'a new window starts');
        self::assertSame(1, $audit->record(6, 3, self::NOW), 'another user');
    }

    public function testExceedingTheThresholdIsLoggedOnceAndNeverBlocks(): void
    {
        $audit = $this->audit();

        for ($i = 0; $i < 5; $i++) {
            $audit->record(5, 3, self::NOW + $i);
        }

        self::assertCount(1, $this->logs());
        self::assertSame('warning', $this->logs()[0]['level']);
        self::assertSame(['uid' => 5, 'rvid' => 3, 'searches' => 3, 'windowSeconds' => 600], $this->logs()[0]['context']);
    }

    public function testNoLogBelowTheThreshold(): void
    {
        $audit = $this->audit();

        $audit->record(5, 3, self::NOW);
        $audit->record(5, 3, self::NOW);

        self::assertSame([], $this->logs());
    }
}

final class RecipientSearchAuditTestLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
    }
}
