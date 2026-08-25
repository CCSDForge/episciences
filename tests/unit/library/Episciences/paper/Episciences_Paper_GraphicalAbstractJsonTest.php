<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for the graphical_abstract_file key of the JSON v2 paper export.
 *
 * The value is carried over from the stored PAPERS.DOCUMENT column, which
 * AdministrategraphabstractController writes to directly (JSON_SET on upload,
 * JSON_REMOVE on delete). It must be null, not '', when there is no file.
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
}
