<?php

declare(strict_types=1);

namespace unit\library\Episciences\Hal;

use DOMDocument;
use DOMXPath;
use Episciences\Hal\OaiTeiResponse;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Hal\OaiTeiResponse
 */
final class OaiTeiResponseTest extends TestCase
{
    private const ENVELOPE_START = '<?xml version="1.0" encoding="utf-8"?>'
        . '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord xmlns:tei="http://www.tei-c.org/ns/1.0">'
        . '<record><metadata>';
    private const ENVELOPE_END = '</metadata></record></GetRecord></OAI-PMH>';

    public function testPrefixedTeiIsReturnedWithItsRootInTheDefaultNamespace(): void
    {
        $response = OaiTeiResponse::parse(self::ENVELOPE_START
            . '<tei:TEI><tei:text><tei:body><tei:listBibl><tei:biblFull>'
            . '<tei:publicationStmt><tei:availability><tei:licence target="https://creativecommons.org/licenses/by/4.0/"/></tei:availability></tei:publicationStmt>'
            . '</tei:biblFull></tei:listBibl></tei:body></tei:text></tei:TEI>'
            . self::ENVELOPE_END);

        self::assertTrue($response->isTei());
        $tei = simplexml_load_string((string)$response->tei);
        self::assertNotFalse($tei);
        self::assertSame(
            'https://creativecommons.org/licenses/by/4.0/',
            (string)$tei->text->body->listBibl->biblFull->publicationStmt->availability->licence['target']
        );
    }

    public function testAttributesAndElementsOfOtherNamespacesAreKept(): void
    {
        $response = OaiTeiResponse::parse(self::ENVELOPE_START
            . '<tei:TEI xmlns:hal="http://hal.archives-ouvertes.fr/"><tei:teiHeader>'
            . '<tei:title xml:lang="fr">Titre</tei:title><hal:extra hal:kind="k">x</hal:extra>'
            . '</tei:teiHeader></tei:TEI>'
            . self::ENVELOPE_END);

        $xpath = self::xpath((string)$response->tei);

        self::assertSame('fr', $xpath->evaluate('string(//tei:title/@xml:lang)'));
        self::assertSame('k', $xpath->evaluate('string(//hal:extra/@hal:kind)'));
    }

    public function testTeiAlreadyInTheDefaultNamespaceIsRead(): void
    {
        $response = OaiTeiResponse::parse('<TEI xmlns="http://www.tei-c.org/ns/1.0"><text/></TEI>');

        self::assertTrue($response->isTei());
        self::assertSame(1, self::xpath((string)$response->tei)->query('/tei:TEI/tei:text')?->length);
    }

    public function testOaiErrorIsReportedWithItsCode(): void
    {
        $response = OaiTeiResponse::parse('<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/">'
            . '<error code="idDoesNotExist">unknown identifier</error></OAI-PMH>');

        self::assertFalse($response->isTei());
        self::assertStringContainsString('idDoesNotExist', $response->error);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notATeiProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'HTML page' => ['<!DOCTYPE html><html><body>Maintenance</body></html>'];
        yield 'JSON' => ['{"response":{"docs":[]}}'];
        yield 'truncated' => [self::ENVELOPE_START . '<tei:TEI><tei:text>'];
        yield 'TEI outside the TEI namespace' => ['<TEI><text/></TEI>'];
        yield 'record without TEI' => [self::ENVELOPE_START . self::ENVELOPE_END];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notATeiProvider')]
    public function testResponseWithoutTeiIsNotATei(string $body): void
    {
        $response = OaiTeiResponse::parse($body);

        self::assertFalse($response->isTei());
        self::assertNull($response->tei);
        self::assertNotSame('', $response->error);
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('tei', OaiTeiResponse::TEI_NS);
        $xpath->registerNamespace('hal', 'http://hal.archives-ouvertes.fr/');

        return $xpath;
    }
}
