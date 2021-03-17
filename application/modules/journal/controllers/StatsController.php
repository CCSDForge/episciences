<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

class StatsController extends Zend_Controller_Action
{
    const COLORS_CODE = ["#8e5ea2", "#3e95cd", "#dd2222", "#c45850", "#3cba9f", "#e8c3b9"];
    const CHART_TYPE = ['BAR' => 'bar', 'PIE' => 'pie', 'BAR_H' => 'barH', 'DOUGHNUT' => 'doughnut', 'LINE' => 'line'];


    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $yearQuery = (!empty($request->getParam('year'))) ? (int)$request->getParam('year') : null;

        $uri = 'review/stats/dashboard';
        $errorMessage = "Une erreur s'est produite lors de la récupération des statistiques. Nous vous suggérons de ré-essayer dans quelques instants. Si le problème persiste vous devriez contacter le support de la revue.";

        try {
            $result = json_decode($this->askApi($uri), true);
        } catch (GuzzleException $e) {
            $this->view->errorMessage = $errorMessage;
            return;
        }

        if (empty($result)) {
            $this->view->errorMessage = 'Aucun résultat';
            return;
        }

        $dashboard = $result[array_key_first($result)];

        $allSubmissions = $dashboard['submissions']['value']; // all review submissions
        $moreDetails = $dashboard['submissions']['details']['moreDetails'];

        $yearCategories = array_filter(array_keys($moreDetails), static function ($value) {
            return !empty($value);
        });

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
        $allPublications = $allRefusals = $allAcceptations = 0;
        $publicationsPercentage = $acceptationsPercentage = $refusalsPercentage = null;


        if ($yearQuery) { // for stats by year
            $yearCategories = [$yearQuery];
        }

        $submissionsDelay = $dashboard['submissionsDelay'];
        $publicationsDelay = $dashboard['publicationsDelay'];

        foreach ($yearCategories as $year) {

            $totalByYear = $dashboard['submissions']['details']['submissionsByYear'][$year]['submissions'];

            $nbPublications = $nbRefusals = $nbAcceptation = 0;

            $submissionsByYearResponse = array_key_exists($year, $moreDetails) ? $moreDetails[$year] : [];

            foreach ($submissionsByYearResponse as $repoId => $values) { // submissions by repository

                $totalByRepo = 0;

                foreach ($values as $status => $nbSubmissions) {

                    $totalByRepo += $nbSubmissions['nbSubmissions'];

                    if ($status === Episciences_Paper::STATUS_PUBLISHED) {
                        $allPublications += $nbSubmissions['nbSubmissions'];
                        $nbPublications += $nbSubmissions['nbSubmissions'];
                    }

                    if ($status === Episciences_Paper::STATUS_REFUSED) {
                        $allRefusals += $nbSubmissions['nbSubmissions'];
                        $nbRefusals += $nbSubmissions['nbSubmissions'];
                    }

                    if (in_array($status, Episciences_Paper::$_canBeAssignedDOI, true)) {
                        $allAcceptations += $nbSubmissions['nbSubmissions'];
                        $nbAcceptation += $nbSubmissions['nbSubmissions'];
                    }

                    unset($status, $nbSubmissions);
                }

                $series['submissionsByRepo'][$repoId]['nbSubmissions'][] = $totalByRepo;

                unset($totalByRepo);

            }

            $series['submissionsByYear']['submissions'][] = $totalByYear;
            $series['acceptationByYear']['acceptations'][] = $nbAcceptation;
            $series['refusalsByYear']['refusals'][] = $nbRefusals;
            $series['publicationsByYear']['publications'][] = $nbPublications;

            if ($totalByYear) {
                $series['acceptationByYear']['percentage'][] = round($nbAcceptation / $totalByYear * 100, 2);
                $series['refusalsByYear']['percentage'][] = round($nbRefusals / $totalByYear * 100, 2);
                $series['publicationsByYear']['percentage'][] = round($nbPublications / $totalByYear * 100, 2);
            }

            if (!empty($submissionsDelay['details']) && array_key_exists($year, $submissionsDelay['details'][RVID])) {
                $series['delayBetweenSubmissionAndAcceptance'][] = $submissionsDelay['details'][RVID][$year]['delay'];
            }

            if (!empty($publicationsDelay['details']) && array_key_exists($year, $publicationsDelay['details'][RVID])) {
                $series['delayBetweenSubmissionAndPublication'][] = $publicationsDelay['details'][RVID][$year]['delay'];
            }
        }

        unset($totalByYear, $nbPublications, $nbRefusals, $nbPublications);

        if ($yearQuery) {

            $allSubmissions = $series['submissionsByYear']['submissions'][0];
            $allPublications = $series['publicationsByYear']['publications'][0];
            $allRefusals = $series['refusalsByYear']['refusals'][0];
            $allAcceptations = $series['acceptationByYear']['acceptations'][0];

            $publicationsPercentage = $series['publicationsByYear']['percentage'][0];
            $refusalsPercentage = $series['refusalsByYear']['percentage'][0];
            $acceptationsPercentage = $series['acceptationByYear']['percentage'][0];

        } elseif ($allSubmissions) {
            $publicationsPercentage = round($allPublications / $allSubmissions * 100, 2);
            $refusalsPercentage = round($allRefusals / $allSubmissions * 100, 2);
            $acceptationsPercentage = round($allAcceptations / $allSubmissions * 100, 2);

        }

        $label1 = ucfirst($this->view->translate('soumissions'));
        $label2 = ucfirst($this->view->translate('articles publiés'));
        $label3 = ucfirst($this->view->translate('articles refusés'));
        $label4 = ucfirst($this->view->translate('articles acceptés'));

        // figure 1
        $this->view->chart1Title = $this->view->translate("Soumissions");

        $seriesJs['allSubmissionsPercentage']['datasets'][] = [
            'data' => [$publicationsPercentage, $acceptationsPercentage, $refusalsPercentage],
            'backgroundColor' => [self::COLORS_CODE[4], self::COLORS_CODE[5], self::COLORS_CODE[2]]
        ];

        $seriesJs['allSubmissionsPercentage']['labels'] = [$label2, $label4, $label3];
        $seriesJs['allSubmissionsPercentage']['chartType'] = self::CHART_TYPE['PIE'];

        //figure 2
        $this->view->chart2Title = !$yearQuery ?
            $this->view->translate("Par <code>année</code>, la répartition des <code>soumissions</code>, <code>articles publiés</code> et <code>articles refusés</code>") :
            $this->view->translate("La répartition des <code>soumissions</code>, <code>articles publiés</code> et <code>articles refusés</code>");

        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label1, 'data' => $series['submissionsByYear']['submissions'], 'backgroundColor' => self::COLORS_CODE[1]];
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label2, 'data' => $series['publicationsByYear']['publications'], 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label3, 'data' => $series['refusalsByYear']['refusals'], 'backgroundColor' => self::COLORS_CODE[2]];
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

        //Users stats
        $nbUsersByRole = [];
        $rolesJs = [];

        if ($yearQuery || !array_key_exists('users', $dashboard)) {
            $uUri = 'users/stats/nb-users';
            try {
                $usersStats = json_decode($this->askApi($uUri, ['registrationDate' => $yearQuery], 'users'), true);

            } catch (GuzzleException $e) {
                $usersStats = [];
            }

            $usersDetails = $usersStats[0]['details'];

        } else {

            $usersStats = $dashboard['users'];
            $usersDetails = $usersStats['details'];
            $roles = array_keys($usersDetails);
            $rootKey = array_search(Episciences_Acl::ROLE_ROOT, $roles, true);
            unset($roles[$rootKey]);

        }

        $roles = array_keys($usersDetails);
        $rootKey = array_search(Episciences_Acl::ROLE_ROOT, $roles, true);
        unset($roles[$rootKey]);

        //figure 5
        $this->view->chart5Title = $this->view->translate("Le nombre d'utilisateurs par <code>rôles</code>");

        $data = [];

        foreach ($roles as $key => $role) {
            $rolesJs[] = $this->view->translate($role);
            $data[] = $usersDetails[$role]['nbUsers'];
        }

        $nbUsersByRole['datasets'][] = ['label' => $this->view->translate("Nombre d'utilisateurs"), 'data' => $data, 'backgroundColor' => self::COLORS_CODE[4]];
        $nbUsersByRole['chartType'] = self::CHART_TYPE['BAR'];
        $this->view->allUsers = !$yearQuery ? $usersStats['value'] : $usersStats[0]['value'];

        $this->view->allSubmissionsJs = $allSubmissions;
        $this->view->allPublications = $allPublications;
        $this->view->allRefusals = $allRefusals;
        $this->view->allAcceptations = $allAcceptations;

        $this->view->publicationsPercentage = $publicationsPercentage;
        $this->view->refusalsPercentage = $refusalsPercentage;
        $this->view->acceptationsPercentage = $acceptationsPercentage;

        $this->view->yearCategoriesJs = $yearCategories;
        $this->view->seriesJs = $seriesJs;
        $this->view->yearQuery = $yearQuery;
        $this->view->errorMessage = null;
        $this->view->roles = $rolesJs;
        $this->view->nbUsersByRole = $nbUsersByRole;
    }

    /**
     * @param $uri
     * @param array $options
     * @param string|null $resource
     * @return StreamInterface
     * @throws GuzzleException
     */
    private function askApi($uri, array $options = [], string $resource = null): StreamInterface
    {
        $url = EPISCIENCES_API_URL . $uri;

        $url = $resource !== 'users' ? $url . '?rvid=' . RVID : $url . '?roles.rvid=' . RVID;

        $defaultOptions = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json',
                'X-AUTH-TOKEN' => EPISCIENCES_API_SECRET_KEY,
                'X-AUTH-RVID' => RVID,
                'X-AUTH-UID' => Episciences_Auth::getUid()
            ]
        ];

        $options = array_merge($defaultOptions, $options);

        $client = new Client();

        try {
            return $client->request('GET', $url, $options)->getBody();
        } catch (GuzzleException $e) {
            error_log('SATATISTIC_MODULE: ' . $e->getMessage());
            throw $e;
        }
    }

}

