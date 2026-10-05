<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for the graphical_abstract_* keys (file, alt, license) of the JSON v2 paper export.
 *
 * The values are carried over from the stored PAPERS.DOCUMENT column, which
 * GraphicalAbstractRepository writes to directly. They must be null, not '',
 * when absent.
 *
 * All tests are DB-free: the value is read from the in-memory document set
 * through Episciences_Paper::setDocument().
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_GraphicalAbstractJsonTest extends TestCase
{
    private function callGetGraphicalAbstractFileToJson(Episciences_Paper $paper): ?string
    {
        $method = new ReflectionMethod(Episciences_Paper::class, 'getGraphicalAbstractFileToJson');
        $method->setAccessible(true);

        return $method->invoke($paper);
    }

    private function callGetStoredCurrentStringToJson(Episciences_Paper $paper, string $key): ?string
    {
        $method = new ReflectionMethod(Episciences_Paper::class, 'getStoredCurrentStringToJson');
        $method->setAccessible(true);

        return $method->invoke($paper, $key);
    }

    /**
     * @param array<string, mixed> $current
     */
    private function makePaperWithCurrent(array $current): Episciences_Paper
    {
        $paper = new Episciences_Paper();
        $paper->setDocument(json_encode(
            ['database' => ['current' => $current]],
            JSON_THROW_ON_ERROR
        ));

        return $paper;
    }

    public function testReturnsTheStoredFilename(): void
    {
        $paper = $this->makePaperWithCurrent(['graphical_abstract_file' => 'graphical_abstract.png']);

        self::assertSame('graphical_abstract.png', $this->callGetGraphicalAbstractFileToJson($paper));
    }

    public function testReturnsNullWhenTheKeyWasRemovedFromTheStoredDocument(): void
    {
        // JSON_REMOVE path: AdministrategraphabstractController drops the key on delete
        $paper = $this->makePaperWithCurrent(['volume' => null]);

        self::assertNull($this->callGetGraphicalAbstractFileToJson($paper));
    }

    public function testReturnsNullWhenTheStoredValueIsAnEmptyString(): void
    {
        // legacy value: the dead unset() used to leave '' behind on regeneration
        $paper = $this->makePaperWithCurrent(['graphical_abstract_file' => '']);

        self::assertNull($this->callGetGraphicalAbstractFileToJson($paper));
    }

    public function testReturnsNullWhenTheStoredValueIsBlank(): void
    {
        $paper = $this->makePaperWithCurrent(['graphical_abstract_file' => "  \n"]);

        self::assertNull($this->callGetGraphicalAbstractFileToJson($paper));
    }

    public function testTrimsTheStoredFilename(): void
    {
        $paper = $this->makePaperWithCurrent(['graphical_abstract_file' => ' graphical_abstract.jpg ']);

        self::assertSame('graphical_abstract.jpg', $this->callGetGraphicalAbstractFileToJson($paper));
    }

    public function testReturnsNullWhenNoDocumentIsStoredAtAll(): void
    {
        self::assertNull($this->callGetGraphicalAbstractFileToJson(new Episciences_Paper()));
    }

    public function testReturnsNullWhenTheStoredDocumentHasNoDatabaseKey(): void
    {
        $paper = new Episciences_Paper();
        $paper->setDocument(json_encode(['journal' => []], JSON_THROW_ON_ERROR));

        self::assertNull($this->callGetGraphicalAbstractFileToJson($paper));
    }

    public function testCarriesOverTheAltAndTheLicense(): void
    {
        $paper = $this->makePaperWithCurrent([
            'graphical_abstract_file' => 'graphical_abstract.png',
            'graphical_abstract_alt' => ' A chart ',
            'graphical_abstract_license' => 'CC BY 4.0',
        ]);

        self::assertSame('A chart', $this->callGetStoredCurrentStringToJson($paper, 'graphical_abstract_alt'));
        self::assertSame('CC BY 4.0', $this->callGetStoredCurrentStringToJson($paper, 'graphical_abstract_license'));
    }

    public function testReturnsNullForAnAbsentOrNonScalarAltOrLicense(): void
    {
        $paper = $this->makePaperWithCurrent([
            'graphical_abstract_file' => 'graphical_abstract.png',
            'graphical_abstract_alt' => ['unexpected'],
        ]);

        self::assertNull($this->callGetStoredCurrentStringToJson($paper, 'graphical_abstract_alt'));
        self::assertNull($this->callGetStoredCurrentStringToJson($paper, 'graphical_abstract_license'));
    }

    public function testToJsonExportsTheThreeKeys(): void
    {
        $source = (string)file_get_contents(dirname(APPLICATION_PATH) . '/library/Episciences/Paper.php');

        foreach (['graphical_abstract_file', 'graphical_abstract_alt', 'graphical_abstract_license'] as $key) {
            self::assertStringContainsString("'$key' => \$this->get", $source, "toJson() must export $key");
        }
    }
}
