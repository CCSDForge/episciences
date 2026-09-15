<?php

namespace unit\library\Episciences\Log;

use Episciences\Log\LoggerFactory;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

/**
 * rotating() takes an explicit $file, so its tests write to a throwaway
 * sys_get_temp_dir() directory and never touch EPISCIENCES_LOG_PATH.
 *
 * cli() has no such override and resolves EPISCIENCES_LOG_PATH itself; the
 * full application bootstrap this suite runs under always defines that
 * constant (as "../logs/", see tests/bootstrap.php -> defineApplicationConstants()),
 * so — unlike rotating() — its tests write into that real, shared, writable
 * directory under a unique per-test channel name and delete what they created.
 *
 * @covers \Episciences\Log\LoggerFactory
 */
class LoggerFactoryTest extends TestCase
{
    private string $logDir;

    /** @var string[] absolute paths written by cli() tests, removed in tearDown() */
    private array $cliFilesToClean = [];

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/' . uniqid('logger-factory-test-', true) . '/';
        mkdir($this->logDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->logDir);

        foreach ($this->cliFilesToClean as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testRotatingPushesASingleRotatingFileHandler(): void
    {
        $logger = LoggerFactory::rotating('appLogger', $this->logDir . 'app.log');

        $handlers = $logger->getHandlers();
        self::assertCount(1, $handlers);
        self::assertInstanceOf(RotatingFileHandler::class, $handlers[0]);
    }

    public function testRotatingUsesALineFormatter(): void
    {
        $logger = LoggerFactory::rotating('appLogger', $this->logDir . 'app.log');

        self::assertInstanceOf(LineFormatter::class, $logger->getHandlers()[0]->getFormatter());
    }

    public function testRotatingUsesTheRequestedChannelName(): void
    {
        $logger = LoggerFactory::rotating('CASLogger', $this->logDir . 'cas.log');

        self::assertSame('CASLogger', $logger->getName());
    }

    public function testRotatingHonoursTheRequestedLevel(): void
    {
        $logger = LoggerFactory::rotating('appLogger', $this->logDir . 'app.log', Level::Info);

        self::assertSame(Level::Info, $logger->getHandlers()[0]->getLevel());
    }

    public function testRotatingWritesToTheGivenFile(): void
    {
        $logger = LoggerFactory::rotating('appLogger', $this->logDir . 'app.log');

        $logger->info('hello');

        /** @var RotatingFileHandler $handler */
        $handler = $logger->getHandlers()[0];
        $writtenFile = $handler->getUrl();

        self::assertNotNull($writtenFile);
        self::assertFileExists($writtenFile);
        self::assertStringContainsString('hello', file_get_contents($writtenFile));
    }

    public function testCliWithStdoutPushesTwoHandlersIncludingStdout(): void
    {
        $logger = LoggerFactory::cli(uniqid('logger-factory-cli-test-', true));

        $handlers = $logger->getHandlers();
        self::assertCount(2, $handlers);

        /** @var StreamHandler $fileHandler */
        $fileHandler = $handlers[0];
        $this->cliFilesToClean[] = $fileHandler->getUrl();

        $urls = array_map(static fn(StreamHandler $handler): ?string => $handler->getUrl(), $handlers);
        self::assertContains('php://stdout', $urls);
    }

    public function testCliWithoutStdoutPushesOnlyTheFileHandler(): void
    {
        $logger = LoggerFactory::cli(uniqid('logger-factory-cli-test-', true), false);

        $handlers = $logger->getHandlers();
        self::assertCount(1, $handlers);

        /** @var StreamHandler $handler */
        $handler = $handlers[0];
        $this->cliFilesToClean[] = $handler->getUrl();

        self::assertNotSame('php://stdout', $handler->getUrl());
    }

    public function testCliFileNameContainsTheChannelAndTodaysDate(): void
    {
        $channel = uniqid('logger-factory-cli-test-', true);

        $logger = LoggerFactory::cli($channel, false);
        $logger->info('hello');

        $expectedFile = LoggerFactory::logPath() . $channel . '_' . date('Y-m-d') . '.log';
        $this->cliFilesToClean[] = $expectedFile;

        self::assertFileExists($expectedFile);
    }
}
