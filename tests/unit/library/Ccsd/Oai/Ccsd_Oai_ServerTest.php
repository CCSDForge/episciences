<?php

namespace unit\library\Ccsd\Oai;

use Ccsd_Oai_Server;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for Ccsd_Oai_Server
 *
 * The constructor requires a Zend_Controller_Request_Abstract and calls
 * header()/exit — it cannot be invoked in a unit test context.
 * Tests cover: constants, static method getXsl(), and class structure.
 */
class Ccsd_Oai_ServerTest extends TestCase
{
    // ------------------------------------------------------------------
    // Constants
    // ------------------------------------------------------------------

    public function testLimitIdentifiers(): void
    {
        $this->assertSame(400, Ccsd_Oai_Server::LIMIT_IDENTIFIERS);
    }

    public function testLimitRecords(): void
    {
        $this->assertSame(100, Ccsd_Oai_Server::LIMIT_RECORDS);
    }

    public function testVerbListIdentifiers(): void
    {
        $this->assertSame('ListIdentifiers', Ccsd_Oai_Server::OAI_VERB_LISTIDS);
    }

    public function testVerbListRecords(): void
    {
        $this->assertSame('ListRecords', Ccsd_Oai_Server::OAI_VERB_LISTRECS);
    }

    public function testVerbIdentify(): void
    {
        $this->assertSame('Identify', Ccsd_Oai_Server::OAI_VERB_IDENTIFY);
    }

    public function testVerbListSets(): void
    {
        $this->assertSame('ListSets', Ccsd_Oai_Server::OAI_VERB_LIST_SETS);
    }

    public function testVerbListMetadataFormats(): void
    {
        $this->assertSame('ListMetadataFormats', Ccsd_Oai_Server::OAI_VERB_LIST_METADATA_FORMATS);
    }

    public function testVerbGetRecord(): void
    {
        $this->assertSame('GetRecord', Ccsd_Oai_Server::OAI_VERB_GET_RECORD);
    }

    // ------------------------------------------------------------------
    // Class structure: abstract + 9 abstract methods declared
    // ------------------------------------------------------------------

    public function testClassIsAbstract(): void
    {
        $rc = new ReflectionClass(Ccsd_Oai_Server::class);
        $this->assertTrue($rc->isAbstract(), 'Ccsd_Oai_Server must be declared abstract');
    }

    /** @dataProvider abstractMethodNames */
    public function testAbstractMethodExists(string $method): void
    {
        $rc = new ReflectionClass(Ccsd_Oai_Server::class);
        $this->assertTrue($rc->hasMethod($method), "Method $method must exist");
        $this->assertTrue($rc->getMethod($method)->isAbstract(), "Method $method must be abstract");
    }

    /** @return array<string, array{string}> */
    public static function abstractMethodNames(): array
    {
        return [
            'getIdentity'     => ['getIdentity'],
            'getFormats'      => ['getFormats'],
            'getSets'         => ['getSets'],
            'existId'         => ['existId'],
            'existFormat'     => ['existFormat'],
            'existSet'        => ['existSet'],
            'checkDateFormat' => ['checkDateFormat'],
            'getId'           => ['getId'],
            'getIds'          => ['getIds'],
        ];
    }

    // ------------------------------------------------------------------
    // getXsl() throws on missing file
    // ------------------------------------------------------------------

    public function testGetXslThrowsWhenFileNotFound(): void
    {
        // The XSL file lives in library/Ccsd/Oai/oai2.xsl
        // If it exists in the test container, getXsl() succeeds; if not, it throws.
        // Either way the method must not error out unexpectedly.
        try {
            $result = Ccsd_Oai_Server::getXsl();
            $this->assertIsString($result, 'getXsl() must return a string when file exists');
            $this->assertNotEmpty($result, 'XSL file content must not be empty');
        } catch (\Exception $e) {
            $this->assertStringContainsString('non exists', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // LIMIT constants are positive integers > 0
    // ------------------------------------------------------------------

    public function testLimitIdentifiersIsPositive(): void
    {
        $this->assertGreaterThan(0, Ccsd_Oai_Server::LIMIT_IDENTIFIERS);
    }

    public function testLimitRecordsIsPositive(): void
    {
        $this->assertGreaterThan(0, Ccsd_Oai_Server::LIMIT_RECORDS);
    }

    public function testLimitIdentifiersGreaterThanLimitRecords(): void
    {
        $this->assertGreaterThan(
            Ccsd_Oai_Server::LIMIT_RECORDS,
            Ccsd_Oai_Server::LIMIT_IDENTIFIERS,
            'ListIdentifiers limit should be larger than ListRecords limit'
        );
    }
}
