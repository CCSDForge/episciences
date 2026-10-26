<?php

use Episciences\Api\DelegatedTokenClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;
use Psr\Log\NullLogger;

class StatsController extends Zend_Controller_Action
{
    // series of the charts (translated), in their order: the colour of a series is given by its position
    public const CHART_LABELS = [
        'submitted' => 'Soumissions (hors imports)',
        'accepted' => 'Acceptés',
        'refused' => 'Refusés',
        'published' => 'Publiés',
        'acceptanceDelay' => 'Dépôt → acceptation',
        'publicationDelay' => 'Dépôt → publication',
        'users' => "Nombre d'utilisateurs",
    ];

    public const ACCEPTED_SUBMISSIONS = Episciences_Paper::ACCEPTED_SUBMISSIONS;
    public const SUBMISSIONS_BY_YEAR = 'submissionsByYear';

    public const NB_SUBMISSIONS = 'nbSubmissions';
    public const NB_IMPORTED = 'nbImported';
    public const NB_PUBLISHED = 'nbPublished';
    public const ACCEPTED_NOT_YET_PUBLISHED = 'acceptedNotYetPublished';
    public const NB_OTHER_STATUS = 'nbOtherStatus';
    public const NB_REFUSED = 'nbRefused';
    public const NB_ACCEPTED = 'nbAccepted';
    public const SUBMISSION_ACCEPTANCE_DELAY = 'submissionAcceptanceTime';
    public const SUBMISSION_PUBLICATION_DELAY = 'submissionPublicationTime';
    public const SUBMISSION_ACCEPTANCE_MEDIAN_DELAY = 'submissionAcceptanceTimeMedian';
    public const SUBMISSION_PUBLICATION_MEDIAN_DELAY = 'submissionPublicationTimeMedian';
    public const NB_USERS = 'nbUsers';
    public const NB_IMPORTED_PUBLISHED = 'nbImportedPublished';
    public const NB_ACCEPTED_NOT_YET_PUBLISHED = 'nbAcceptedNotYetPublished';
    // papers of the published total: published and imported published papers
    public const TOTAL_PUBLISHED_PAPERS = 'totalPublished';
    public const WITH_PAPERS_PARAM = 'withPapers';
    // value of the "displayStatistics" setting: statistics reserved for the editorial secretaries (the API checks it too)
    public const DISPLAY_STATISTICS_RESERVED = '2';
    // the papers of the figures are kept in session for the papers action (no DOCID in the page)
    public const PAPERS_SESSION_NAMESPACE = 'statsPapers';
    public const PAPERS_SESSION_TTL = 1800;
    // the administrators of the journal can show all the data, without the "startStatsAfterDate" setting of the journal
    public const FULL_HISTORY_PARAM = 'fullHistory';
    // date columns of the papers table: only the dates relevant to each figure
    // (the submission date is the import date for an imported paper)
    public const COLUMN_SUBMISSION = 'submissionDate';
    public const COLUMN_REFUSAL = 'refusalDate';
    public const COLUMN_ACCEPTANCE = 'acceptanceDate';
    public const COLUMN_PUBLICATION = 'publicationDate';
    public const FIGURE_COLUMNS = [
        self::NB_SUBMISSIONS => [self::COLUMN_SUBMISSION, self::COLUMN_REFUSAL, self::COLUMN_ACCEPTANCE, self::COLUMN_PUBLICATION],
        self::NB_IMPORTED => [self::COLUMN_SUBMISSION, self::COLUMN_PUBLICATION],
        self::NB_IMPORTED_PUBLISHED => [self::COLUMN_SUBMISSION, self::COLUMN_PUBLICATION],
        self::NB_PUBLISHED => [self::COLUMN_SUBMISSION, self::COLUMN_PUBLICATION],
        self::TOTAL_PUBLISHED_PAPERS => [self::COLUMN_SUBMISSION, self::COLUMN_PUBLICATION],
        self::NB_ACCEPTED => [self::COLUMN_SUBMISSION, self::COLUMN_ACCEPTANCE, self::COLUMN_PUBLICATION],
        self::NB_ACCEPTED_NOT_YET_PUBLISHED => [self::COLUMN_SUBMISSION, self::COLUMN_ACCEPTANCE],
        self::NB_REFUSED => [self::COLUMN_SUBMISSION, self::COLUMN_REFUSAL],
        self::NB_OTHER_STATUS => [self::COLUMN_SUBMISSION],
        self::SUBMISSION_ACCEPTANCE_DELAY => [self::COLUMN_SUBMISSION, self::COLUMN_ACCEPTANCE],
        self::SUBMISSION_PUBLICATION_DELAY => [self::COLUMN_SUBMISSION, self::COLUMN_PUBLICATION],
    ];
    public const DEFAULT_COLUMNS = [self::COLUMN_SUBMISSION, self::COLUMN_PUBLICATION];
    // statuses of these dates in the history of a paper, as for the figures of the API
    // (PaperLogRepository::ACCEPTED_STATUSES and REFUSED_STATUS)
    public const COLUMN_LOG_STATUSES = [
        self::COLUMN_REFUSAL => [Episciences_Paper::STATUS_REFUSED],
        self::COLUMN_ACCEPTANCE => [Episciences_Paper::STATUS_ACCEPTED, Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED],
    ];
    // label of the submission date column: submitted papers, imported papers, or both
    public const SUBMISSION_LABEL_SUBMITTED = 'submitted';
    public const SUBMISSION_LABEL_IMPORTED = 'imported';
    public const SUBMISSION_LABEL_MIXED = 'mixed';
    // Episciences API: the page must not wait indefinitely for an unreachable API (seconds)
    public const API_CONNECT_TIMEOUT = 5;
    public const API_TIMEOUT = 30;


    public const REFERENCE_YEAR = 2013;


    /**
     * @return void
     */
    public function indexAction(): void
    {

        try {
            /** @var Monolog\Logger $logger */
            $logger = Zend_Registry::get('appLogger');
        } catch (Throwable $e) {
            $logger = null;
            trigger_error($e->getMessage());
        }


        try {
            if (Zend_Registry::get('hideStatistics')) {
                $this->getResponse()?->setHttpResponseCode(403);
                $this->renderScript('index/notfound.phtml');
                return;

            }
        } catch (Zend_Exception $e) {
            $logger?->critical($e->getMessage());
            return;
        }


        $params = ['withDetails' => true];

        $evalOptions = [];

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $yearQuery = (!empty($request->getParam('year'))) ? (int)$request->getParam('year') : null;

        try {
            $journalSettings = Zend_Registry::get('reviewSettings');
        } catch (Zend_Exception $e) {
            $logger?->critical($e->getMessage());

        }
        $startStatsAfterDate = isset($journalSettings['startStatsAfterDate']) && $journalSettings['startStatsAfterDate'] !== '' ?
            $journalSettings['startStatsAfterDate'] : null;

        // The API rejects a malformed date: ignore it, as before, rather than failing to display the statistics
        if ($startStatsAfterDate && !Episciences_Tools::isValidSQLDate($startStatsAfterDate)) {
            $logger?->warning(sprintf('Invalid startStatsAfterDate setting "%s" ignored (RVCODE: %s)', $startStatsAfterDate, RVCODE));
            $startStatsAfterDate = null;
        }

        // The administrators of the journal can show all the data, without the "startStatsAfterDate" setting of the journal
        $canShowFullHistory = $startStatsAfterDate !== null && self::canSeePapers();
        $fullHistory = $canShowFullHistory && self::isFullHistoryRequested($request->getParam(self::FULL_HISTORY_PARAM));

        $this->view->startStatsAfterDateSetting = $canShowFullHistory ? $startStatsAfterDate : null;
        $this->view->fullHistory = $fullHistory;

        if ($fullHistory) {
            $startStatsAfterDate = null;
        }

        if ($startStatsAfterDate) {
            $startStatsAfterDateYear = (int)date('Y', strtotime($startStatsAfterDate));
            $params['startAfterDate'] = $startStatsAfterDate;
            $this->view->startStatsAfterDate = $startStatsAfterDate;
        }

        $askApi = !$yearQuery || ( // all stats
                $yearQuery >= (!$startStatsAfterDate ? self::REFERENCE_YEAR : $startStatsAfterDateYear) && $yearQuery <= (int)date('Y') // by year
            );


        $uri = 'journals/stats/dashboard/' . RVCODE;


        $errorMessage = "Une erreur s'est produite lors de la récupération des statistiques. Nous vous suggérons de ré-essayer dans quelques instants. Si le problème persiste vous devriez contacter le support de la revue.";

        if ($yearQuery) {
            $params['year'] = $yearQuery;
            $evalOptions['year'] = $yearQuery;
        }

        $yearCategories = [];
        $dashboard = null;

        if ($askApi) {
            $token = $this->getDelegatedToken($logger);
            // reserved statistics: the API also requires the token without the papers
            $dashboardToken = self::areStatisticsReserved() ? $token : null;

            if ($token !== null && self::canSeePapers()) {
                try {
                    $dashboard = json_decode($this->askApi($uri, array_merge($params, [self::WITH_PAPERS_PARAM => 'true']), $token), true, 512, JSON_THROW_ON_ERROR);
                } catch (GuzzleException|JsonException $e) {
                    // falls back to the statistics without the papers
                    $logger?->warning(sprintf('Statistics of %s: the papers are not available: %s', RVCODE, $e->getMessage()));
                }
            }

            if (empty($dashboard)) {
                try {
                    $dashboard = json_decode($this->askApi($uri, $params, $dashboardToken), true, 512, JSON_THROW_ON_ERROR);
                } catch (GuzzleException|JsonException $e) {
                    $this->view->errorMessage = $errorMessage;
                    $logger?->warning($e->getMessage());
                }
            }

            if (empty($dashboard)) {
                $this->renderUnavailable($errorMessage);
                return;
            }

            try {
                $this->processEvaluationStats($evalOptions, $reviewsRequested, $reviewsReceived, $medianReviewsNumber);
            } catch (GuzzleException|JsonException $e) {
                $this->view->errorMessage = $errorMessage;
                $logger?->warning($e->getMessage());
            }
        }

        $details = $dashboard['details'] ?? [];

        if (isset($details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR])) {
            $yearCategories = array_keys($details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR]);
        }

        $navYears = $details[self::NB_SUBMISSIONS]['years']['relevantYears'] ?? [];

        if ($startStatsAfterDate) {
            $navYears = array_filter($navYears ?? [], static function ($year) use ($startStatsAfterDateYear) {
                return $year >= $startStatsAfterDateYear;
            });
        }

        $this->view->yearCategories = $navYears; // navigation

        if ($yearQuery && !in_array($yearQuery, $yearCategories, true)) {
            $this->renderError($yearQuery);
            return;
        }

        if ($yearQuery) { // for stats by year
            $yearCategories = [$yearQuery];
        }

        // Figure 1 > At a glance

        // all(Sub.., Pub.., Acce..., Aru..., Oth... ) : Imported articles are not included
        $allSubmissions = $dashboard['value'][self::NB_SUBMISSIONS] ?? null;
        $allPublications = $dashboard['value'][self::NB_PUBLISHED] ?? null;
        $allAcceptations = $dashboard['value'][self::NB_ACCEPTED];
        $allRefusals = $dashboard['value'][self::NB_REFUSED];
        $allOtherStatus = $dashboard['value'][self::NB_OTHER_STATUS];


        $importedPublished = $dashboard['value']['nbImportedPublished'] ?? 0; // imported and published


        // The API only returns these values if the "startAfterDate" filter is enabled: they provide an overview of the data, without taking this filter into account.
        $totalArticles = $dashboard['value']['totalWithoutStartAfterDate']['totalSubmissions'] ?? $dashboard['value'][self::NB_SUBMISSIONS] ?? null;
        $totalImported = $dashboard['value']['totalWithoutStartAfterDate']['totalImported'] ?? $dashboard['value'][self::NB_IMPORTED] ?? null;
        $totalPublished = $dashboard['value']['totalWithoutStartAfterDate']['totalPublished'] ?? ($allPublications + $importedPublished) ?? null;
        $totalImportedPublished = $dashboard['value']['totalWithoutStartAfterDate']['totalImportedPublished'] ?? $importedPublished ?? null;


        $this->view->chart1Title = $this->view->translate("En un coup d'oeil");

        // Indicators
        $this->view->totalArticles = $totalArticles;
        $this->view->allSubmissions = $allSubmissions;
        $this->view->totalImportedArticles = $totalImported; // without "startAfterDate" filter
        $this->view->imported = $dashboard['value']['nbImported'] ?? null; // filter's "startAfterDate" taking into account.
        $this->view->totalPublishedArticles = $totalPublished;
        $this->view->totalImportedPublished = $totalImportedPublished;

        $this->view->allPublications = $allPublications ?? null;
        $this->view->allRefusals = $allRefusals ?? null;
        $this->view->allAcceptations = $allAcceptations ?? null;
        $this->view->allOtherStatus = $allOtherStatus ?? null;


        $this->view->acceptedNotYetPublished = $dashboard['value']['nbAcceptedNotYetPublished'] ?? null;
        $this->view->acceptedSubmittedSameYear = $dashboard['value']['totalAcceptedSubmittedSameYear'] ?? null;
        $this->view->publishedSubmittedSameYear = $dashboard['value']['totalPublishedSubmittedSameYear'] ?? null;
        $this->view->refusedSubmittedSameYear = $dashboard['value']['totalRefusedSubmittedSameYear'] ?? null;
        $this->view->reviewsRequested = $reviewsRequested ?? null;
        $this->view->reviewsReceived = $reviewsReceived ?? null;
        $this->view->medianReviewsNumber = $medianReviewsNumber ?? null;


        // Percentages
        $publicationRate = $dashboard['value']['rates']['publication'] ?? null;
        $acceptanceRate = $dashboard['value']['rates']['acceptance'] ?? null;
        $refusalRate = $dashboard['value']['rates']['refusal'] ?? null;

        $this->view->acceptanceRate = $acceptanceRate;
        $this->view->publicationRate = $publicationRate;
        $this->view->declineRate = $refusalRate;


        $this->view->submissionAcceptanceTime = $dashboard['value'][self::SUBMISSION_ACCEPTANCE_DELAY]['value'] ?? null;
        $this->view->submissionAcceptanceTimeUnit = $dashboard['value'][self::SUBMISSION_ACCEPTANCE_DELAY]['unit'] ?? null;
        $this->view->submissionPublicationTime = $dashboard['value'][self::SUBMISSION_PUBLICATION_DELAY]['value'] ?? null;
        $this->view->submissionPublicationTimeUnit = $dashboard['value'][self::SUBMISSION_PUBLICATION_DELAY]['unit'] ?? null;
        $this->view->submissionPublicationTimeMedian = $dashboard['value'][self::SUBMISSION_PUBLICATION_MEDIAN_DELAY]['value'] ?? null;
        $this->view->submissionPublicationTimeMedianUnit = $dashboard['value'][self::SUBMISSION_PUBLICATION_MEDIAN_DELAY]['unit'] ?? null;
        $this->view->submissionAcceptanceTimeMedian = $dashboard['value'][self::SUBMISSION_ACCEPTANCE_MEDIAN_DELAY]['value'] ?? null;
        $this->view->submissionAcceptanceTimeMedianUnit = $dashboard['value'][self::SUBMISSION_ACCEPTANCE_MEDIAN_DELAY]['unit'] ?? null;


        // Charts: the imported papers are left out of the charts by year (an import makes a single year dwarf the others)
        $this->view->chart2Title = !$yearQuery ?
            $this->view->translate('Soumissions, décisions et publications par année') :
            $this->view->translate('Soumissions, décisions et publications');
        $this->view->chart3Title = !$yearQuery ?
            $this->view->translate('Répartition des soumissions par année et par archive') :
            $this->view->translate('Répartition des soumissions par archive');
        $charts = self::buildChartsData(
            $details,
            $yearCategories,
            $this->getImportedByYearAndRepo($startStatsAfterDate, $logger),
            array_map(fn(string $label) => $this->view->translate($label), self::CHART_LABELS)
        );

        // titles of the delays chart, following the selector "moyenne" / "médiane" of the delay table
        $charts['delays']['titles'] = [
            'mean' => $this->view->translate('Délai moyen en jours entre dépôt et acceptation | dépôt et publication'),
            'median' => $this->view->translate('Délai médian en jours entre dépôt et acceptation | dépôt et publication'),
        ];
        // the median by default; the mean when the API does not give the median by year
        $this->view->chart4Title = $charts['delays']['titles'][$charts['delays']['medianDatasets'] !== null ? 'median' : 'mean'];

        $isAvailableUsersStats = !$yearQuery && isset($dashboard['value'][self::NB_USERS]);

        // Users: not available by year, the database does not allow to get this information
        if ($isAvailableUsersStats) {
            $charts['users'] = self::buildUsersChartData($details[self::NB_USERS] ?? [], fn(string $role) => $this->view->translate($role));
            $this->view->chart5Title = $this->view->translate("Le nombre d'utilisateurs par rôles");
            $this->view->allUsers = $dashboard['value'][self::NB_USERS];
        }

        $this->view->charts = $charts;
        $this->view->papersWithTemporaryVersion = $this->countPapersWithTemporaryVersion($yearQuery, $startStatsAfterDate, $logger);
        $this->view->yearQuery = $yearQuery;
        $this->view->errorMessage = null;
        $this->view->isAvailableUsersStats = $isAvailableUsersStats;

        // Papers of the figures (journal administrators only): kept in session, the page only gets their number
        $paperLists = self::getPaperLists($dashboard['value'] ?? []);
        $this->storePaperLists($paperLists, $yearQuery, $fullHistory);
        $this->view->paperLists = self::countPaperLists($paperLists, self::getFigureValues($dashboard['value'] ?? []), $logger);
        $this->view->paperListTitles = $paperLists ? $this->getPaperListTitles() : [];
        // without the "startAfterDate" filter, the totals are not explained by the papers
        $this->view->totalsExplained = !isset($dashboard['value']['totalWithoutStartAfterDate']);

        // CSV exports: base name of the files, and the figures of the page
        $this->view->exportName = self::getExportName(RVCODE, $yearQuery, $fullHistory);
        $this->view->figuresExport = $this->getFiguresExport($startStatsAfterDate);

    }

    /**
     * Base name of the CSV files of the page: journal, year, all the data or not
     */
    public static function getExportName(string $journalCode, ?int $year, bool $fullHistory = false): string
    {
        return implode('-', array_filter(['statistiques', $journalCode, $year ?: 'tout', $fullHistory ? 'donnees-completes' : null]));
    }

    /**
     * Rows of the export of the figures of the page (CSV): indicator, value; the figures without value are left out
     * @param array<int, array{0: string, 1: mixed}> $figures translated label => value
     * @return array<int, array{0: string, 1: string}>
     */
    public static function buildFiguresExport(array $figures): array
    {
        $rows = [];

        foreach ($figures as [$label, $value]) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $rows[] = [(string)$label, (string)$value];
        }

        return $rows;
    }

    /**
     * The figures of the page, as shown, for their CSV export
     * @param string|null $startStatsAfterDate the start date applied to the statistics (null: all the data)
     * @return array<int, array{0: string, 1: string}>
     */
    private function getFiguresExport(?string $startStatsAfterDate): array
    {
        $view = $this->view;
        $t = fn(string $label) => ucfirst((string)$view->translate($label));
        $days = ' (' . $view->translate('jours') . ')';

        return self::buildFiguresExport([
            [$t('Revue'), RVCODE],
            [$t('Année'), $view->yearQuery ?: $view->translate('Tout')],
            [$t('Données à partir du'), $startStatsAfterDate ?: $view->translate('Toutes les données')],
            [$t('articles'), $view->totalArticles],
            [$t('articles importés'), $view->totalImportedArticles],
            [$t('publiés'), $view->totalPublishedArticles],
            [$t('articles importés publiés'), $view->totalImportedPublished],
            [$t('Soumissions'), $startStatsAfterDate ? $view->allSubmissions : null],
            [$t('articles publiés'), $view->allPublications],
            [$t('articles acceptés'), $view->allAcceptations],
            [$t('articles refusés'), $view->allRefusals],
            [$t('autres statuts'), $view->allOtherStatus],
            [$t('articles acceptés (non encore publiés)'), $view->acceptedNotYetPublished],
            [$t('articles avec version temporaire'), $view->papersWithTemporaryVersion],
            [$t('articles acceptés') . ' (' . $view->translate('soumis la même année') . ')', $view->yearQuery ? $view->acceptedSubmittedSameYear : null],
            [$t('articles publiés') . ' (' . $view->translate('soumis la même année') . ')', $view->yearQuery ? $view->publishedSubmittedSameYear : null],
            [$t('articles refusés') . ' (' . $view->translate('soumis la même année') . ')', $view->yearQuery ? $view->refusedSubmittedSameYear : null],
            [$t("taux d'acceptation") . ' (%)', $view->acceptanceRate],
            [$t('taux de publication') . ' (%)', $view->publicationRate],
            [$t('taux de refus') . ' (%)', $view->declineRate],
            [$t('délai soumission-acceptation') . ' – ' . $view->translate('moyenne') . $days, $view->submissionAcceptanceTime],
            [$t('délai soumission-acceptation') . ' – ' . $view->translate('médiane') . $days, $view->submissionAcceptanceTimeMedian],
            [$t('délai soumission-publication') . ' – ' . $view->translate('moyenne') . $days, $view->submissionPublicationTime],
            [$t('délai soumission-publication') . ' – ' . $view->translate('médiane') . $days, $view->submissionPublicationTimeMedian],
            [$t('Évaluations demandées'), $view->reviewsRequested],
            [$t('Évaluations reçues'), $view->reviewsReceived],
            [$t("Nombre médian d'évaluations d'articles de recherche"), $view->medianReviewsNumber],
            [$t("Nombre total d'utilisateurs"), $view->isAvailableUsersStats ? $view->allUsers : null],
        ]);
    }

    /**
     * Data of the charts of the page, from the details of the dashboard
     * The imported papers are left out: they are counted in the year of their import, which dwarfs the other years.
     * Each value is given for each year (0 or null when there is none), so that the values stay in line with the years.
     * @param array $details details of the dashboard
     * @param array $years years of the charts
     * @param array<int|string, array<string, int>> $importedByYearAndRepo year => repository label => imported papers
     * @param array<string, string> $labels translated CHART_LABELS
     * @return array{years: string[], byYear: array, byRepo: array, delays: array}
     */
    public static function buildChartsData(array $details, array $years, array $importedByYearAndRepo, array $labels): array
    {
        $byYear = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR] ?? [];
        $byRepo = $details[self::NB_SUBMISSIONS]['submissionsByRepo'] ?? [];
        $years = array_values(array_map('strval', $years));
        $count = static fn($year, string $key): int => (int)($byYear[$year][$key] ?? 0);

        $imported = [];

        foreach ($years as $year) {
            if ($count($year, 'imported') > 0) {
                $imported[$year] = $count($year, 'imported');
            }
        }

        $yearsDatasets = [];

        foreach (['submitted' => null, 'accepted' => 'accepted', 'refused' => 'refused', 'published' => 'published'] as $series => $key) {
            $yearsDatasets[] = [
                'label' => $labels[$series] ?? $series,
                'data' => array_map(
                    static fn($year) => $key === null ? max(0, $count($year, 'submissions') - $count($year, 'imported')) : $count($year, $key),
                    $years
                ),
            ];
        }

        // repositories in a stable order (their identifier), so that each one keeps its colour
        $repositories = [];

        foreach ($years as $year) {
            foreach (array_keys($byRepo[$year] ?? []) as $repository) {
                $repositories[$repository] = true;
            }
        }

        // the local repository holds the temporary versions of papers already counted in their repository: not a repository
        $repositoryIds = Episciences_Repositories::getRepositoriesByLabel();
        $repositories = array_values(array_filter(
            array_keys($repositories),
            static fn($label) => (string)($repositoryIds[$label]['id'] ?? '') !== Episciences_Repositories::EPISCIENCES_REPO_ID
        ));
        $ids = array_map(static fn($label) => (int)($repositoryIds[$label]['id'] ?? PHP_INT_MAX), $repositories);
        array_multisort($ids, SORT_NUMERIC, $repositories);

        $repoDatasets = [];

        foreach ($repositories as $repository) {
            $repoDatasets[] = [
                'label' => (string)$repository,
                'data' => array_map(
                    static fn($year) => max(0, (int)($byRepo[$year][$repository]['submissions'] ?? 0) - (int)($importedByYearAndRepo[$year][$repository] ?? 0)),
                    $years
                ),
            ];
        }

        // delays (mean and median by year): the years without any delay are left out
        $delay = static fn(string $key, $year) => isset($details[$key][$year]['delay']['value']) ? (float)$details[$key][$year]['delay']['value'] : null;
        $delayKeys = [self::SUBMISSION_ACCEPTANCE_DELAY, self::SUBMISSION_PUBLICATION_DELAY, self::SUBMISSION_ACCEPTANCE_MEDIAN_DELAY, self::SUBMISSION_PUBLICATION_MEDIAN_DELAY];
        $delayYears = array_values(array_filter(
            $years,
            static function ($year) use ($delay, $delayKeys): bool {
                foreach ($delayKeys as $key) {
                    if ($delay($key, $year) !== null) {
                        return true;
                    }
                }

                return false;
            }
        ));
        $delayDatasets = static fn(string $acceptanceKey, string $publicationKey): array => [
            ['label' => $labels['acceptanceDelay'] ?? 'acceptanceDelay', 'data' => array_map(static fn($year) => $delay($acceptanceKey, $year), $delayYears)],
            ['label' => $labels['publicationDelay'] ?? 'publicationDelay', 'data' => array_map(static fn($year) => $delay($publicationKey, $year), $delayYears)],
        ];
        $hasMedian = isset($details[self::SUBMISSION_ACCEPTANCE_MEDIAN_DELAY]) || isset($details[self::SUBMISSION_PUBLICATION_MEDIAN_DELAY]);

        return [
            'years' => $years,
            'byYear' => ['datasets' => $yearsDatasets, 'imported' => $imported],
            'byRepo' => ['datasets' => $repoDatasets, 'imported' => $imported, 'stacked' => true],
            'delays' => [
                'years' => $delayYears,
                'datasets' => $delayDatasets(self::SUBMISSION_ACCEPTANCE_DELAY, self::SUBMISSION_PUBLICATION_DELAY),
                // null when the API does not give the median by year: the chart keeps the mean
                'medianDatasets' => $hasMedian ? $delayDatasets(self::SUBMISSION_ACCEPTANCE_MEDIAN_DELAY, self::SUBMISSION_PUBLICATION_MEDIAN_DELAY) : null,
            ],
        ];
    }

    /**
     * Users by role, sorted from the largest number (the root role is left out)
     * @param array $usersDetails role => ['nbUsers' => n]
     * @param callable $translate translation of a role
     * @return array{labels: string[], datasets: array}
     */
    public static function buildUsersChartData(array $usersDetails, callable $translate): array
    {
        unset($usersDetails[Episciences_Acl::ROLE_ROOT]);

        $users = [];

        foreach ($usersDetails as $role => $values) {
            $users[] = [(string)$translate($role), (int)($values[self::NB_USERS] ?? 0)];
        }

        usort($users, static fn(array $a, array $b) => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]));

        return [
            'labels' => array_column($users, 0),
            'datasets' => [['label' => (string)$translate(self::CHART_LABELS['users']), 'data' => array_column($users, 1)]],
        ];
    }

    /**
     * Papers of each figure of the dashboard, if the API returned them
     * @param array $value the value of the dashboard
     * @return array<string, array{docIds: int[], hidden: int}> figure => DOCIDs of the listed papers and number of papers not listed (conflict of interest)
     */
    public static function getPaperLists(array $value): array
    {
        if (!isset($value['papers']) || !is_array($value['papers'])) {
            return [];
        }

        $hiddenPapers = isset($value['hiddenPapers']) && is_array($value['hiddenPapers']) ? $value['hiddenPapers'] : [];
        $lists = [];

        foreach ($value['papers'] as $figure => $docIds) {
            if (!is_array($docIds)) {
                continue;
            }

            $lists[$figure] = [
                'docIds' => array_values(array_filter(array_map('intval', $docIds), static fn(int $docId) => $docId > 0)),
                'hidden' => max(0, (int)($hiddenPapers[$figure] ?? 0)),
            ];
        }

        if (isset($lists[self::NB_PUBLISHED], $lists[self::NB_IMPORTED_PUBLISHED])) {
            $lists[self::TOTAL_PUBLISHED_PAPERS] = [
                'docIds' => array_values(array_unique(array_merge($lists[self::NB_PUBLISHED]['docIds'], $lists[self::NB_IMPORTED_PUBLISHED]['docIds']))),
                'hidden' => $lists[self::NB_PUBLISHED]['hidden'] + $lists[self::NB_IMPORTED_PUBLISHED]['hidden'],
            ];
        }

        return $lists;
    }

    /**
     * JSON: the papers of a figure of the statistics page (journal administrators only)
     * Only the papers kept in session by indexAction() for the connected user can be returned.
     * @return void
     */
    public function papersAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if (!self::canSeePapers()) {
            $this->sendJson(403, ['message' => 'Forbidden']);
            return;
        }

        $request = $this->getRequest();
        $figure = (string)$request->getParam('figure', '');
        $year = (int)$request->getParam('year', 0) ?: null;
        $fullHistory = self::isFullHistoryRequested($request->getParam(self::FULL_HISTORY_PARAM));

        $stored = (new Zend_Session_Namespace(self::PAPERS_SESSION_NAMESPACE))->{self::getPapersSessionKey($year, $fullHistory)} ?? null;

        if (
            !is_array($stored) ||
            ($stored['uid'] ?? null) !== (int)Episciences_Auth::getUid() ||
            !isset($stored['lists'][$figure]['docIds'])
        ) {
            // expired session, or a figure without papers
            $this->sendJson(404, ['message' => 'Not found']);
            return;
        }

        $list = $stored['lists'][$figure];
        $columns = self::FIGURE_COLUMNS[$figure] ?? self::DEFAULT_COLUMNS;
        $rows = self::getPaperRows($list['docIds'], $columns);

        $this->sendJson(200, [
            'figure' => $figure,
            'columns' => $columns,
            'submissionLabel' => self::getSubmissionLabel($rows),
            'total' => count($list['docIds']),
            'hidden' => (int)$list['hidden'],
            'rows' => $rows,
        ]);
    }

    /**
     * Number of papers by figure, for the page
     * @param array<string, array{docIds: int[], hidden: int}> $paperLists
     * @param array<string, int> $figureValues value displayed for each figure, to check the lists
     * @param Monolog\Logger|null $logger
     * @return array<string, array{figure: string, count: int, hidden: int, incomplete: bool}>
     */
    public static function countPaperLists(array $paperLists, array $figureValues = [], ?Monolog\Logger $logger = null): array
    {
        $counts = [];

        foreach ($paperLists as $figure => $list) {
            $count = count($list['docIds']);
            $incomplete = isset($figureValues[$figure]) && $count + $list['hidden'] !== $figureValues[$figure];

            if ($incomplete) {
                $logger?->warning(sprintf(
                    'Statistics of %s: %s, %d papers listed and %d hidden for a figure of %d',
                    defined('RVCODE') ? RVCODE : '', $figure, $count, $list['hidden'], $figureValues[$figure]
                ));
            }

            $counts[$figure] = ['figure' => $figure, 'count' => $count, 'hidden' => $list['hidden'], 'incomplete' => $incomplete];
        }

        return $counts;
    }

    /**
     * Values of the figures which count papers (the delays are not a number of papers)
     * @param array $value the value of the dashboard
     * @return array<string, int>
     */
    public static function getFigureValues(array $value): array
    {
        $figures = [
            self::NB_SUBMISSIONS, self::NB_IMPORTED, self::NB_IMPORTED_PUBLISHED, self::NB_PUBLISHED, self::NB_ACCEPTED,
            self::NB_REFUSED, self::NB_ACCEPTED_NOT_YET_PUBLISHED, self::NB_OTHER_STATUS,
        ];
        $values = [];

        foreach ($figures as $figure) {
            if (isset($value[$figure]) && is_numeric($value[$figure])) {
                $values[$figure] = (int)$value[$figure];
            }
        }

        if (isset($values[self::NB_PUBLISHED], $values[self::NB_IMPORTED_PUBLISHED])) {
            $values[self::TOTAL_PUBLISHED_PAPERS] = $values[self::NB_PUBLISHED] + $values[self::NB_IMPORTED_PUBLISHED];
        }

        return $values;
    }

    /**
     * Rows of the papers table: the papers of the journal among the given DOCIDs
     * @param int[] $docIds
     * @param string[] $columns date columns (COLUMN_*) of the figure
     * @return array<int, array<string, mixed>>
     */
    public static function getPaperRows(array $docIds, array $columns = self::DEFAULT_COLUMNS): array
    {
        $docIds = array_values(array_unique(array_filter(array_map('intval', $docIds), static fn(int $docId) => $docId > 0)));

        if (!$docIds) {
            return [];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rows = [];

        foreach (array_chunk($docIds, 1000) as $chunk) {
            $select = $db->select()
                ->from(T_PAPERS, ['DOCID', 'PAPERID', 'RVID', 'STATUS', 'FLAG', 'RECORD', 'IDENTIFIER', 'VERSION', 'REPOID', 'SUBMISSION_DATE', 'PUBLICATION_DATE'])
                ->where('RVID = ?', RVID)
                ->where('DOCID IN (?)', $chunk);

            $papers = $db->fetchAll($select);
            $paperIds = array_column($papers, 'PAPERID');
            $logDates = [];

            // the dates read in the history of the papers: only those of the columns of the figure
            foreach (self::COLUMN_LOG_STATUSES as $column => $statuses) {
                if (in_array($column, $columns, true)) {
                    $logDates[$column] = self::getStatusDates($paperIds, $statuses);
                }
            }

            foreach ($papers as $row) {
                $row['withxsl'] = false;
                $paperId = (int)$row['PAPERID'];
                $dates = array_map(static fn(array $datesByPaper) => $datesByPaper[$paperId] ?? null, $logDates);
                $rows[] = self::formatPaperRow(new Episciences_Paper($row), $columns, $dates);
            }
        }

        return $rows;
    }

    /**
     * Latest date at which each paper (all its versions) took one of the given statuses in the journal
     * (the latest acceptance, as the submission-acceptance delay of the API; the latest refusal)
     * @param array $paperIds
     * @param int[] $statuses
     * @return array<int, string> PAPERID => date
     */
    public static function getStatusDates(array $paperIds, array $statuses): array
    {
        $paperIds = array_values(array_unique(array_filter(array_map('intval', $paperIds), static fn(int $paperId) => $paperId > 0)));

        if (!$paperIds || !$statuses) {
            return [];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_LOGS, ['PAPERID', 'STATUS_DATE' => new Zend_Db_Expr('MAX(DATE)')])
            ->where('RVID = ?', RVID)
            ->where('PAPERID IN (?)', $paperIds)
            ->where('status IN (?)', array_map('intval', $statuses))
            ->group('PAPERID');

        return array_map('strval', $db->fetchPairs($select));
    }

    /**
     * Label of the submission date column, from the papers of the figure
     * @param array<int, array{imported: bool}> $rows
     * @return string SUBMISSION_LABEL_*
     */
    public static function getSubmissionLabel(array $rows): string
    {
        $imported = count(array_filter($rows, static fn(array $row) => !empty($row['imported'])));

        return match (true) {
            $imported === 0 => self::SUBMISSION_LABEL_SUBMITTED,
            $imported === count($rows) => self::SUBMISSION_LABEL_IMPORTED,
            default => self::SUBMISSION_LABEL_MIXED,
        };
    }

    /**
     * @param Episciences_Paper $paper
     * @param string[] $columns date columns (COLUMN_*) of the figure: only these dates are returned
     * @param array<string, ?string> $logDates dates read in the history of the paper, by column (refusal, acceptance)
     * @return array<string, mixed> docid, paperid, title, status, statusLabel, statusGroup, imported, and the dates of the columns
     */
    public static function formatPaperRow(Episciences_Paper $paper, array $columns = self::DEFAULT_COLUMNS, array $logDates = []): array
    {
        $status = (int)$paper->getStatus();

        try {
            $title = (string)$paper->getTitle();
            $statusLabel = (string)Zend_Registry::get('Zend_Translate')->translate((string)Episciences_PapersManager::getStatusLabel($status));
        } catch (Throwable $e) {
            $title = $title ?? '';
            $statusLabel = (string)Episciences_PapersManager::getStatusLabel($status);
        }

        $row = [
            'docid' => (int)$paper->getDocid(),
            'paperid' => (int)$paper->getPaperid(),
            'title' => $title,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'statusGroup' => self::getStatusGroup($status),
            'imported' => $paper->getFlag() === 'imported', // FLAG, as the imported figures of the API
        ];

        $dates = [
            self::COLUMN_SUBMISSION => $paper->getSubmission_date(),
            self::COLUMN_REFUSAL => $logDates[self::COLUMN_REFUSAL] ?? null,
            self::COLUMN_ACCEPTANCE => $logDates[self::COLUMN_ACCEPTANCE] ?? null,
            self::COLUMN_PUBLICATION => $paper->getPublication_date(),
        ];

        foreach ($columns as $column) {
            if (array_key_exists($column, $dates)) {
                $row[$column] = self::toDate($dates[$column]);
            }
        }

        return $row;
    }

    /**
     * Group of a status, displayed with an icon next to its label
     */
    public static function getStatusGroup(int $status): string
    {
        return match (true) {
            $status === Episciences_Paper::STATUS_PUBLISHED => 'published',
            $status === Episciences_Paper::STATUS_REFUSED => 'refused',
            in_array($status, Episciences_Paper::ACCEPTED_SUBMISSIONS, true) => 'accepted',
            in_array($status, [Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED, Episciences_Paper::STATUS_REMOVED, Episciences_Paper::STATUS_ABANDONED], true) => 'closed',
            default => 'pending',
        };
    }

    /**
     * Key of the lists of papers in session: by journal, year and "startStatsAfterDate" filter
     */
    public static function getPapersSessionKey(?int $year, bool $fullHistory = false): string
    {
        return sprintf('%d_%s%s', defined('RVID') ? RVID : 0, $year ?: 'all', $fullHistory ? '_full' : '');
    }

    /**
     * Whether all the data is requested, without the "startStatsAfterDate" setting of the journal
     */
    public static function isFullHistoryRequested(mixed $value): bool
    {
        return is_scalar($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Imported papers of the journal by year of the first submission (import) of the paper and repository of its first
     * version: the versions of a paper can have different submission dates, the paper is counted once, like in the
     * statistics of the API
     * The API gives the imported papers by year, not by repository.
     * @param string|null $startAfterDate the "startStatsAfterDate" setting of the journal
     * @param Monolog\Logger|null $logger
     * @return array<int, array<string, int>> year => repository label => number of papers
     */
    private function getImportedByYearAndRepo(?string $startAfterDate, ?Monolog\Logger $logger): array
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $papers = $db->select()
                ->from(T_PAPERS, [
                    'PAPERID',
                    'FIRST_SUBMISSION_DATE' => new Zend_Db_Expr('MIN(SUBMISSION_DATE)'),
                    'REPOID' => new Zend_Db_Expr("SUBSTRING_INDEX(GROUP_CONCAT(REPOID ORDER BY SUBMISSION_DATE, DOCID), ',', 1)"),
                ])
                ->where('RVID = ?', RVID)
                ->where('FLAG = ?', 'imported')
                ->group('PAPERID');

            if ($startAfterDate) {
                $papers->having('MIN(SUBMISSION_DATE) > ?', $startAfterDate);
            }

            $select = $db->select()
                ->from(['papers' => $papers], ['REPOID', 'YEAR' => new Zend_Db_Expr('YEAR(FIRST_SUBMISSION_DATE)'), 'NB' => new Zend_Db_Expr('COUNT(*)')])
                ->group(['REPOID', 'YEAR']);

            $imported = [];

            foreach ($db->fetchAll($select) as $row) {
                $label = (string)Episciences_Repositories::getLabel((int)$row['REPOID']);
                $imported[(int)$row['YEAR']][$label] = ($imported[(int)$row['YEAR']][$label] ?? 0) + (int)$row['NB'];
            }

            return $imported;
        } catch (Throwable $e) {
            $logger?->warning($e->getMessage());
            return [];
        }
    }

    /**
     * Number of papers of the journal with a temporary version (local repository), by year of the first submission of
     * the paper: the versions of a paper can have different submission dates, the paper is counted once, like in the
     * statistics of the API
     * @param int|null $year
     * @param string|null $startAfterDate the "startStatsAfterDate" setting of the journal
     * @param Monolog\Logger|null $logger
     * @return int|null null if it could not be counted
     */
    private function countPapersWithTemporaryVersion(?int $year, ?string $startAfterDate, ?Monolog\Logger $logger): ?int
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $papers = $db->select()
                ->from(T_PAPERS, ['PAPERID'])
                ->where('RVID = ?', RVID)
                ->where('STATUS NOT IN (?)', [Episciences_Paper::STATUS_DELETED, Episciences_Paper::STATUS_REMOVED])
                ->group('PAPERID')
                ->having('SUM(REPOID = ?) > 0', (int)Episciences_Repositories::EPISCIENCES_REPO_ID);

            if ($year) {
                $papers->having('YEAR(MIN(SUBMISSION_DATE)) = ?', $year);
            }

            if ($startAfterDate) {
                $papers->having('MIN(SUBMISSION_DATE) > ?', $startAfterDate);
            }

            $select = $db->select()->from(['papers' => $papers], [new Zend_Db_Expr('COUNT(*)')]);

            return (int)$db->fetchOne($select);
        } catch (Throwable $e) {
            $logger?->warning($e->getMessage());
            return null;
        }
    }

    /**
     * Whether the connected user can see the papers of the statistics
     */
    private static function canSeePapers(): bool
    {
        return Episciences_Auth::isLogged() && (Episciences_Auth::isSecretary(RVID) || Episciences_Auth::isAdministrator(RVID) || Episciences_Auth::isRoot());
    }

    /**
     * "displayStatistics" setting of the journal: statistics reserved for the editorial secretaries
     */
    private static function areStatisticsReserved(): bool
    {
        try {
            $journalSettings = Zend_Registry::get('reviewSettings');
        } catch (Zend_Exception) {
            return false;
        }

        return ($journalSettings[Episciences_Review::SETTING_DISPLAY_STATISTICS] ?? null) === self::DISPLAY_STATISTICS_RESERVED;
    }

    /**
     * Keeps the papers of the figures in session for the papers action
     * @param array<string, array{docIds: int[], hidden: int}> $paperLists
     */
    private function storePaperLists(array $paperLists, ?int $year, bool $fullHistory = false): void
    {
        if (!$paperLists || !self::canSeePapers()) {
            return;
        }

        $session = new Zend_Session_Namespace(self::PAPERS_SESSION_NAMESPACE);
        $session->{self::getPapersSessionKey($year, $fullHistory)} = ['uid' => (int)Episciences_Auth::getUid(), 'lists' => $paperLists];
        $session->setExpirationSeconds(self::PAPERS_SESSION_TTL);
    }

    /**
     * Title of the papers window, by figure
     * @return array<string, string>
     */
    private function getPaperListTitles(): array
    {
        $titles = [
            self::NB_SUBMISSIONS => 'soumissions',
            self::NB_IMPORTED => 'articles importés',
            self::NB_IMPORTED_PUBLISHED => 'articles importés publiés',
            self::NB_PUBLISHED => 'articles publiés',
            self::TOTAL_PUBLISHED_PAPERS => 'articles publiés (importés inclus)',
            self::NB_ACCEPTED => 'articles acceptés',
            self::NB_REFUSED => 'articles refusés',
            self::NB_ACCEPTED_NOT_YET_PUBLISHED => 'articles acceptés (non encore publiés)',
            self::NB_OTHER_STATUS => 'autres statuts',
            self::SUBMISSION_ACCEPTANCE_DELAY => 'délai soumission-acceptation',
            self::SUBMISSION_PUBLICATION_DELAY => 'délai soumission-publication',
        ];

        return array_map(fn(string $title) => ucfirst($this->view->translate($title)), $titles);
    }

    private static function toDate(?string $date): ?string
    {
        return ($date && ($time = strtotime($date)) !== false) ? date('Y-m-d', $time) : null;
    }

    /**
     * @param int $httpCode
     * @param array<string, mixed> $payload
     * @throws JsonException
     */
    private function sendJson(int $httpCode, array $payload): void
    {
        $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setHeader('Content-Type', 'application/json', true)
            ->setBody(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * A token to get the papers of the figures (administrators of the journal only),
     * or the reserved statistics (editorial secretaries: the page is hidden to the others)
     * @param Monolog\Logger|null $logger
     * @return string|null
     */
    private function getDelegatedToken(?Monolog\Logger $logger): ?string
    {
        if (!self::canSeePapers() && !(Episciences_Auth::isLogged() && self::areStatisticsReserved())) {
            return null;
        }

        $client = new DelegatedTokenClient(
            new Client(),
            $logger ?? new NullLogger(),
            (string)EPISCIENCES_API_URL,
            defined('EPISCIENCES_API_SECRET_KEY') ? (string)EPISCIENCES_API_SECRET_KEY : ''
        );

        return $client->get((int)Episciences_Auth::getUid(), RVCODE);
    }

    /**
     * @param string $uri
     * @param array $options
     * @param string|null $token
     * @return StreamInterface
     * @throws GuzzleException
     */
    private function askApi(string $uri, array $options = [], ?string $token = null): StreamInterface
    {
        $client = new Client();
        return $client->request('GET', EPISCIENCES_API_URL . $uri, self::getApiRequestOptions($options, $token))->getBody();
    }

    /**
     * Guzzle options of the requests to the Episciences API
     * @param array $query
     * @param string|null $token
     * @return array<string, mixed>
     */
    public static function getApiRequestOptions(array $query = [], ?string $token = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
        ];

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return [
            'headers' => $headers,
            'query' => $query,
            'connect_timeout' => self::API_CONNECT_TIMEOUT,
            'timeout' => self::API_TIMEOUT,
        ];
    }

    /**
     * No statistics for the requested year
     * @param int $yearQuery
     * @return void
     */
    private function renderError(int $yearQuery): void
    {
        Episciences_Tools::header('HTTP/1.1 404 Not Found');
        // plain text: the message is escaped by the view
        $this->view->message = $this->view->translate("Vous essayez de consulter les indicateurs statistiques pour l'année") . ' ' . $yearQuery;
        $this->view->description = "Aucune information n'est disponible pour cette page pour le moment.";
        $this->renderScript('error/error.phtml');
    }

    /**
     * The statistics could not be retrieved from the API
     * @param string $description
     * @return void
     */
    private function renderUnavailable(string $description): void
    {
        Episciences_Tools::header('HTTP/1.1 503 Service Unavailable');
        $this->view->message = 'Les indicateurs statistiques sont momentanément indisponibles.';
        $this->view->description = $description;
        $this->renderScript('error/error.phtml');
    }


    /**
     *
     * @param array $evalOptions
     * @param float|null $reviewsRequested
     * @param float|null $reviewsReceived
     * @param float|null $medianReviewsNumber
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     */
    private function processEvaluationStats(array $evalOptions, float &$reviewsRequested = null, float &$reviewsReceived = null, float &$medianReviewsNumber = null): void
    {
        $evalOptions = array_merge(['rvcode' => RVCODE], $evalOptions);
        $evalUri = 'statistics/evaluation';
        $evaluations = json_decode($this->askApi($evalUri, $evalOptions), true, 512, JSON_THROW_ON_ERROR);

        foreach ($evaluations as $stats) {
            $key = Episciences_Tools::convertToCamelCase($stats['name'] ?? '', '-');
            $value = $stats['value'] ?? null;

            switch ($key) {
                case 'reviewsRequested':
                    $reviewsRequested = $value !== null ? (float)$value : null;
                    break;
                case 'reviewsReceived':
                    $reviewsReceived = $value !== null ? (float)$value : null;
                    break;
                case 'medianReviewsNumber':
                    $medianReviewsNumber = $value !== null ? (float)$value : null;
                    break;
                default:
                    // unknown stat, ignore
                    break;
            }
        }
    }

}

