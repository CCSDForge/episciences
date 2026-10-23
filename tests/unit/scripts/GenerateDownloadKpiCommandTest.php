<?php
declare(strict_types=1);

namespace unit\scripts;

use GenerateDownloadKpiCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/GenerateDownloadKpiCommand.php';

/**
 * Unit tests for GenerateDownloadKpiCommand.
 *
 * All tests are pure: no bootstrap, no database, no filesystem side-effects
 * (except writeJson tests which use sys_get_temp_dir()).
 */
class GenerateDownloadKpiCommandTest extends TestCase
{
    private GenerateDownloadKpiCommand $command;

    protected function setUp(): void
    {
        $this->command = new GenerateDownloadKpiCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('stats:download-kpi', $this->command->getName());
    }

    public function testCommandHasOutputOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('output'));
        $this->assertTrue(
            $definition->getOption('output')->isValueRequired(),
            '--output must require a value'
        );
    }

    public function testCommandHasRvcodeOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('rvcode'));
        $this->assertTrue(
            $definition->getOption('rvcode')->isValueRequired(),
            '--rvcode must require a value'
        );
    }

    public function testCommandHasPrettyOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('pretty'));
        $this->assertFalse(
            $definition->getOption('pretty')->acceptValue(),
            '--pretty must be a flag (no value)'
        );
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse(
            $definition->getOption('dry-run')->acceptValue(),
            '--dry-run must be a flag (no value)'
        );
    }

    // -------------------------------------------------------------------------
    // resolveOutputPath()
    // -------------------------------------------------------------------------

    public function testResolveOutputPath_defaultWhenCustomIsNull(): void
    {
        $path = $this->command->resolveOutputPath('/var/www/htdocs/application', null);
        $this->assertSame('/var/www/htdocs/data/kpi_downloads.json', $path);
    }

    public function testResolveOutputPath_defaultWhenCustomIsEmpty(): void
    {
        $path = $this->command->resolveOutputPath('/srv/app/application', '');
        $this->assertSame('/srv/app/data/kpi_downloads.json', $path);
    }

    public function testResolveOutputPath_customPathOverridesDefault(): void
    {
        $path = $this->command->resolveOutputPath('/app/application', '/tmp/my_output.json');
        $this->assertSame('/tmp/my_output.json', $path);
    }

    public function testResolveOutputPath_noDoubleSlash(): void
    {
        $path = $this->command->resolveOutputPath('/var/www/htdocs/application', null);
        $this->assertStringNotContainsString('//', $path);
    }

    // -------------------------------------------------------------------------
    // aggregatePapers()
    // -------------------------------------------------------------------------

    public function testAggregatePapers_singleVersion(): void
    {
        $rows = [
            [
                'PAPERID'          => 1,
                'DOCID'            => 10,
                'DOI'              => '10.1000/abc',
                'RVID'             => 5,
                'RVCODE'           => 'epiga',
                'JOURNAL_NAME'     => 'Epiga Journal',
                'PUBLICATION_DATE' => '2024-03-15 00:00:00',
            ],
        ];

        $result = $this->command->aggregatePapers($rows);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertSame('10.1000/abc', $result[1]['doi']);
        $this->assertSame(1, $result[1]['paperid']);
        $this->assertSame(5, $result[1]['rvid']);
        $this->assertSame('epiga', $result[1]['rvcode']);
        $this->assertSame('2024-03-15', $result[1]['publication_date']);
    }

    public function testAggregatePapers_multipleVersionsSamesPaperIdMerged(): void
    {
        $rows = [
            [
                'PAPERID'          => 42,
                'DOCID'            => 100,
                'DOI'              => '10.1000/xyz',
                'RVID'             => 3,
                'RVCODE'           => 'dmtcs',
                'JOURNAL_NAME'     => 'DMTCS',
                'PUBLICATION_DATE' => '2023-06-01 00:00:00',
            ],
            [
                'PAPERID'          => 42,
                'DOCID'            => 101,
                'DOI'              => '10.1000/xyz',
                'RVID'             => 3,
                'RVCODE'           => 'dmtcs',
                'JOURNAL_NAME'     => 'DMTCS',
                'PUBLICATION_DATE' => '2023-01-10 00:00:00', // earlier → should win
            ],
        ];

        $result = $this->command->aggregatePapers($rows);

        $this->assertCount(1, $result);
        $this->assertSame('2023-01-10', $result[42]['publication_date'], 'Earliest date must be kept');
    }

    public function testAggregatePapers_laterVersionDoesNotReplaceEarlierDate(): void
    {
        $rows = [
            ['PAPERID' => 7, 'DOCID' => 1, 'DOI' => '10.x/y', 'RVID' => 1, 'RVCODE' => 'j', 'JOURNAL_NAME' => 'J', 'PUBLICATION_DATE' => '2022-05-01 00:00:00'],
            ['PAPERID' => 7, 'DOCID' => 2, 'DOI' => '10.x/y', 'RVID' => 1, 'RVCODE' => 'j', 'JOURNAL_NAME' => 'J', 'PUBLICATION_DATE' => '2024-12-31 00:00:00'],
        ];

        $result = $this->command->aggregatePapers($rows);

        $this->assertSame('2022-05-01', $result[7]['publication_date']);
    }

    public function testAggregatePapers_skipsRowsWithEmptyDoi(): void
    {
        $rows = [
            ['PAPERID' => 1, 'DOCID' => 10, 'DOI' => '', 'RVID' => 1, 'RVCODE' => 'j', 'JOURNAL_NAME' => 'J', 'PUBLICATION_DATE' => '2024-01-01'],
            ['PAPERID' => 2, 'DOCID' => 11, 'DOI' => null, 'RVID' => 1, 'RVCODE' => 'j', 'JOURNAL_NAME' => 'J', 'PUBLICATION_DATE' => '2024-01-01'],
        ];

        $result = $this->command->aggregatePapers($rows);

        $this->assertCount(0, $result);
    }

    public function testAggregatePapers_nullPublicationDate(): void
    {
        $rows = [
            ['PAPERID' => 5, 'DOCID' => 50, 'DOI' => '10.x/z', 'RVID' => 2, 'RVCODE' => 'r', 'JOURNAL_NAME' => 'R', 'PUBLICATION_DATE' => null],
        ];

        $result = $this->command->aggregatePapers($rows);

        $this->assertNull($result[5]['publication_date']);
    }

    // -------------------------------------------------------------------------
    // aggregateStats()
    // -------------------------------------------------------------------------

    public function testAggregateStats_downloadsAccumulated(): void
    {
        $rows = [
            ['PAPERID' => 1, 'CONSULT' => 'file', 'YEAR' => '2024', 'total' => 30],
            ['PAPERID' => 1, 'CONSULT' => 'file', 'YEAR' => '2025', 'total' => 20],
        ];

        $result = $this->command->aggregateStats($rows);

        $this->assertSame(30, $result[1]['file']['2024']);
        $this->assertSame(20, $result[1]['file']['2025']);
    }

    public function testAggregateStats_pageViewsAccumulated(): void
    {
        $rows = [
            ['PAPERID' => 2, 'CONSULT' => 'notice', 'YEAR' => '2023', 'total' => 100],
        ];

        $result = $this->command->aggregateStats($rows);

        $this->assertSame(100, $result[2]['notice']['2023']);
    }

    public function testAggregateStats_multipleYearsAndConsults(): void
    {
        $rows = [
            ['PAPERID' => 3, 'CONSULT' => 'file',   'YEAR' => '2024', 'total' => 10],
            ['PAPERID' => 3, 'CONSULT' => 'notice',  'YEAR' => '2024', 'total' => 50],
            ['PAPERID' => 3, 'CONSULT' => 'file',   'YEAR' => '2025', 'total' => 15],
        ];

        $result = $this->command->aggregateStats($rows);

        $this->assertSame(10, $result[3]['file']['2024']);
        $this->assertSame(50, $result[3]['notice']['2024']);
        $this->assertSame(15, $result[3]['file']['2025']);
    }

    public function testAggregateStats_skipsRowsWithMissingKeys(): void
    {
        $rows = [
            ['PAPERID' => 0, 'CONSULT' => 'file', 'YEAR' => '2024', 'total' => 5], // PAPERID=0 → skip
            ['PAPERID' => 1, 'CONSULT' => '',     'YEAR' => '2024', 'total' => 5], // empty consult → skip
            ['PAPERID' => 1, 'CONSULT' => 'file', 'YEAR' => '',     'total' => 5], // empty year → skip
        ];

        $result = $this->command->aggregateStats($rows);

        $this->assertCount(0, $result);
    }

    // -------------------------------------------------------------------------
    // aggregateGeoStats()
    // -------------------------------------------------------------------------

    public function testAggregateGeoStats_downloadsCountedSeparately(): void
    {
        $rows = [
            ['PAPERID' => 1, 'CONSULT' => 'file', 'COUNTRY' => 'FR', 'CONTINENT' => 'EU', 'total' => 80],
        ];

        $result = $this->command->aggregateGeoStats($rows);

        $this->assertSame(80, $result[1]['FR']['downloads']);
        $this->assertSame(0,  $result[1]['FR']['page_views']);
        $this->assertSame('EU', $result[1]['FR']['continent']);
    }

    public function testAggregateGeoStats_pageViewsCountedSeparately(): void
    {
        $rows = [
            ['PAPERID' => 2, 'CONSULT' => 'notice', 'COUNTRY' => 'DE', 'CONTINENT' => 'EU', 'total' => 150],
        ];

        $result = $this->command->aggregateGeoStats($rows);

        $this->assertSame(0,   $result[2]['DE']['downloads']);
        $this->assertSame(150, $result[2]['DE']['page_views']);
    }

    public function testAggregateGeoStats_bothConsultsForSameCountry(): void
    {
        $rows = [
            ['PAPERID' => 3, 'CONSULT' => 'file',   'COUNTRY' => 'US', 'CONTINENT' => 'NA', 'total' => 200],
            ['PAPERID' => 3, 'CONSULT' => 'notice',  'COUNTRY' => 'US', 'CONTINENT' => 'NA', 'total' => 500],
        ];

        $result = $this->command->aggregateGeoStats($rows);

        $this->assertSame(200, $result[3]['US']['downloads']);
        $this->assertSame(500, $result[3]['US']['page_views']);
        $this->assertSame('NA', $result[3]['US']['continent']);
    }

    public function testAggregateGeoStats_multipleCountries(): void
    {
        $rows = [
            ['PAPERID' => 4, 'CONSULT' => 'file', 'COUNTRY' => 'FR', 'CONTINENT' => 'EU', 'total' => 10],
            ['PAPERID' => 4, 'CONSULT' => 'file', 'COUNTRY' => 'JP', 'CONTINENT' => 'AS', 'total' => 5],
        ];

        $result = $this->command->aggregateGeoStats($rows);

        $this->assertCount(2, $result[4]);
        $this->assertArrayHasKey('FR', $result[4]);
        $this->assertArrayHasKey('JP', $result[4]);
        $this->assertSame('AS', $result[4]['JP']['continent']);
    }

    public function testAggregateGeoStats_unknownCountryStoredAsEmptyString(): void
    {
        $rows = [
            ['PAPERID' => 5, 'CONSULT' => 'file', 'COUNTRY' => '', 'CONTINENT' => '', 'total' => 30],
        ];

        $result = $this->command->aggregateGeoStats($rows);

        $this->assertArrayHasKey('', $result[5]);
        $this->assertSame(30, $result[5]['']['downloads']);
    }

    public function testAggregateGeoStats_skipsRowsWithInvalidPaperid(): void
    {
        $rows = [
            ['PAPERID' => 0, 'CONSULT' => 'file', 'COUNTRY' => 'FR', 'CONTINENT' => 'EU', 'total' => 10],
        ];

        $result = $this->command->aggregateGeoStats($rows);

        $this->assertCount(0, $result);
    }

    public function testAggregateGeoStats_countriesSortedAlphabetically(): void
    {
        $rows = [
            ['PAPERID' => 6, 'CONSULT' => 'file', 'COUNTRY' => 'ZA', 'CONTINENT' => 'AF', 'total' => 1],
            ['PAPERID' => 6, 'CONSULT' => 'file', 'COUNTRY' => 'AU', 'CONTINENT' => 'OC', 'total' => 2],
            ['PAPERID' => 6, 'CONSULT' => 'file', 'COUNTRY' => 'FR', 'CONTINENT' => 'EU', 'total' => 3],
        ];

        $result = $this->command->aggregateGeoStats($rows);
        $keys   = array_keys($result[6]);

        $this->assertSame(['AU', 'FR', 'ZA'], $keys);
    }

    // -------------------------------------------------------------------------
    // buildJournalMap()
    // -------------------------------------------------------------------------

    public function testBuildJournalMap_groupsByJournal(): void
    {
        $papers = [
            10 => ['doi' => '10.x/a', 'paperid' => 10, 'rvid' => 1, 'rvcode' => 'j1', 'journal_name' => 'Journal 1', 'publication_date' => '2024-01-01'],
            20 => ['doi' => '10.x/b', 'paperid' => 20, 'rvid' => 2, 'rvcode' => 'j2', 'journal_name' => 'Journal 2', 'publication_date' => '2024-02-01'],
        ];

        $result = $this->command->buildJournalMap($papers, [], []);

        $this->assertArrayHasKey('j1', $result);
        $this->assertArrayHasKey('j2', $result);
        $this->assertCount(1, $result['j1']['papers']);
        $this->assertCount(1, $result['j2']['papers']);
    }

    public function testBuildJournalMap_paperWithNoStatsHasZeroTotals(): void
    {
        $papers = [
            5 => ['doi' => '10.x/z', 'paperid' => 5, 'rvid' => 1, 'rvcode' => 'jx', 'journal_name' => 'JX', 'publication_date' => null],
        ];

        $result = $this->command->buildJournalMap($papers, [], []);

        $paper = $result['jx']['papers'][0];
        $this->assertSame(0, $paper['downloads']['total']);
        $this->assertSame(0, $paper['page_views']['total']);
        $this->assertSame([], $paper['downloads']['by_year']);
        $this->assertSame([], $paper['page_views']['by_year']);
    }

    public function testBuildJournalMap_statsCorrectlyMerged(): void
    {
        $papers = [
            99 => ['doi' => '10.x/q', 'paperid' => 99, 'rvid' => 1, 'rvcode' => 'myj', 'journal_name' => 'MyJ', 'publication_date' => '2023-01-01'],
        ];
        $stats = [
            99 => [
                'file'   => ['2023' => 40, '2024' => 60],
                'notice' => ['2023' => 200],
            ],
        ];

        $result = $this->command->buildJournalMap($papers, $stats, []);

        $paper = $result['myj']['papers'][0];
        $this->assertSame(100, $paper['downloads']['total']);
        $this->assertSame(200, $paper['page_views']['total']);
        $this->assertSame(['2023' => 40, '2024' => 60], $paper['downloads']['by_year']);
    }

    public function testBuildJournalMap_geoDataMergedIntoPaper(): void
    {
        $papers = [
            10 => ['doi' => '10.x/g', 'paperid' => 10, 'rvid' => 1, 'rvcode' => 'gj', 'journal_name' => 'GJ', 'publication_date' => null],
        ];
        $geoStats = [
            10 => [
                'FR' => ['continent' => 'EU', 'downloads' => 50, 'page_views' => 200],
                'US' => ['continent' => 'NA', 'downloads' => 30, 'page_views' => 100],
            ],
        ];

        $result = $this->command->buildJournalMap($papers, [], $geoStats);

        $geo = $result['gj']['papers'][0]['geo'];
        $this->assertIsArray($geo);
        $this->assertArrayHasKey('FR', $geo);
        $this->assertSame(50, $geo['FR']['downloads']);
        $this->assertSame('EU', $geo['FR']['continent']);
    }

    public function testBuildJournalMap_paperWithNoGeoHasEmptyGeo(): void
    {
        $papers = [
            7 => ['doi' => '10.x/n', 'paperid' => 7, 'rvid' => 1, 'rvcode' => 'nj', 'journal_name' => 'NJ', 'publication_date' => null],
        ];

        $result = $this->command->buildJournalMap($papers, [], []);

        $geo = $result['nj']['papers'][0]['geo'];
        // geo is (object)[] when no data — JSON encodes to {} not []
        $this->assertEmpty((array) $geo);
    }

    public function testBuildJournalMap_isSortedByRvcode(): void
    {
        $papers = [
            1 => ['doi' => '10.x/1', 'paperid' => 1, 'rvid' => 1, 'rvcode' => 'zzz', 'journal_name' => 'Z', 'publication_date' => null],
            2 => ['doi' => '10.x/2', 'paperid' => 2, 'rvid' => 2, 'rvcode' => 'aaa', 'journal_name' => 'A', 'publication_date' => null],
        ];

        $result = $this->command->buildJournalMap($papers, [], []);
        $keys   = array_keys($result);

        $this->assertSame('aaa', $keys[0]);
        $this->assertSame('zzz', $keys[1]);
    }

    // -------------------------------------------------------------------------
    // buildPayload()
    // -------------------------------------------------------------------------

    public function testBuildPayload_totalsPapers(): void
    {
        $journalMap = [
            'j1' => ['rvid' => 1, 'name' => 'J1', 'papers' => [['doi' => 'a'], ['doi' => 'b']]],
            'j2' => ['rvid' => 2, 'name' => 'J2', 'papers' => [['doi' => 'c']]],
        ];

        $payload = $this->command->buildPayload($journalMap, '2026-04-06T00:00:00+00:00');

        $this->assertSame(3, $payload['total_papers']);
        $this->assertSame(2, $payload['total_journals']);
        $this->assertSame('2026-04-06T00:00:00+00:00', $payload['generated_at']);
    }

    public function testBuildPayload_papersCountAddedToEachJournal(): void
    {
        $journalMap = [
            'jx' => ['rvid' => 1, 'name' => 'JX', 'papers' => [['doi' => 'a'], ['doi' => 'b'], ['doi' => 'c']]],
        ];

        $payload = $this->command->buildPayload($journalMap, '2026-04-06T00:00:00+00:00');

        $this->assertSame(3, $payload['journals']['jx']['papers_count']);
    }

    public function testBuildPayload_emptyJournalMap(): void
    {
        $payload = $this->command->buildPayload([], '2026-04-06T00:00:00+00:00');

        $this->assertSame(0, $payload['total_papers']);
        $this->assertSame(0, $payload['total_journals']);
        $this->assertSame([], $payload['journals']);
    }

    // -------------------------------------------------------------------------
    // writeJson()
    // -------------------------------------------------------------------------

    public function testWriteJson_writesCompactJson(): void
    {
        $path = sys_get_temp_dir() . '/kpi_test_compact_' . uniqid() . '.json';
        $data = ['key' => 'value', 'num' => 42];

        $this->command->writeJson($data, $path, false);

        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringNotContainsString("\n", $content, 'Compact JSON must not contain newlines');
        $decoded = json_decode($content, true);
        $this->assertSame(42, $decoded['num']);

        unlink($path);
    }

    public function testWriteJson_writesPrettyJson(): void
    {
        $path = sys_get_temp_dir() . '/kpi_test_pretty_' . uniqid() . '.json';
        $data = ['journals' => ['j1' => ['rvid' => 1]]];

        $this->command->writeJson($data, $path, true);

        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString("\n", $content, 'Pretty JSON must contain newlines');

        unlink($path);
    }

    public function testWriteJson_createsIntermediateDirectories(): void
    {
        $dir  = sys_get_temp_dir() . '/kpi_nested_' . uniqid();
        $path = $dir . '/sub/output.json';

        $this->command->writeJson(['ok' => true], $path, false);

        $this->assertFileExists($path);

        unlink($path);
        rmdir($dir . '/sub');
        rmdir($dir);
    }

    public function testWriteJson_throwsRuntimeExceptionOnInvalidPath(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->command->writeJson(['x' => 1], '/nonexistent_root_dir_xyz/sub/file.json', false);
    }

    public function testWriteJson_geoEmptyObjectEncodesAsObject(): void
    {
        $path = sys_get_temp_dir() . '/kpi_geo_' . uniqid() . '.json';
        $data = ['geo' => (object) []];

        $this->command->writeJson($data, $path, false);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('"geo":{}', $content, 'Empty geo must encode as {} not []');

        unlink($path);
    }
}
