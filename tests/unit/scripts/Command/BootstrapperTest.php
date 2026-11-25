<?php

namespace unit\scripts\Command;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use scripts\Command\Bootstrapper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/../../../../scripts/Command/Bootstrapper.php';

/**
 * The logger always writes to its file; it only adds a stdout handler
 * when the command output is not muted (--quiet or --silent).
 */
class BootstrapperTest extends TestCase
{
    /**
     * @return array<string, array{int, bool}>
     */
    public static function verbosityProvider(): array
    {
        return [
            'silent' => [OutputInterface::VERBOSITY_SILENT, false],
            'quiet' => [OutputInterface::VERBOSITY_QUIET, false],
            'normal' => [OutputInterface::VERBOSITY_NORMAL, true],
            'verbose' => [OutputInterface::VERBOSITY_VERBOSE, true],
        ];
    }

    #[DataProvider('verbosityProvider')]
    public function testStdoutHandlerDependsOnVerbosity(int $verbosity, bool $expectsStdoutHandler): void
    {
        $logger = $this->initLogger($verbosity);

        $stdoutHandlers = array_filter(
            $logger->getHandlers(),
            static fn($handler): bool => $handler instanceof StreamHandler && $handler->getUrl() === 'php://stdout'
        );

        self::assertSame($expectsStdoutHandler, $stdoutHandlers !== []);
    }

    private function initLogger(int $verbosity): Logger
    {
        $reflection = new ReflectionClass(Bootstrapper::class);
        /** @var Bootstrapper $bootstrapper */
        $bootstrapper = $reflection->newInstanceWithoutConstructor();
        $io = new SymfonyStyle(new ArrayInput([]), new BufferedOutput($verbosity));

        $reflection->getMethod('initLogger')->invoke($bootstrapper, $io, 'bootstrapper_test');

        $logger = $bootstrapper->getLogger();
        self::assertInstanceOf(Logger::class, $logger);

        return $logger;
    }
}
