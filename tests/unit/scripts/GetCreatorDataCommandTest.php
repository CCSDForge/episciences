<?php

declare(strict_types=1);

namespace unit\scripts;

use Episciences_Hal_TeiCacheManager;
use Episciences_Paper;
use Episciences_Paper_Authors_EnrichmentService;
use Episciences_Paper_Authors_Repository;
use Episciences_Repositories;
use GetCreatorDataCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Input\InputDefinition;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

require_once __DIR__ . '/../../../scripts/GetCreatorDataCommand.php';

/**
 * Unit tests for GetCreatorDataCommand.
 *
 * Focuses on command metadata and HAL TEI enrichment DB update behavior (no bootstrap, no live DB, no HTTP).
 */
class GetCreatorDataCommandTest extends TestCase
{
    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    protected function tearDown(): void
    {
        if ($this->previousAdapter !== null) {
            Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
            $this->previousAdapter = null;
        }
        Episciences_Paper_Authors_Repository::setCachePool(new ArrayAdapter());
        Episciences_Hal_TeiCacheManager::setCachePool(null);
        Episciences_Paper_Authors_EnrichmentService::resetCorrections();
    }

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:creators', (new GetCreatorDataCommand())->getName());
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testCommandHasNoCacheOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('no-cache'));
        $this->assertFalse($definition->getOption('no-cache')->acceptValue(), 'no-cache must be a flag');
    }

    public function testCommandHasDoiOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('doi'));
        $this->assertTrue($definition->getOption('doi')->isValueOptional(), 'doi must accept an optional value');
    }

    public function testCommandHasPaperidOption(): void
    {
        $definition = (new GetCreatorDataCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('paperid'));
        $this->assertTrue($definition->getOption('paperid')->isValueOptional(), 'paperid must accept an optional value');
    }

    public function testEnrichFromHalTeiUpdatesByPaperIdWithoutRestrictingToFirstAuthorRow(): void
    {
        $adapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$adapter instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }

        $paperId = 182;
        $identifier = 'hal-00000182';
        $version = 1;

        // Duplicate rows in `authors` for the same paperid: first row already enriched, last row still stale
        $enrichedJson = json_encode([
            0 => ['fullname' => 'C. Hooley', 'given' => 'C.', 'family' => 'Hooley'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
        $staleJson = json_encode([
            0 => ['fullname' => 'C Hooley', 'given' => 'C', 'family' => 'Hooley'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);

        $authorsCache = new ArrayAdapter();
        $authorsItem = $authorsCache->getItem('authors_paper_' . $paperId);
        $authorsItem->set([
            10 => ['idauthors' => 10, 'paperid' => $paperId, 'authors' => $enrichedJson],
            50 => ['idauthors' => 50, 'paperid' => $paperId, 'authors' => $staleJson],
        ]);
        $authorsCache->save($authorsItem);
        Episciences_Paper_Authors_Repository::setCachePool($authorsCache);

        $teiXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0">
  <text>
    <body>
      <listBibl>
        <biblFull>
          <titleStmt>
            <author role="aut">
              <persName>
                <forename type="first">C</forename>
                <surname>Hooley</surname>
              </persName>
            </author>
          </titleStmt>
        </biblFull>
      </listBibl>
    </body>
  </text>
</TEI>
XML;

        $teiCache = new ArrayAdapter();
        $teiItem = $teiCache->getItem('hal-tei-' . Episciences_Repositories::HAL_REPO_ID . '-' . $identifier . '-v' . $version . '.xml');
        $teiItem->set($teiXml);
        $teiCache->save($teiItem);
        Episciences_Hal_TeiCacheManager::setCachePool($teiCache);

        $this->previousAdapter = $adapter;
        $capturedWhere = null;
        $capturedValues = null;

        $dbMock = $this->getMockBuilder($adapter::class)
            ->setConstructorArgs([$adapter->getConfig()])
            ->onlyMethods(['update'])
            ->getMock();
        $dbMock->expects(self::once())
            ->method('update')
            ->with(
                T_PAPER_AUTHORS,
                self::callback(static function (array $values) use (&$capturedValues): bool {
                    $capturedValues = $values;
                    return true;
                }),
                self::callback(static function ($where) use (&$capturedWhere): bool {
                    $capturedWhere = $where;
                    return true;
                })
            )
            ->willReturn(1);
        Zend_Db_Table_Abstract::setDefaultAdapter($dbMock);

        $paper = new Episciences_Paper();
        $paper->setPaperid($paperId);

        $command = new GetCreatorDataCommand();
        $method = new ReflectionMethod($command, 'enrichFromHalTei');
        $method->invoke($command, $paper, $identifier, $version, false);

        self::assertSame(['paperid = ?' => $paperId], $capturedWhere, 'Update must target all rows for paperid, not only idauthors of the first row');
        self::assertSame($enrichedJson, $capturedValues['authors'] ?? null);
    }
}
