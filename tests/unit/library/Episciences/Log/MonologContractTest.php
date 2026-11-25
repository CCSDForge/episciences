<?php

namespace unit\library\Episciences\Log;

use DateTimeImmutable;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Locks in the parts of the Monolog 3 contract the migration from 1.x
 * depends on, so a future `composer update` cannot silently drift back to
 * a different major version or a different default log line format.
 *
 * @covers \Monolog\Logger
 */
class MonologContractTest extends TestCase
{
    public function testMonologApiIsMajorVersion3(): void
    {
        self::assertSame(3, Logger::API);
    }

    public function testProcessorsReceiveALogRecordInstance(): void
    {
        $logger = new Logger('test');
        $logger->pushHandler(new TestHandler());

        $receivedRecord = null;
        $logger->pushProcessor(static function (LogRecord $record) use (&$receivedRecord): LogRecord {
            $receivedRecord = $record;
            return $record;
        });

        $logger->info('hello');

        self::assertInstanceOf(LogRecord::class, $receivedRecord);
    }

    public function testDefaultLineFormatterUsesIso8601Timestamps(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable(),
            'test',
            Level::Info,
            'hello'
        );

        $formatted = (new LineFormatter())->format($record);

        self::assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\]/',
            $formatted
        );
    }
}
