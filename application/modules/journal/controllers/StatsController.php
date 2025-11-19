<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

class StatsController extends Zend_Controller_Action
{
    public const COLORS_CODE = ["#8e5ea2", "#3e95cd", "#dd2222", "#c45850", "#3cba9f", "#e8c3b9", "#33ff99", "#29c73b"];
    public const CHART_TYPE = [
        'BAR' => 'bar',
        'PIE' => 'pie',
        'BAR_H' => 'barH',
        'DOUGHNUT' => 'doughnut',
        'LINE' => 'line'
    ];

    public const ACCEPTED_SUBMISSIONS = Episciences_Paper::ACCEPTED_SUBMISSIONS;
    public const SUBMISSIONS_BY_YEAR = 'submissionsByYear';

    public const NB_SUBMISSIONS = 'nbSubmissions';
    public const NB_IMPORTED = 'nbImported';
    public const NB_PUBLISHED = 'nbPublished';
    public const NB_ACCEPTED_NOT_YET_PUBLISHED = 'nbAcceptedNotYetPublished';
    public const NB_OTHER_STATUS = 'nbOtherStatus';
    public const NB_REFUSED = 'nbRefused';
    public const NB_ACCEPTED = 'nbAccepted';
    public const SUBMISSION_ACCEPTANCE_DELAY = 'submissionAcceptanceTime';
    public const SUBMISSION_PUBLICATION_DELAY = 'submissionPublicationTime';
    public const NB_USERS = 'nbUsers';


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
             error_log($e->getMessage());
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
            try {
                $dashboard = json_decode($this->askApi($uri, $params), true, 512, JSON_THROW_ON_ERROR);
            } catch (GuzzleException|JsonException $e) {
                $this->view->errorMessage = $errorMessage;
                $logger?->warning($e->getMessage());
            }

            if (empty($dashboard)) {
                $this->renderError($yearQuery);
                return;
            }

            try {
                $this->processEvaluationStats($evalOptions, $reviewsRequested, $reviewsReceived, $medianReviewsNumber);
            } catch (GuzzleException|JsonException $e) {
                $this->view->errorMessage = $errorMessage;
                $logger?->warning($e->getMessage());
            }
        }

        $repositories = Episciences_Repositories::getRepositoriesByLabel();
        $details = $dashboard['details'] ?? [];

        if (isset($details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR])) {
            $yearCategories = array_keys($details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR]);
        }

        $navYears = $details[self::NB_SUBMISSIONS]['years']['relevantYears'];

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

        // initialisation
        $series = [];
        $series[self::SUBMISSION_ACCEPTANCE_DELAY] = [];
        $series[self::SUBMISSION_PUBLICATION_DELAY] = [];
        $series['submissionsByRepo'] = [];
        $series[self::SUBMISSIONS_BY_YEAR] = [];


        if ($yearQuery) { // for stats by year
            $yearCategories = [$yearQuery];
        }

        foreach ($yearCategories as $year) {

            $series[self::SUBMISSIONS_BY_YEAR]['submissions'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['submissions'] ?? 0;
            $series[self::SUBMISSIONS_BY_YEAR]['published'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['published'] ?? 0;
            $series[self::SUBMISSIONS_BY_YEAR]['accepted'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['accepted'] ?? 0;
            $series[self::SUBMISSIONS_BY_YEAR]['refused'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['refused'] ?? 0;
            $series[self::SUBMISSIONS_BY_YEAR]['others'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['others'] ?? 0;
            $series[self::SUBMISSIONS_BY_YEAR]['acceptedSubmittedSameYear'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['acceptedSubmittedSameYear'] ?? 0;
            $series[self::SUBMISSIONS_BY_YEAR]['acceptedNotYetPublished'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['acceptedNotYetPublished'] ?? 0;


            $subByYear = $details[self::NB_SUBMISSIONS]['submissionsByRepo'][$year] ?? [];

            // submission by repo
            foreach ($subByYear as $repoLabel => $val) {
                $series['submissionsByRepo'][$repoLabel][self::NB_SUBMISSIONS][] = $val['submissions'];
            }

            if (!empty($details[self::SUBMISSION_ACCEPTANCE_DELAY])) {
                $series[self::SUBMISSION_ACCEPTANCE_DELAY][] = $details[self::SUBMISSION_ACCEPTANCE_DELAY][$year]['delay']['value'] ?? null;
            }

            if (!empty($details[self::SUBMISSION_PUBLICATION_DELAY])) {
                $series[self::SUBMISSION_PUBLICATION_DELAY][] = $details[self::SUBMISSION_PUBLICATION_DELAY][$year]['delay']['value'] ?? null;
            }

        }


        // Figure 1 > At a glance

        $label1 = ucfirst($this->view->translate('soumissions'));
        $label2 = ucfirst($this->view->translate('articles publiés'));
        $label3 = ucfirst($this->view->translate('articles refusés'));
        $label4 = ucfirst($this->view->translate('articles acceptés'));
        $label5 = ucfirst($this->view->translate('autres statuts'));
        $label6 = ucfirst($this->view->translate('articles acceptés (soumis la même année)'));
        $label7 = ucfirst($this->view->translate('articles acceptés (non encore publiés)'));

        $rateLabel1 = ucfirst($this->view->translate('taux de publication'));
        $rateLabel3 = ucfirst($this->view->translate('taux de refus'));
        $rateLabel2 = ucfirst($this->view->translate("taux d'acceptation"));
        $rateLabel4 = ucfirst($this->view->translate('autre'));


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
        $totalPublished = $dashboard['value']['totalWithoutStartAfterDate']['totalPublished'] ?? ($allPublications + $importedPublished)  ?? null;
        $totalImportedPublished =  $dashboard['value']['totalWithoutStartAfterDate']['totalImportedPublished'] ?? $importedPublished ?? null;


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


        $this->view->acceptedNotYetPublished = $dashboard['value'][self::NB_ACCEPTED_NOT_YET_PUBLISHED] ?? null;
        $this->view->acceptedSubmittedSameYear = $dashboard['value']['totalAcceptedSubmittedSameYear'] ?? null;
        $this->view->publishedSubmittedSameYear = $dashboard['value']['totalPublishedSubmittedSameYear'] ?? null;
        $this->view->refusedSubmittedSameYear = $dashboard['value']['totalRefusedSubmittedSameYear'] ?? null;
        $this->view->reviewsRequested = $reviewsRequested ?? null;
        $this->view->reviewsReceived = $reviewsReceived ?? null;
        $this->view->medianReviewsNumber = $medianReviewsNumber ?? null;


        // Percentages
        $publishedPercentage = $dashboard['value']['rate']['published'] ?? null;
        $acceptedPercentage = $dashboard['value']['rate']['accepted'] ?? null;
        $refusedPercentage = $dashboard['value']['rate']['refused'] ?? null;
        $otherPercentage = $dashboard['value']['rate']['other'] ?? null;

        $this->view->acceptanceRate = $acceptedPercentage;
        $this->view->publicationRate = $publishedPercentage;
        $this->view->declineRate = $refusedPercentage;


        $piChartData = [$acceptedPercentage, $refusedPercentage, $otherPercentage];
        $piChartColors = [self::COLORS_CODE[5], self::COLORS_CODE[2], self::COLORS_CODE[0]];
        $piChartLabels = [$rateLabel2, $rateLabel3, $rateLabel4];

        $seriesJs['allSubmissionsPercentage']['datasets'][] = [
            'data' => $piChartData,
            'backgroundColor' => $piChartColors
        ];

        $seriesJs['allSubmissionsPercentage']['labels'] = $piChartLabels;
        $seriesJs['allSubmissionsPercentage']['chartType'] = self::CHART_TYPE['PIE'];


        $this->view->submissionAcceptanceTime = $dashboard['value'][self::SUBMISSION_ACCEPTANCE_DELAY]['value'] ?? null;
        $this->view->submissionAcceptanceTimeUnit = $dashboard['value'][self::SUBMISSION_ACCEPTANCE_DELAY]['unit'] ?? null;
        $this->view->submissionPublicationTime = $dashboard['value'][self::SUBMISSION_PUBLICATION_DELAY]['value'] ?? null;
        $this->view->submissionPublicationTimeUnit = $dashboard['value'][self::SUBMISSION_PUBLICATION_DELAY]['unit'] ?? null;
        $this->view->submissionPublicationTimeMedian = $dashboard['value']['submissionPublicationTimeMedian']['value'] ?? null;
        $this->view->submissionPublicationTimeMedianUnit = $dashboard['value']['submissionPublicationTimeMedian']['unit'] ?? null;
        $this->view->submissionAcceptanceTimeMedian = $dashboard['value']['submissionAcceptanceTimeMedian']['value'] ?? null;
        $this->view->submissionAcceptanceTimeMedianUnit = $dashboard['value']['submissionAcceptanceTimeMedian']['unit'] ?? null;


        //figure 2 > Breakdown of submissions by year and status
        $this->view->chart2Title = !$yearQuery ?
            $this->view->translate("La répartition des <code>soumissions</code>par <code>année</code> et par <code>statut</code>") :
            $this->view->translate("La répartition des <code>soumissions</code> par <code>statut</code>");

        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label1, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['submissions'] ?? 0, 'backgroundColor' => self::COLORS_CODE[1]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label2, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['published'] ?? 0, 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label4, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['accepted'] ?? 0, 'backgroundColor' => self::COLORS_CODE[5]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label7, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['acceptedNotYetPublished'] ?? 0, 'backgroundColor' => self::COLORS_CODE[7]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label6, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['acceptedSubmittedSameYear'] ?? 0, 'backgroundColor' => self::COLORS_CODE[6]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label3, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['refused'] ?? 0, 'backgroundColor' => self::COLORS_CODE[2]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label5, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['others'] ?? 0, 'backgroundColor' => self::COLORS_CODE[0]];


        $seriesJs[self::SUBMISSIONS_BY_YEAR]['chartType'] = self::CHART_TYPE['BAR'];

        // Figure3 > Breakdown of submissions by year and repository


        foreach ($series['submissionsByRepo'] as $repoLabel => $values) {
            $repoId = $repositories[$repoLabel]['id'];
            $colorsCodeSize = count(self::COLORS_CODE);
            $backgroundColor = self::COLORS_CODE[$repoId % $colorsCodeSize];
            $seriesJs['submissionsByRepo']['repositories']['datasets'][] = ['label' => $repoLabel, 'data' => $values[self::NB_SUBMISSIONS], 'backgroundColor' => $backgroundColor];

        }

        $this->view->chart3Title = !$yearQuery ?
            $this->view->translate("Répartition des soumissions par <code>année</code> et par <code>archive</code>") :
            $this->view->translate("Répartition des soumissions par <code>archive</code>");

        $seriesJs['submissionsByRepo']['repositories']['chartType'] = self::CHART_TYPE['BAR'];
        $seriesJs['submissionsByRepo']['rate']['chartType'] = self::CHART_TYPE['PIE'];


        // figure4 > Average time in days between submission and acceptance (submission and publication)


        $this->view->chart4Title = $this->view->translate('Délai moyen en <code>jours</code> entre <code>dépôt et acceptation</code> (<code>dépôt et publication</code>)');

        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Acceptation'), 'data' => $series[self::SUBMISSION_ACCEPTANCE_DELAY], 'backgroundColor' => self::COLORS_CODE[5]];
        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Publication'), 'data' => $series[self::SUBMISSION_PUBLICATION_DELAY], 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs['submissionDelay']['chartType'] = self::CHART_TYPE['BAR_H'];

        $isAvailableUsersStats = !$yearQuery && isset($dashboard['value'][self::NB_USERS]);

        // Figure 5 > Users stats > Number of users by roles : these statistics are not available by year because the database structure does not allow this information to be obtained.
        $rolesJs = [];
        $nbUsersByRole = [];
        $data = [];

        if ($isAvailableUsersStats) {
            $allUsers = $dashboard['value'][self::NB_USERS];
            $usersDetails = $details[self::NB_USERS];
            $roles = array_keys($usersDetails);
            $rootKey = array_search(Episciences_Acl::ROLE_ROOT, $roles, true);

            if ($rootKey !== false) {
                unset($roles[$rootKey]);
            }

            foreach ($roles as $role) {
                $rolesJs[] = $this->view->translate($role);
                $data[] = $usersDetails[$role][self::NB_USERS];
            }

            $this->view->chart5Title = $this->view->translate("Le nombre d'utilisateurs par <code>rôles</code>");
            $nbUsersByRole['chartType'] = self::CHART_TYPE['BAR'];
            $this->view->allUsers = $allUsers;

        }

        $nbUsersByRole['datasets'][] = ['label' => $this->view->translate("Nombre d'utilisateurs"), 'data' => $data, 'backgroundColor' => self::COLORS_CODE[4]];

        $this->view->roles = $rolesJs;
        $this->view->nbUsersByRole = $nbUsersByRole;


        $this->view->yearCategoriesJs = $yearCategories;
        $this->view->seriesJs = $seriesJs;
        $this->view->yearQuery = $yearQuery;
        $this->view->errorMessage = null;
        $this->view->isAvailableUsersStats = $isAvailableUsersStats;

    }

    /**
     * @param string $uri
     * @param array $options
     * @return StreamInterface
     * @throws GuzzleException
     */
    private function askApi(string $uri, array $options = []): StreamInterface
    {
        $url = EPISCIENCES_API_URL . $uri;

        $headers = [
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
        ];

        $gOptions = [
            'headers' => $headers,
            'query' => $options
        ];

        $client = new Client();
        return $client->request('GET', $url, $gOptions)->getBody();

    }

    /**
     * @param int|null $yearQuery
     * @param string $header
     * @return void
     */
    private function renderError(int $yearQuery = null, string $header = 'HTTP/1.1 404 Not Found'): void
    {
        Episciences_Tools::header($header);
        $message = $this->view->translate("Vous essayez de consulter les indicateurs statistiques pour l'année");

        if ($yearQuery) {
            $message .= " <code>$yearQuery</code>";
        }

        $this->view->message = $message;
        $this->view->description = "Aucune information n'est disponible pour cette page pour le moment.";
        $this->renderScript('error/error.phtml');
    }


    /**
     * /!\ variables used dynamically: do not delete
     * @param $evalOptions
     * @param float|null $reviewsRequested
     * @param float|null $reviewsReceived
     * @param float|null $medianReviewsNumber
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     */
    private function processEvaluationStats($evalOptions, float &$reviewsRequested = null, float &$reviewsReceived = null, float &$medianReviewsNumber = null): void
    {

        if (!isset($evalOptions['rvcode'])) {
            $evalOptions['rvcode'] = RVCODE;
        }

        $evalOptions = array_merge($evalOptions, $evalOptions);
        $evalUri = 'statistics/evaluation';
        $evaluations = json_decode($this->askApi($evalUri, $evalOptions), true, 512, JSON_THROW_ON_ERROR);

        foreach ($evaluations as $stats) {
            $var = Episciences_Tools::convertToCamelCase($stats['name'], '-');
            $$var = $stats['value'] ?? null;
        }
    }

}

