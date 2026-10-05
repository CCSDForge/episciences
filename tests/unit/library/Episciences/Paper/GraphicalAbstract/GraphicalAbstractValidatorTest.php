<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\GraphicalAbstract;

use Episciences\Paper\GraphicalAbstract\GraphicalAbstractValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The image fixtures are generated in a temporary directory: the actual MIME type is read
 * by the "file" binary (Symfony FileBinaryMimeTypeGuesser).
 *
 * @covers \Episciences\Paper\GraphicalAbstract\GraphicalAbstractValidator
 */
final class GraphicalAbstractValidatorTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    private const WEBP = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';
    private const JPEG = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8wABgAQEAX/2gAIAQEAAD8A0s8g/9k=';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function fixture(string $contents): string
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'ep_illustration_');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function acceptedImages(): array
    {
        return [
            'png' => [self::PNG, 'chart.png', 'png'],
            'uppercase extension' => [self::PNG, 'CHART.PNG', 'png'],
            'gif' => [self::GIF, 'chart.gif', 'gif'],
            'webp' => [self::WEBP, 'chart.webp', 'webp'],
            'jpeg' => [self::JPEG, 'chart.jpeg', 'jpg'],
            // the stored extension follows the actual type, not the original name
            'png named jpg' => [self::PNG, 'chart.jpg', 'png'],
        ];
    }

    #[DataProvider('acceptedImages')]
    public function testAcceptsImages(string $base64, string $name, string $expectedExtension): void
    {
        $path = $this->fixture((string)base64_decode($base64));

        self::assertNull(GraphicalAbstractValidator::validateFile($path, $name));
        self::assertSame($expectedExtension, GraphicalAbstractValidator::extensionFor($path));
    }

    public function testRejectsSvg(): void
    {
        $path = $this->fixture('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        self::assertSame(GraphicalAbstractValidator::ERROR_FILE_TYPE, GraphicalAbstractValidator::validateFile($path, 'chart.svg'));
        self::assertNull(GraphicalAbstractValidator::extensionFor($path));
    }

    public function testRejectsAFakePng(): void
    {
        $path = $this->fixture('<?php echo "not an image";');

        self::assertSame(GraphicalAbstractValidator::ERROR_FILE_TYPE, GraphicalAbstractValidator::validateFile($path, 'chart.png'));
    }

    public function testRejectsAnImageWithAnUnacceptedExtension(): void
    {
        $path = $this->fixture((string)base64_decode(self::PNG));

        self::assertSame(GraphicalAbstractValidator::ERROR_FILE_TYPE, GraphicalAbstractValidator::validateFile($path, 'chart.php'));
    }

    public function testRejectsATooLargeImage(): void
    {
        $path = $this->fixture(base64_decode(self::PNG) . str_repeat("\0", GraphicalAbstractValidator::MAX_SIZE));

        self::assertSame(GraphicalAbstractValidator::ERROR_FILE_TOO_LARGE, GraphicalAbstractValidator::validateFile($path, 'chart.png'));
    }

    public function testRejectsAMissingFile(): void
    {
        self::assertSame(GraphicalAbstractValidator::ERROR_FILE_REQUIRED, GraphicalAbstractValidator::validateFile('/nonexistent/chart.png', 'chart.png'));
    }

    public function testValidateTextNormalizesTheValues(): void
    {
        $result = GraphicalAbstractValidator::validateText("  A chart\n", ' <b>CC BY 4.0</b> ');

        self::assertSame(['alt' => 'A chart', 'license' => 'CC BY 4.0', 'errors' => []], $result);
    }

    public function testValidateTextRequiresTheAlt(): void
    {
        foreach ([null, '', "  \n", ['array']] as $alt) {
            $result = GraphicalAbstractValidator::validateText($alt, null);

            self::assertSame(
                [GraphicalAbstractValidator::FIELD_ALT => GraphicalAbstractValidator::ERROR_ALT_REQUIRED],
                $result['errors']
            );
            self::assertNull($result['license']);
        }
    }

    public function testValidateTextCountsCharactersNotBytes(): void
    {
        $alt = str_repeat('é', GraphicalAbstractValidator::ALT_MAX_LENGTH);

        self::assertSame([], GraphicalAbstractValidator::validateText($alt, null)['errors']);
        self::assertSame(
            [GraphicalAbstractValidator::FIELD_ALT => GraphicalAbstractValidator::ERROR_ALT_TOO_LONG],
            GraphicalAbstractValidator::validateText($alt . 'é', null)['errors']
        );
    }

    public function testValidateTextLimitsTheLicense(): void
    {
        $result = GraphicalAbstractValidator::validateText('A chart', str_repeat('a', GraphicalAbstractValidator::LICENSE_MAX_LENGTH + 1));

        self::assertSame(
            [GraphicalAbstractValidator::FIELD_LICENSE => GraphicalAbstractValidator::ERROR_LICENSE_TOO_LONG],
            $result['errors']
        );
    }

    public function testEveryErrorHasAMessage(): void
    {
        $codes = [
            GraphicalAbstractValidator::ERROR_FILE_REQUIRED,
            GraphicalAbstractValidator::ERROR_FILE_TOO_LARGE,
            GraphicalAbstractValidator::ERROR_FILE_TYPE,
            GraphicalAbstractValidator::ERROR_ALT_REQUIRED,
            GraphicalAbstractValidator::ERROR_ALT_TOO_LONG,
            GraphicalAbstractValidator::ERROR_LICENSE_TOO_LONG,
        ];

        foreach ($codes as $code) {
            self::assertArrayHasKey($code, GraphicalAbstractValidator::MESSAGES);
            $hasPlaceholder = str_contains(GraphicalAbstractValidator::MESSAGES[$code], '%s');
            self::assertSame($hasPlaceholder, GraphicalAbstractValidator::messageArgument($code) !== null, $code);
        }

        self::assertSame('500', GraphicalAbstractValidator::messageArgument(GraphicalAbstractValidator::ERROR_FILE_TOO_LARGE));
    }
}
