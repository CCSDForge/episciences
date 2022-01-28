<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class StatsController extends Zend_Controller_Action
{
    public const COLORS_CODE = ["#8e5ea2", "#3e95cd", "#dd2222", "#c45850", "#3cba9f", "#e8c3b9"];
    public const CHART_TYPE = ['BAR' => 'bar', 'PIE' => 'pie', 'BAR_H' => 'barH', 'DOUGHNUT' => 'doughnut', 'LINE' => 'line'];

    public const ACCEPTED_SUBMISSIONS = Episciences_Paper::ACCEPTED_SUBMISSIONS;

    public const CURRENT_RVID = RVID;

    public function indexAction(): void
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $yearQuery = (!empty($request->getParam('year'))) ? (int)$request->getParam('year') : null;

        $uri = 'review/stats/dashboard';

        $errorMessage = "Une erreur s'est produite lors de la récupération des statistiques. Nous vous suggérons de ré-essayer dans quelques instants. Si le problème persiste vous devriez contacter le support de la revue.";

        try { // api request
            $result = json_decode($this->askApi($uri, ['rvid' => self::CURRENT_RVID, 'withDetails' => '', 'year' => $yearQuery]), true);
        } catch (GuzzleException $e) {
            $this->view->errorMessage = $errorMessage;
            return;
        }

        if (empty($result)) {
            $this->view->errorMessage = 'Aucun résultat';
            return;
        }

        $dashboard = $result[array_key_first($result)];
        $details = $dashboard['submissions']['details'];
        $yearCategories = array_keys($details['submissionsByYear']);

        $this->view->yearCategories = $yearCategories; // navigation

        if ($yearQuery && !in_array($yearQuery, $yearCategories, true)) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            return;
        }

        // initialisation
        $series = [];
        $series['delayBetweenSubmissionAndAcceptance'] = [];
        $series['delayBetweenSubmissionAndPublication'] = [];

        $allPublications = $allRefusals = $allAcceptations = $allOtherStatus = 0;
        $publicationsPercentage = $acceptationsPercentage = $refusalsPercentage = $otherStatusPercentage = null;

        if ($yearQuery) { // for stats by year
            $yearCategories = [$yearQuery];
        }

        $submissionsDelay = $dashboard['submissionsDelay'];
        $publicationsDelay = $dashboard['publicationsDelay'];
        $allSubmissions = $dashboard['submissions']['value']; // all review submissions
        $totalByYear = 0;

        foreach ($yearCategories as $year) {

            $nbRefusals = $nbAcceptations = $nbOthers = 0;

            $nbPublications = $dashboard['submissions']['details']['submissionsByYear'][$year]['publications'];
            $allPublications += $nbPublications; // l'ensemble de la revue

            // stats collectées par rapport à la date de modification
            $submissionsByYearResponse = array_key_exists($year, $details['moreDetails']) ? $details['moreDetails'][$year] : [];

            foreach ($submissionsByYearResponse as $values) {

                foreach ($values as $status => $nbSubmissions) {

                    if ($status === Episciences_Paper::STATUS_PUBLISHED) {
                        continue;
                    }

                    if ($status === Episciences_Paper::STATUS_REFUSED) {
                        $allRefusals += $nbSubmissions['nbSubmissions'];
                        $nbRefusals += $nbSubmissions['nbSubmissions'];
                    } elseif (in_array($status, self::ACCEPTED_SUBMISSIONS, true)) {
                        $allAcceptations += $nbSubmissions['nbSubmissions'];
                        $nbAcceptations += $nbSubmissions['nbSubmissions'];
                    } else {  // others status (except published status)
                        $allOtherStatus += $nbSubmissions['nbSubmissions'];
                        $nbOthers += $nbSubmissions['nbSubmissions'];
                    }

                    unset($status, $nbSubmissions);
                }

                $totalByYear = $nbRefusals + $nbAcceptations + $nbOthers;

            }

            $totalByYear += $nbPublications;

            $series['submissionsByYear']['submissions'][] = $dashboard['submissions']['details']['submissionsByYear'][$year]['submissions']; // only submissions (1st version) of the current year
            $series['acceptationByYear']['acceptations'][] = $nbAcceptations;
            $series['refusalsByYear']['refusals'][] = $nbRefusals;
            $series['publicationsByYear']['publications'][] = $nbPublications;
            $series['otherStatusByYear']['otherStatus'][] = $nbOthers;

            if ($totalByYear) {
                $series['acceptationByYear']['percentage'][] = round($nbAcceptations / $totalByYear * 100, 2);
                $series['refusalsByYear']['percentage'][] = round($nbRefusals / $totalByYear * 100, 2);
                $series['publicationsByYear']['percentage'][] = round($nbPublications / $totalByYear * 100, 2);
                $series['otherStatusByYear']['percentage'][] = round($nbOthers / $totalByYear * 100, 2);
            }

            // submission by repo
            foreach ($details['submissionsByRepo'][$year] as $repoId => $val) {
                $series['submissionsByRepo'][$repoId]['nbSubmissions'][] = $val['submissions'];
            }

            if (!empty($submissionsDelay['details'])) {
                if (array_key_exists($year, $submissionsDelay['details'][self::CURRENT_RVID])) {
                    $series['delayBetweenSubmissionAndAcceptance'][] = $submissionsDelay['details'][self::CURRENT_RVID][$year]['delay'];
                } else {
                    $series['delayBetweenSubmissionAndAcceptance'][] = null;
                }
            }

            if (!empty($publicationsDelay['details'])) {

                if (array_key_exists($year, $publicationsDelay['details'][self::CURRENT_RVID])) {
                    $series['delayBetweenSubmissionAndPublication'][] = $publicationsDelay['details'][self::CURRENT_RVID][$year]['delay'];
                } else {
                    $series['delayBetweenSubmissionAndPublication'][] = null;
                }
            }

        }

        unset($nbPublications, $nbRefusals, $nbPublications, $nbOthers);

        if ($yearQuery) {
            $allSubmissions = $series['submissionsByYear']['submissions'][0];
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

        } elseif ($allSubmissions) {
            $publicationsPercentage = round($allPublications / $allSubmissions * 100, 2);
            $refusalsPercentage = round($allRefusals / $allSubmissions * 100, 2);
            $acceptationsPercentage = round($allAcceptations / $allSubmissions * 100, 2);
            $otherStatusPercentage = round($allOtherStatus / $allSubmissions * 100, 2);
        }

        $label1 = ucfirst($this->view->translate('soumissions'));
        $label2 = ucfirst($this->view->translate('articles publiés'));
        $label3 = ucfirst($this->view->translate('articles refusés'));
        $label4 = ucfirst($this->view->translate('articles acceptés'));
        $label5 = ucfirst($this->view->translate('autres statuts'));

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

        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label1, 'data' => $series['submissionsByYear']['submissions'], 'backgroundColor' => self::COLORS_CODE[1]];
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label2, 'data' => $series['publicationsByYear']['publications'], 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label4, 'data' => $series['acceptationByYear']['acceptations'], 'backgroundColor' => self::COLORS_CODE[5]];
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label3, 'data' => $series['refusalsByYear']['refusals'], 'backgroundColor' => self::COLORS_CODE[2]];

        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label5, 'data' => $series['otherStatusByYear']['otherStatus'], 'backgroundColor' => self::COLORS_CODE[0]];

        $seriesJs['submissionsByYear']['chartType'] = self::CHART_TYPE['BAR'];


        foreach ($series['submissionsByRepo'] as $repoId => $values) {
            $backgroundColor = ($repoId > count(self::COLORS_CODE) - 1) ? self::COLORS_CODE[array_rand(self::COLORS_CODE)] : self::COLORS_CODE[$repoId];
            $repoLabel = Episciences_Repositories::getLabel($repoId);

            //figure3
            $seriesJs['submissionsByRepo']['repositories']['datasets'][] = ['label' => $repoLabel, 'data' => $values['nbSubmissions'], 'backgroundColor' => $backgroundColor];

        }
        $this->view->chart3Title = !$yearQuery ?
            $this->view->translate("Répartition des soumissions par <code>année</code> et par <code>archive</code>") :
            $this->view->translate("Répartition des soumissions par <code>archive</code>");

        $seriesJs['submissionsByRepo']['repositories']['chartType'] = self::CHART_TYPE['BAR'];
        $seriesJs['submissionsByRepo']['percentage']['chartType'] = self::CHART_TYPE['PIE'];


        // figure4
        $this->view->chart4Title = $this->view->translate('Délai moyen en <code>jours</code> entre <code>dépôt et acceptation</code> (<code>dépôt et publication</code>)');

        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Acceptation'), 'data' => $series['delayBetweenSubmissionAndAcceptance'], 'backgroundColor' => self::COLORS_CODE[5]];
        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Publication'), 'data' => $series['delayBetweenSubmissionAndPublication'], 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs['submissionDelay']['chartType'] = self::CHART_TYPE['BAR_H'];

        $isAvailableUsersStats = array_key_exists('users', $dashboard);

        //Users stats
        $rolesJs = [];
        $nbUsersByRole = [];
        $data = [];

        if ($isAvailableUsersStats) {
            $allUsers = $dashboard['users']['value'];
            $usersDetails = $dashboard['users']['details'][self::CURRENT_RVID];
            $roles = array_keys($usersDetails);
            $rootKey = array_search(Episciences_Acl::ROLE_ROOT, $roles, true);

            if ($rootKey !== false) {
                unset($roles[$rootKey]);
            }

            foreach ($roles as $key => $role) {
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
        $this->view->allPublications = $allPublications;
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
     * @param $uri
     * @param array $options
     * @param bool $isAsynchronous
     * @return StreamInterface
     * @throws GuzzleException
     */
    private function askApi($uri, array $options = [], $isAsynchronous = false): StreamInterface
    {
        $url = EPISCIENCES_API_URL . $uri;

        $headers = [
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'X-AUTH-TOKEN' => EPISCIENCES_API_SECRET_KEY,
            'X-AUTH-RVID' => self::CURRENT_RVID,
            'X-AUTH-LOGIN' => Episciences_Auth::getUsername()
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

}

