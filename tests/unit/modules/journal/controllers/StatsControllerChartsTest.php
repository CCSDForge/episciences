<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use StatsController;

/**
 * Data of the charts of the statistics page (StatsController).
 */
class StatsControllerChartsTest extends TestCase
{
    private const LABELS = [
        'submitted' => 'Submissions',
        'accepted' => 'Accepted',
        'refused' => 'Refused',
        'published' => 'Published',
        'acceptanceDelay' => 'Submission → acceptance',
        'publicationDelay' => 'Submission → publication',
    ];

    /**
     * The repositories known to the charts, whatever the database or the other tests registered
     */
    private const METADATA_SOURCES = [
        0 => ['id' => '0', 'name' => 'Episciences', 'type' => 'repository'],
        1 => ['id' => '1', 'name' => 'HAL', 'type' => 'repository'],
    ];

    private bool $hadMetadataSources;
    /** @var mixed */
    private $originalMetadataSources;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    protected function setUp(): void
    {
        $this->hadMetadataSources = \Zend_Registry::isRegistered('metadataSources');

        if ($this->hadMetadataSources) {
            $this->originalMetadataSources = \Zend_Registry::get('metadataSources');
        }

        \Zend_Registry::set('metadataSources', self::METADATA_SOURCES);
        self::resetRepositoriesCache();
    }

    protected function tearDown(): void
    {
        if ($this->hadMetadataSources) {
            \Zend_Registry::set('metadataSources', $this->originalMetadataSources);
        } else {
            \Zend_Registry::getInstance()->offsetUnset('metadataSources');
        }

        self::resetRepositoriesCache();
    }

    private static function resetRepositoriesCache(): void
    {
        $cache = new \ReflectionProperty(\Episciences_Repositories::class, '_repositories');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    private static function details(): array
    {
        return [
            'nbSubmissions' => [
                'submissionsByYear' => [
                    2022 => ['submissions' => 3, 'imported' => 0, 'accepted' => 0, 'refused' => 0, 'published' => 0],
                    2023 => ['submissions' => 450, 'imported' => 423, 'accepted' => 11, 'refused' => 8, 'published' => 5],
                    2024 => ['submissions' => 29, 'imported' => 0, 'accepted' => 13, 'refused' => 12, 'published' => 15],
                ],
                'submissionsByRepo' => [
                    2022 => ['HAL' => ['submissions' => 3], 'Episciences' => ['submissions' => 0]],
                    2023 => ['HAL' => ['submissions' => 450], 'Episciences' => ['submissions' => 3]],
                    2024 => ['HAL' => ['submissions' => 29], 'Episciences' => ['submissions' => 6]],
                ],
            ],
            'submissionAcceptanceTime' => [
                2023 => ['delay' => ['value' => 57, 'unit' => 'DAY']],
                2024 => ['delay' => ['value' => 162]],
            ],
            'submissionPublicationTime' => [
                2023 => ['delay' => ['value' => 234]],
            ],
            'submissionAcceptanceTimeMedian' => [
                2023 => ['delay' => ['value' => 41, 'count' => 6]],
                2024 => ['delay' => ['value' => 150, 'count' => 1]],
            ],
            'submissionPublicationTimeMedian' => [
                2023 => ['delay' => ['value' => 210]],
            ],
        ];
    }

    private static function charts(): array
    {
        return StatsController::buildChartsData(self::details(), [2022, 2023, 2024], [2023 => ['HAL' => 423]], self::LABELS);
    }

    public function testBuildChartsData_ByYear_ImportedPapersLeftOut(): void
    {
        $charts = self::charts();

        $this->assertSame(['2022', '2023', '2024'], $charts['years']);
        $this->assertSame([
            ['label' => 'Submissions', 'data' => [3, 27, 29]],
            ['label' => 'Accepted', 'data' => [0, 11, 13]],
            ['label' => 'Refused', 'data' => [0, 8, 12]],
            ['label' => 'Published', 'data' => [0, 5, 15]],
        ], $charts['byYear']['datasets']);
        $this->assertSame(['2023' => 423], $charts['byYear']['imported']);
    }

    /**
     * Regression: the values of a year without papers were skipped, so that the next values were shown a year too early
     */
    public function testBuildChartsData_ValuesStayInLineWithTheYears(): void
    {
        $charts = self::charts();

        foreach ($charts['byYear']['datasets'] as $dataset) {
            $this->assertCount(count($charts['years']), $dataset['data'], $dataset['label']);
        }

        // nothing published in 2022: the 5 papers published in 2023 stay in 2023
        $published = array_combine($charts['years'], $charts['byYear']['datasets'][3]['data']);
        $this->assertSame(0, $published['2022']);
        $this->assertSame(5, $published['2023']);
    }

    public function testBuildChartsData_ByRepository_ImportedPapersLeftOut(): void
    {
        $byRepo = array_column(self::charts()['byRepo']['datasets'], 'data', 'label');

        $this->assertSame([3, 27, 29], $byRepo['HAL']);
        $this->assertTrue(self::charts()['byRepo']['stacked']);
    }

    /**
     * The local repository holds the temporary versions of papers already counted in their repository
     */
    public function testBuildChartsData_ByRepository_LocalRepositoryLeftOut(): void
    {
        $byRepo = array_column(self::charts()['byRepo']['datasets'], 'data', 'label');
        $localLabel = \Episciences_Repositories::getLabel((int)\Episciences_Repositories::EPISCIENCES_REPO_ID);

        $this->assertSame('Episciences', $localLabel);
        $this->assertArrayNotHasKey($localLabel, $byRepo);

        // the repositories add up to the submissions of each year (imported papers left out)
        $totals = [];

        foreach ($byRepo as $data) {
            foreach ($data as $i => $value) {
                $totals[$i] = ($totals[$i] ?? 0) + $value;
            }
        }

        $this->assertSame(self::charts()['byYear']['datasets'][0]['data'], $totals);
    }

    public function testBuildChartsData_Delays_YearsWithoutDelayLeftOut(): void
    {
        $delays = self::charts()['delays'];

        $this->assertSame(['2023', '2024'], $delays['years']);
        $this->assertSame([57.0, 162.0], $delays['datasets'][0]['data']);
        $this->assertSame([234.0, null], $delays['datasets'][1]['data']);
        $this->assertSame('Submission → acceptance', $delays['datasets'][0]['label']);
    }

    public function testBuildChartsData_Delays_MedianByYear(): void
    {
        $delays = self::charts()['delays'];

        // the same years and series as the mean
        $this->assertSame([41.0, 150.0], $delays['medianDatasets'][0]['data']);
        $this->assertSame([210.0, null], $delays['medianDatasets'][1]['data']);
        $this->assertSame('Submission → publication', $delays['medianDatasets'][1]['label']);
    }

    public function testBuildChartsData_Delays_UnitOfTheApi(): void
    {
        $this->assertSame('DAY', self::charts()['delays']['unit']);

        $details = self::details();
        $details['submissionAcceptanceTime'][2023]['delay']['unit'] = 'MONTH';
        $this->assertSame('MONTH', StatsController::buildChartsData($details, [2023], [], self::LABELS)['delays']['unit']);

        // without unit: days, as the API by default
        $this->assertSame('DAY', StatsController::buildChartsData([], [], [], self::LABELS)['delays']['unit']);
    }

    public function testUnitLabel(): void
    {
        $this->assertSame('jours', StatsController::unitLabel('DAY'));
        $this->assertSame('mois', StatsController::unitLabel('month'));
        $this->assertSame('quarter', StatsController::unitLabel('QUARTER'));
    }

    /**
     * The number of articles of each delay, when the API gives it
     */
    public function testBuildChartsData_Delays_NumberOfArticles(): void
    {
        $delays = self::charts()['delays'];

        $this->assertSame([6, 1], $delays['medianDatasets'][0]['counts']);
        $this->assertSame([null, null], $delays['datasets'][0]['counts']);
    }

    /**
     * The API does not give the median by year: no median series (the chart keeps the mean)
     */
    public function testBuildChartsData_Delays_WithoutMedianByYear(): void
    {
        $details = self::details();
        unset($details['submissionAcceptanceTimeMedian'], $details['submissionPublicationTimeMedian']);

        $delays = StatsController::buildChartsData($details, [2022, 2023, 2024], [], self::LABELS)['delays'];

        $this->assertSame(['2023', '2024'], $delays['years']);
        $this->assertNull($delays['medianDatasets']);
    }

    /**
     * A year with a median delay only is kept
     */
    public function testBuildChartsData_Delays_YearWithMedianOnlyKept(): void
    {
        $details = self::details();
        $details['submissionPublicationTimeMedian'][2022] = ['delay' => ['value' => 12]];

        $delays = StatsController::buildChartsData($details, [2022, 2023, 2024], [], self::LABELS)['delays'];

        $this->assertSame(['2022', '2023', '2024'], $delays['years']);
        $this->assertSame([null, 57.0, 162.0], $delays['datasets'][0]['data']);
        $this->assertSame([12.0, 210.0, null], $delays['medianDatasets'][1]['data']);
    }

    public function testBuildChartsData_ByYearPage(): void
    {
        $charts = StatsController::buildChartsData(self::details(), [2024], [], self::LABELS);

        $this->assertSame(['2024'], $charts['years']);
        $this->assertSame([29], $charts['byYear']['datasets'][0]['data']);
        $this->assertSame([], $charts['byYear']['imported']);
    }

    public function testBuildChartsData_NoDetails(): void
    {
        $charts = StatsController::buildChartsData([], [], [], self::LABELS);

        $this->assertSame([], $charts['years']);
        $this->assertSame([], $charts['byRepo']['datasets']);
        $this->assertSame([], $charts['delays']['years']);
    }

    public function testGetExportName(): void
    {
        $this->assertSame('statistiques-dev-tout', StatsController::getExportName('dev', null));
        $this->assertSame('statistiques-dev-2026', StatsController::getExportName('dev', 2026));
        // raw: the page (statCsvFileName) makes it a file name
        $this->assertSame('statistiques-dev-2026-données complètes', StatsController::getExportName('dev', 2026, true));
    }

    public function testGetExportName_InTheLanguageOfThePage(): void
    {
        $english = ['statistiques' => 'statistics', 'tout' => 'all', 'données complètes' => 'full data'];
        $translate = static fn(string $text): string => $english[$text] ?? $text;

        $this->assertSame('statistics-dev-all', StatsController::getExportName('dev', null, false, $translate));
        $this->assertSame('statistics-dev-2026-full data', StatsController::getExportName('dev', 2026, true, $translate));
    }

    public function testBuildFiguresExport_FiguresWithoutValueLeftOut(): void
    {
        $this->assertSame([
            ['Articles', '507'],
            ['Taux de refus (%)', '45.68'],
            ['Autres statuts', '0'],
        ], StatsController::buildFiguresExport([
            ['Articles', 507],
            ['Soumissions', null],
            ['Taux de refus (%)', 45.68],
            ['Délai', ''],
            ['Autres statuts', 0],
            ['Liste', [1, 2]],
        ]));
    }

    public function testBuildUsersChartData_SortedRootLeftOut(): void
    {
        $users = StatsController::buildUsersChartData(
            ['author' => ['nbUsers' => 81], 'epiadmin' => ['nbUsers' => 2], 'member' => ['nbUsers' => 203], 'editor' => ['nbUsers' => 5]],
            static fn(string $key) => strtoupper($key)
        );

        $this->assertSame(['MEMBER', 'AUTHOR', 'EDITOR'], $users['labels']);
        $this->assertSame([203, 81, 5], $users['datasets'][0]['data']);
        $this->assertSame(strtoupper(StatsController::CHART_LABELS['users']), $users['datasets'][0]['label']);
    }
}
