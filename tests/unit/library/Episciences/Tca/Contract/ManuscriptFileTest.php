<?php

declare(strict_types=1);

namespace unit\library\Episciences\Tca\Contract;

use Episciences\Tca\Contract\ManuscriptFile;
use Episciences\Tca\Contract\ManuscriptTemporarilyUnavailableException;
use Episciences\Tca\Contract\ManuscriptTooLargeException;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Episciences\Tca\Contract\ManuscriptFile
 * @covers \Episciences\Tca\Contract\ManuscriptTooLargeException
 * @covers \Episciences\Tca\Contract\ManuscriptTemporarilyUnavailableException
 */
final class ManuscriptFileTest extends TestCase
{
    public function testExposesTheGivenValues(): void
    {
        $stream = Utils::streamFor('%PDF-1.4');

        $file = new ManuscriptFile($stream, 'Étude complète.pdf', 8, 'application/pdf');

        self::assertSame($stream, $file->stream);
        self::assertSame('Étude complète.pdf', $file->fileName);
        self::assertSame(8, $file->size);
        self::assertSame('application/pdf', $file->mimeType);
    }

    public function testMaxSizeIsTheTurnitinLimit(): void
    {
        self::assertSame(104857600, ManuscriptFile::MAX_SIZE);
    }

    /**
     * @dataProvider invalidSizeProvider
     */
    public function testRejectsANonPositiveSize(int $size): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ManuscriptFile(Utils::streamFor('x'), 'paper.pdf', $size, 'application/pdf');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidSizeProvider(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    /**
     * @dataProvider invalidFileNameProvider
     */
    public function testRejectsAFileNameWithoutNameOrExtension(string $fileName): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ManuscriptFile(Utils::streamFor('x'), $fileName, 1, 'application/pdf');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFileNameProvider(): array
    {
        return ['no extension' => ['paper'], 'extension only' => ['.pdf'], 'empty' => ['']];
    }

    public function testRejectsAnEmptyMimeType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ManuscriptFile(Utils::streamFor('x'), 'paper.pdf', 1, '');
    }

    public function testRejectsAnUnreadableStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);

        new ManuscriptFile($stream, 'paper.pdf', 1, 'application/pdf');
    }

    public function testTooLargeIsFinalAndCarriesTheObservedSize(): void
    {
        $exception = new ManuscriptTooLargeException(200, 100);

        self::assertFalse($exception->isRetryable());
        self::assertSame(200, $exception->getObservedSize());
    }

    public function testTemporarilyUnavailableIsRetryable(): void
    {
        self::assertTrue((new ManuscriptTemporarilyUnavailableException('down'))->isRetryable());
    }
}
