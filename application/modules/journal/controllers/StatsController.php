<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class StatsController extends Zend_Controller_Action
{
    public const COLORS_CODE = ["#8e5ea2", "#3e95cd", "#dd2222", "#c45850", "#3cba9f", "#e8c3b9", "#33ff99"];
    public const CHART_TYPE = [
        'BAR' => 'bar',
        'PIE' => 'pie',
        'BAR_H' => 'barH',
        'DOUGHNUT' => 'doughnut',
        'LINE' => 'line'
    ];

    public const ACCEPTED_SUBMISSIONS = Episciences_Paper::ACCEPTED_SUBMISSIONS;
    public const SUBMISSIONS_BY_YEAR = 'submissionsByYear';
    public const MORE_DETAILS = 'moreDetailsFromModifDate';
    public const NB_SUBMISSIONS = 'nbSubmissions';
    public const SUBMISSION_ACCEPTANCE_DELAY = 'delayBetweenSubmissionAndAcceptance';
    public const SUBMISSION_PUBLICATION_DELAY = 'delayBetweenSubmissionAndPublication';
    public const REFERENCE_YEAR = 2013;


    /**
     * @return void
     * @throws Zend_Exception
     * @throws JsonException
     */
    public function indexAction(): void
    {

        if (Zend_Registry::get('hideStatistics')) {
            Episciences_Tools::header('HTTP/1.0 403 Forbidden');
            $this->renderScript('index/notfound.phtml');
            return;
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $yearQuery = (!empty($request->getParam('year'))) ? (int)$request->getParam('year') : null;



        $journalSettings = Zend_Registry::get('reviewSettings');
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
        $params = ['withDetails' => ''];

        if($yearQuery){
            $params['year'] = $yearQuery;
        }

        $yearCategories = [];

        if ($askApi) {

            try { // api request
                $dashboard = json_decode($this->askApi($uri, $params), true, 512, JSON_THROW_ON_ERROR);
            } catch (GuzzleException $e) {
                $this->view->errorMessage = $errorMessage;
                trigger_error($e->getMessage());
                return;
            }

        }


        if (empty($dashboard)) {
            $this->renderError($yearQuery);
            return;
        }

        $details = $dashboard['details'];

        if (isset($details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR])){
            $yearCategories = array_keys($details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR]);
        }


       $navYears = $details[self::NB_SUBMISSIONS]['years']['indicator'];

       if ($startStatsAfterDate){
           $navYears = array_filter($navYears, static function($year) use($startStatsAfterDateYear){
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

        $allPublications = $allRefusals = $allAcceptations = $allOtherStatus = 0;
        $publicationsPercentage = $acceptationsPercentage = $refusalsPercentage = $otherStatusPercentage = null;

        if ($yearQuery) { // for stats by year
            $yearCategories = [$yearQuery];
        }

        $submissionsDelay = $details['averageDaysSubmissionAcceptation'];
        $publicationsDelay = $details['averageDaysSubmissionPublication'];
        $allSubmissions = $dashboard['value'][self::NB_SUBMISSIONS]; // all review submissions
        $totalByYear = 0;

        foreach ($yearCategories as $year) {

            $nbRefusals = $nbAcceptations = $nbOthers = 0;

            $nbPublications = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['publications'] ?? 0;
            $allPublications += $nbPublications; // l'ensemble de la revue

            // stats collectées par rapport à la date de modification
            $moreDetails = $details[self::NB_SUBMISSIONS][self::MORE_DETAILS] ?? [];
            $submissionsByYearResponse = array_key_exists($year, $moreDetails) ? $moreDetails[$year] : [];

            foreach ($submissionsByYearResponse as $values) {


                foreach ($values as $statusLabel => $nbSubmissions) {

                    $status = array_search($statusLabel, Episciences_Paper::STATUS_DICTIONARY, true);

                    if ($status === false) {
                        trigger_error("STATS: UNDEFINED_STATUS_DICTIONARY_LABEL $statusLabel");
                    }


                    if ($status === Episciences_Paper::STATUS_PUBLISHED) {
                        continue;
                    }

                    if ($status === Episciences_Paper::STATUS_REFUSED) {
                        $allRefusals += $nbSubmissions[self::NB_SUBMISSIONS];
                        $nbRefusals += $nbSubmissions[self::NB_SUBMISSIONS];
                    } elseif (in_array($status, self::ACCEPTED_SUBMISSIONS, true)) {
                        $allAcceptations += $nbSubmissions[self::NB_SUBMISSIONS];
                        $nbAcceptations += $nbSubmissions[self::NB_SUBMISSIONS];
                    } else {  // others status (except published status)
                        $allOtherStatus += $nbSubmissions[self::NB_SUBMISSIONS];
                        $nbOthers += $nbSubmissions[self::NB_SUBMISSIONS];
                    }

                    unset($status, $nbSubmissions);
                }

                $totalByYear = $nbRefusals + $nbAcceptations + $nbOthers;

            }

            $totalByYear += $nbPublications;

            $series[self::SUBMISSIONS_BY_YEAR]['submissions'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['submissions'] ?? 0; // only submissions (1st version) of the current year
            $series['acceptationByYear']['acceptations'][] = $nbAcceptations;
            $series['refusalsByYear']['refusals'][] = $nbRefusals;
            $series['publicationsByYear']['publications'][] = $nbPublications;
            $series['otherStatusByYear']['otherStatus'][] = $nbOthers; //totalNumberOfPapersAccepted
            $series[self::SUBMISSIONS_BY_YEAR]['acceptedSubmittedSameYear'][] = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['acceptedSubmittedSameYear'] ?? 0;


            if ($totalByYear) {
                $series['acceptationByYear']['percentage'][] = round($nbAcceptations / $totalByYear * 100, 2); //'acceptedSubmittedSameYear'
                $series['refusalsByYear']['percentage'][] = round($nbRefusals / $totalByYear * 100, 2);
                $series['publicationsByYear']['percentage'][] = round($nbPublications / $totalByYear * 100, 2);
                $series['otherStatusByYear']['percentage'][] = round($nbOthers / $totalByYear * 100, 2);
            }

            $subByYear = $details[self::NB_SUBMISSIONS]['submissionsByRepo'][$year] ?? [];

            // submission by repo
            foreach ($subByYear as $repoId => $val) {
                $series['submissionsByRepo'][$repoId][self::NB_SUBMISSIONS][] = $val['submissions'];
            }

            if (!empty($submissionsDelay)) {
                if (array_key_exists($year, $submissionsDelay)) {
                    $series[self::SUBMISSION_ACCEPTANCE_DELAY][] = $submissionsDelay[$year]['delay'];
                } else {
                    $series[self::SUBMISSION_ACCEPTANCE_DELAY][] = null;
                }
            }

            if (!empty($publicationsDelay)) {

                if (array_key_exists($year, $publicationsDelay)) {
                    $series[self::SUBMISSION_PUBLICATION_DELAY][] = $publicationsDelay[$year]['delay'];
                } else {
                    $series[self::SUBMISSION_PUBLICATION_DELAY][] = null;
                }
            }

        }

        unset($nbPublications, $nbRefusals, $nbOthers);

        if ($yearQuery) {
            $allSubmissions = $series[self::SUBMISSIONS_BY_YEAR]['submissions'][0];
            $allPublications = $series['publicationsByYear']['publications'][0]; // par année
            $allRefusals = $series['refusalsByYear']['refusals'][0];
            $allAcceptations = $series['acceptationByYear']['acceptations'][0];
            $allOtherStatus = $series['otherStatusByYear']['otherStatus'][0];

            if ($totalByYear) {
                $publicationsPercentage = $series['publicationsByYear']['percentage'][0];
                $refusalsPercentage = $series['refusalsByYear']['percentage'][0];
                $acceptationsPercentage = $series['acceptationByYear']['percentage'][0];
                $otherStatusPercentage = $series['otherStatusByYear']['percentage'][0];
            }

            unset($totalByYear);

            $this->view->acceptedSubmittedSameYaer = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['acceptedSubmittedSameYear'];
            $this->view->acceptationRateSubmittedSameYear = $details[self::NB_SUBMISSIONS][self::SUBMISSIONS_BY_YEAR][$year]['acceptanceRate'];


        } elseif ($allSubmissions) {
            $publicationsPercentage = round($dashboard['value']['totalPublished'] / $allSubmissions * 100, 2);
            $refusalsPercentage = round($allRefusals / $allSubmissions * 100, 2);
            $acceptationsPercentage = round($allAcceptations / $allSubmissions * 100, 2);
            $otherStatusPercentage = round($allOtherStatus / $allSubmissions * 100, 2);
        }

        $label1 = ucfirst($this->view->translate('soumissions'));
        $label2 = ucfirst($this->view->translate('articles publiés'));
        $label3 = ucfirst($this->view->translate('articles refusés'));
        $label4 = ucfirst($this->view->translate('articles acceptés'));
        $label5 = ucfirst($this->view->translate('autres statuts'));
        $label6 = ucfirst($this->view->translate('articles acceptés (soumis la même année)'));

        // figure 1
        $this->view->chart1Title = $this->view->translate("En un coup d'oeil");

        $seriesJs['allSubmissionsPercentage']['datasets'][] = [
            'data' => [$publicationsPercentage, $acceptationsPercentage, $refusalsPercentage, $otherStatusPercentage],
            'backgroundColor' => [self::COLORS_CODE[4], self::COLORS_CODE[5], self::COLORS_CODE[2], self::COLORS_CODE[0]]
        ];

        $seriesJs['allSubmissionsPercentage']['labels'] = [$label2, $label4, $label3, $label5];
        $seriesJs['allSubmissionsPercentage']['chartType'] = self::CHART_TYPE['PIE'];

        //figure 2
        $this->view->chart2Title = !$yearQuery ?
            $this->view->translate("La répartition des <code>soumissions</code>par <code>année</code> et par <code>statut</code>") :
            $this->view->translate("La répartition des <code>soumissions</code> par <code>statut</code>");

        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label1, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['submissions'] ?? 0, 'backgroundColor' => self::COLORS_CODE[1]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label2, 'data' => $series['publicationsByYear']['publications'] ?? 0, 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label4, 'data' => $series['acceptationByYear']['acceptations'] ?? 0, 'backgroundColor' => self::COLORS_CODE[5]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label3, 'data' => $series['refusalsByYear']['refusals'] ?? 0, 'backgroundColor' => self::COLORS_CODE[2]];

        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label5, 'data' => $series['otherStatusByYear']['otherStatus'] ?? 0, 'backgroundColor' => self::COLORS_CODE[0]];
        $seriesJs[self::SUBMISSIONS_BY_YEAR]['datasets'][] = ['label' => $label6, 'data' => $series[self::SUBMISSIONS_BY_YEAR]['acceptedSubmittedSameYear'] ?? 0, 'backgroundColor' => self::COLORS_CODE[6]];


        $seriesJs[self::SUBMISSIONS_BY_YEAR]['chartType'] = self::CHART_TYPE['BAR'];


        foreach ($series['submissionsByRepo'] as $repoId => $values) {
            $backgroundColor = ($repoId > count(self::COLORS_CODE) - 1) ? self::COLORS_CODE[array_rand(self::COLORS_CODE)] : self::COLORS_CODE[$repoId];
            $repoLabel = Episciences_Repositories::getLabel($repoId);

            //figure3
            $seriesJs['submissionsByRepo']['repositories']['datasets'][] = ['label' => $repoLabel, 'data' => $values[self::NB_SUBMISSIONS], 'backgroundColor' => $backgroundColor];

        }
        $this->view->chart3Title = !$yearQuery ?
            $this->view->translate("Répartition des soumissions par <code>année</code> et par <code>archive</code>") :
            $this->view->translate("Répartition des soumissions par <code>archive</code>");

        $seriesJs['submissionsByRepo']['repositories']['chartType'] = self::CHART_TYPE['BAR'];
        $seriesJs['submissionsByRepo']['percentage']['chartType'] = self::CHART_TYPE['PIE'];


        // figure4
        $this->view->chart4Title = $this->view->translate('Délai moyen en <code>jours</code> entre <code>dépôt et acceptation</code> (<code>dépôt et publication</code>)');

        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Acceptation'), 'data' => $series[self::SUBMISSION_ACCEPTANCE_DELAY], 'backgroundColor' => self::COLORS_CODE[5]];
        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Publication'), 'data' => $series[self::SUBMISSION_PUBLICATION_DELAY], 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs['submissionDelay']['chartType'] = self::CHART_TYPE['BAR_H'];

        $isAvailableUsersStats = !$yearQuery && array_key_exists('nbUsers', $dashboard['value']);

        //Users stats
        $rolesJs = [];
        $nbUsersByRole = [];
        $data = [];

        if ($isAvailableUsersStats) {
            $allUsers = $dashboard['value']['nbUsers'];
            $usersDetails = $details['nbUsers'];
            $roles = array_keys($usersDetails);
            $rootKey = array_search(Episciences_Acl::ROLE_ROOT, $roles, true);

            if ($rootKey !== false) {
                unset($roles[$rootKey]);
            }

            foreach ($roles as $role) {
                $rolesJs[] = $this->view->translate($role);
                $data[] = $usersDetails[$role]['nbUsers'];
            }

            //figure 5
            $this->view->chart5Title = $this->view->translate("Le nombre d'utilisateurs par <code>rôles</code>");
            $nbUsersByRole['chartType'] = self::CHART_TYPE['BAR'];
            $this->view->allUsers = $allUsers;

        }

        $nbUsersByRole['datasets'][] = ['label' => $this->view->translate("Nombre d'utilisateurs"), 'data' => $data, 'backgroundColor' => self::COLORS_CODE[4]];

        $this->view->roles = $rolesJs;
        $this->view->nbUsersByRole = $nbUsersByRole;


        $this->view->allSubmissionsJs = $allSubmissions;

        if (!$yearQuery) {

            try {
                $totalPublishedArticles = (int)json_decode($this->askApi('journals/stats/nb-submissions/' . RVCODE, ['status' => Episciences_Paper::STATUS_PUBLISHED]), true, 512, JSON_THROW_ON_ERROR)['value'];
                $totalArticles = (int)json_decode($this->askApi('journals/stats/nb-submissions/' . RVCODE), true, 512, JSON_THROW_ON_ERROR)['value'];
                $totalImportedArticles = (int)json_decode($this->askApi('journals/stats/nb-submissions/' . RVCODE, ['flag' => 'imported']), true, 512, JSON_THROW_ON_ERROR)['value'];

                if ($totalImportedArticles > 0) {
                    $this->view->totalImportedArticles = $totalImportedArticles;
                }

                if ($totalPublishedArticles > 0) {
                    $this->view->totalPublishedArticles = $totalPublishedArticles;
                }

                $this->view->totalArticles = $totalArticles;
            } catch (GuzzleException|JsonException  $e) {
                trigger_error($e->getMessage());

            }


        }

        $this->view->allPublications = !$yearQuery ? $dashboard['value']['totalPublished'] : $allPublications;
        $this->view->allRefusals = $allRefusals;
        $this->view->allAcceptations = $allAcceptations;
        $this->view->allOtherStatus = $allOtherStatus;

        $this->view->publicationsPercentage = $publicationsPercentage;
        $this->view->refusalsPercentage = $refusalsPercentage;
        $this->view->acceptationsPercentage = $acceptationsPercentage;

        $this->view->yearCategoriesJs = $yearCategories;
        $this->view->seriesJs = $seriesJs;
        $this->view->yearQuery = $yearQuery;
        $this->view->errorMessage = null;
        $this->view->isAvailableUsersStats = $isAvailableUsersStats;
    }

    /**
     * @param string $uri
     * @param array $options
     * @param bool $isAsynchronous
     * @return StreamInterface
     * @throws GuzzleException
     */
    private function askApi(string $uri, array $options = [], bool $isAsynchronous = false): StreamInterface
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

        if (!$isAsynchronous) {
            try {
                $request = $client->request('GET', $url, $gOptions);
            } catch (GuzzleException $e) {
                error_log('STATISTICS_MODULE: ' . $e->getMessage());
                throw $e;
            }

            return $request->getBody();

        }

        $promise = $client->requestAsync('GET', $url, $gOptions);

        $promise->then(
            function (ResponseInterface $res) {
                return $res->getBody();
            },
            function (RequestException $e) {
                error_log('STATISTICS_MODULE: ' . $e->getMessage());
                throw $e;
            }
        );

        /** @var GuzzleHttp\Psr7\Response $response */
        $response = $promise->wait();
        return $response->getBody();

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

        if($yearQuery) {
            $message .= " <code>$yearQuery</code>";
        }

        $this->view->message = $message;
        $this->view->description = "Aucune information n'est disponible pour cette page pour le moment.";
        $this->renderScript('error/error.phtml');
    }

}

