<?php

namespace unit\library\Episciences\Console;

use Episciences\Console\ProgressAwareStreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \Episciences\Console\ProgressAwareStreamHandler
 */
class ProgressAwareStreamHandlerTest extends TestCase
{
    /**
     * @param resource $stream
     */
    private function readStream($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }

    /**
     * @return resource
     */
    private function makeMemoryStream()
    {
        return fopen('php://memory', 'a+');
    }

    private function makeLogger(ProgressAwareStreamHandler $handler): Logger
    {
        $logger = new Logger('test');
        $logger->pushHandler($handler);
        return $logger;
    }

    public function testWriteWithoutProgressBarDoesNotPrependNewline(): void
    {
        $stream = $this->makeMemoryStream();
        $handler = new ProgressAwareStreamHandler($stream, Level::Debug);

        $this->makeLogger($handler)->info('hello world');

        $output = $this->readStream($stream);

        self::assertStringStartsNotWith(PHP_EOL, $output);
        self::assertStringContainsString('hello world', $output);
    }

    public function testWriteWithProgressBarPrependsNewline(): void
    {
        $stream = $this->makeMemoryStream();
        $handler = new ProgressAwareStreamHandler($stream, Level::Debug);
        $handler->setProgressBar(new ProgressBar(new BufferedOutput()));

        $this->makeLogger($handler)->info('hello world');

        $output = $this->readStream($stream);

        self::assertStringStartsWith(PHP_EOL, $output);
        self::assertStringContainsString('hello world', $output);
    }

    public function testWriteWithProgressBarClearsThenRedisplaysIt(): void
    {
        $stream = $this->makeMemoryStream();
        $handler = new ProgressAwareStreamHandler($stream, Level::Debug);

        $bufferedOutput = new BufferedOutput();
        $progressBar = new ProgressBar($bufferedOutput);
        $progressBar->start();
        // Drain the output produced by start() so the assertion below only
        // reflects what write() triggers via clear()/display().
        $bufferedOutput->fetch();

        $handler->setProgressBar($progressBar);

        $this->makeLogger($handler)->info('hello world');

        // BufferedOutput is not ANSI-decorated, so clear() is a no-op there, but
        // display() must still have redrawn the bar around the log write.
        self::assertNotSame('', $bufferedOutput->fetch());
    }

    public function testSetProgressBarNullRestoresPlainBehaviour(): void
    {
        $stream = $this->makeMemoryStream();
        $handler = new ProgressAwareStreamHandler($stream, Level::Debug);

        $handler->setProgressBar(new ProgressBar(new BufferedOutput()));
        $handler->setProgressBar(null);

        $this->makeLogger($handler)->info('hello world');

        $output = $this->readStream($stream);

        self::assertStringStartsNotWith(PHP_EOL, $output);
    }

    public function testMultilineMessagesAreNotTruncated(): void
    {
        $stream = $this->makeMemoryStream();
        $handler = new ProgressAwareStreamHandler($stream, Level::Debug);
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        $this->makeLogger($handler)->info("first line\nsecond line");

        $output = $this->readStream($stream);

        self::assertStringContainsString('first line', $output);
        self::assertStringContainsString('second line', $output);
    }
}
