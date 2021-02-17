<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

class StatsController extends Zend_Controller_Action
{
    const COLORS_CODE = ["#8e5ea2", "#3e95cd", "#dd2222", "#c45850", "#3cba9f", "#e8c3b9"];
    const CHART_TYPE = ['BAR' => 'bar', 'PIE' => 'pie', 'BAR_H' => 'barH'];

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $yearQuery = (!empty($request->getParam('year'))) ? (int)$request->getParam('year') : null;

        $uri = 'papers/stats/nb-submissions';
        $delayBetweenSubmissionAndAcceptanceUri = 'papers/stats/delay-between-submit-and-acceptance';
        $delayBetweenSubmissionAndPublicationUri = 'papers/stats/delay-between-submit-and-publication';
        $errorMessage = "Une erreur s'est produite lors de la récupération des statistiques. Nous vous suggérons de ré-essayer dans quelques instants. Si le problème persiste vous devriez contacter le support de la revue.";

        /** @var array $data */
        try {
            $response = $this->askApi($uri)->getContents();
            $submissions = json_decode($this->askApi($uri), true);
        } catch (GuzzleException $e) {
            $this->view->errorMessage = $errorMessage;
            return;
        }

        if (!$submissions) {
            $this->view->errorMessage = $errorMessage;
            return;
        }

        $firstKey = array_key_first($submissions);

        $moreDetails = $submissions[$firstKey]['details']['moreDetails'];

        $yearCategories = array_keys($moreDetails);
        $this->view->yearCategories = $yearCategories;

        $query = ($yearQuery && in_array($yearQuery, $yearCategories, true)) ? ['submissionDate' => $yearQuery] : [];

        $allSubmissions = $submissions[$firstKey]['value']; // submissions by review


        $series = [];

        if ($yearQuery) {
            $yearCategories = [$yearQuery];
        }

        try {
            $publicationsResult = json_decode($this->askApi($uri, ['query' => array_merge(['status' => Episciences_Paper::STATUS_PUBLISHED], $query)]), true)[$firstKey];
            $refusalsResult = json_decode($this->askApi($uri, ['query' => array_merge(['status' => Episciences_Paper::STATUS_REFUSED], $query)]), true)[$firstKey];
            $acceptationsResult = json_decode($this->askApi($uri, ['query' => array_merge(['status' => Episciences_Paper::STATUS_ACCEPTED], $query)]), true)[$firstKey];

        } catch (GuzzleException $e) {
            $this->view->errorMessage = $errorMessage;
            return;
        }

        $allPublications = $publicationsResult['value'];
        $publicationsPercentage = $publicationsResult['details']['percentage'];

        $allRefusals = $refusalsResult['value'];
        $refusalsPercentage = $refusalsResult['details']['percentage'];

        $allAcceptations = $acceptationsResult['value'];
        $acceptationsPercentage = $acceptationsResult['details']['percentage'];

        $label1 = ucfirst($this->view->translate('soumissions'));
        $label2 = ucfirst($this->view->translate('articles publiés'));
        $label3 = ucfirst($this->view->translate('articles refusés'));
        $label4 = ucfirst($this->view->translate('articles acceptés'));

        // figure 1
        $seriesJs['allSubmissionsPercentage']['datasets'][] = ['data' => [$publicationsPercentage, $acceptationsPercentage, $refusalsPercentage], 'backgroundColor' => [self::COLORS_CODE[4], self::COLORS_CODE[5], self::COLORS_CODE[2]]];
        $seriesJs['allSubmissionsPercentage']['labels'] = [$label2, $label4, $label3];
        $seriesJs['allSubmissionsPercentage']['chartType'] = self::CHART_TYPE['PIE'];
        $seriesJs['allSubmissionsPercentage']['title'] = $this->view->translate("Soumissions en %");


        foreach ($yearCategories as $year) {
            try {
                $submissionsByYearResponse = json_decode($this->askApi($uri, ['query' => ['submissionDate' => $year]]), true)[$firstKey];
                $publishedSubmissions = json_decode($this->askApi($uri, ['query' => ['submissionDate' => $year, 'status' => Episciences_paper::STATUS_PUBLISHED]]), true)[$firstKey];
                $refusedSubmissions = json_decode($this->askApi($uri, ['query' => ['submissionDate' => $year, 'status' => Episciences_paper::STATUS_REFUSED]]), true)[$firstKey];
                $acceptedSubmissions = json_decode($this->askApi($uri, ['query' => ['submissionDate' => $year, 'status' => Episciences_paper::STATUS_ACCEPTED]]), true)[$firstKey];
                $delayBetweenSubmissionAndAcceptance = json_decode($this->askApi($delayBetweenSubmissionAndAcceptanceUri, ['query' => ['submissionDate' => $year]]), true)[$firstKey];
                $delayBetweenSubmissionAndPublication = json_decode($this->askApi($delayBetweenSubmissionAndPublicationUri, ['query' => ['submissionDate' => $year]]), true)[$firstKey];
            } catch (GuzzleException $e) {
                $this->view->errorMessage = $errorMessage;
                return;
            }

            $series['submissionsByYear'][] = $submissionsByYearResponse['value'];
            $series['percentageByYear'][] = $submissionsByYearResponse['details']['percentage'];

            $series['publishedSubmissions'][] = $publishedSubmissions['value'];
            $series['publishedPercentage'][] = $publishedSubmissions['details']['percentage'];

            $series['refusedSubmissions'][] = $refusedSubmissions['value'];
            $series['refusedPercentage'][] = $refusedSubmissions['details']['percentage'];

            $series['acceptedSubmissions'][] = $acceptedSubmissions['value'];
            $series['acceptedPercentage'][] = $acceptedSubmissions['details']['percentage'];
            $series['delayBetweenSubmissionAndAcceptance'][] = $delayBetweenSubmissionAndAcceptance['value'];
            $series['delayBetweenSubmissionAndPublication'][] = $delayBetweenSubmissionAndPublication['value'];

            // submissions by repository
            $repositories = array_keys($moreDetails[$year]);

            foreach ($repositories as $repoId) {
                try {
                    $byRepoResponse = json_decode($this->askApi($uri, ['query' => ['submissionDate' => $year, 'repoid' => $repoId]]), true)[$firstKey];
                } catch (GuzzleException $e) {
                    $this->view->errorMessage = $errorMessage;
                    return;
                }
                $series['submissionsByRepo'][$repoId]['nbSubmissions'][] = $byRepoResponse['value'];
                $series['submissionsByRepo'][$repoId]['percentage'][] = $byRepoResponse['details']['percentage'];
            }

        }

        //figure 2
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label1, 'data' => $series['submissionsByYear'], 'backgroundColor' => self::COLORS_CODE[1]];
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label2, 'data' => $series['publishedSubmissions'], 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs['submissionsByYear']['datasets'][] = ['label' => $label3, 'data' => $series['refusedSubmissions'], 'backgroundColor' => self::COLORS_CODE[2]];
        $seriesJs['submissionsByYear']['chartType'] = self::CHART_TYPE['BAR'];
        $seriesJs['submissionsByYear']['title'] = !$yearQuery ?
            $this->view->translate("Par année, la répartition des soumissions, articles publiés et articles refusés") :
            $this->view->translate("La répartition des soumissions, articles publiés et articles refusés");

        foreach ($series['submissionsByRepo'] as $repoId => $values) {
            $backgroundColor = ($repoId > count(self::COLORS_CODE) - 1) ? self::COLORS_CODE[array_rand(self::COLORS_CODE)] : self::COLORS_CODE[$repoId];
            $repoLabel = Episciences_Repositories::getLabel($repoId);

            //figure3
            $seriesJs['submissionsByRepo']['repositories']['datasets'][] = ['label' => $repoLabel, 'data' => $values['nbSubmissions'], 'backgroundColor' => $backgroundColor];
            $seriesJs['submissionsByRepo']['percentage']['datasets'][] = ['label' => '% ' . $repoLabel, 'data' => $values['percentage']];
        }

        $seriesJs['submissionsByRepo']['repositories']['chartType'] = self::CHART_TYPE['BAR'];
        $seriesJs['submissionsByRepo']['repositories']['title'] = !$yearQuery ?
            $this->view->translate("Répartition des soumissions par année et par archive") :
            $this->view->translate("Répartition des soumissions par archive");
        $seriesJs['submissionsByRepo']['percentage']['chartType'] = self::CHART_TYPE['PIE'];
        $seriesJs['submissionsByRepo']['percentage']['title'] = '';


        // figure4
        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Acceptation'), 'data' => $series['delayBetweenSubmissionAndAcceptance'], 'backgroundColor' => self::COLORS_CODE[5]];
        $seriesJs['submissionDelay']['datasets'][] = ['label' => $this->view->translate('Dépôt-Publication'), 'data' => $series['delayBetweenSubmissionAndPublication'], 'backgroundColor' => self::COLORS_CODE[4]];
        $seriesJs['submissionDelay']['chartType'] = self::CHART_TYPE['BAR_H'];
        $seriesJs['submissionDelay']['title'] = $this->view->translate('Délai moyen en jours entre "dépôt-acceptation" et "dépôt-publication"');


        $this->view->allSubmissionsJs = !$yearQuery ? $allSubmissions : $series['submissionsByYear'][0];
        $this->view->allPublications = $allPublications;
        $this->view->publicationsPercentage = $publicationsPercentage;
        $this->view->allRefusals = $allRefusals;
        $this->view->refusalsPercentage = $refusalsPercentage;
        $this->view->allAcceptations = $allAcceptations;
        $this->view->acceptationsPercentage = $acceptationsPercentage;
        $this->view->yearCategoriesJs = $yearCategories;
        $this->view->seriesJs = $seriesJs;
        $this->view->yearQuery = $yearQuery;
        $this->view->errorMessage = null;
    }

    /**
     * @throws GuzzleException
     */
    public function ajaxsubmissionsAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!Episciences_Auth::isLogged() || !Episciences_Auth::isSecretary() || !Episciences_Auth::isRoot()) {
            return;
        }

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $url = EPISCIENCES_SELF_API . 'papers/stats/nb-submissions';

        if (!Episciences_Auth::isRoot()) {
            $url .= '?rvid=' . RVID;
        }

        echo $this->askApi($url);
    }

    public function submissionsAction()
    {


    }

    /**
     * @param $uri
     * @param array $options
     * @return StreamInterface
     * @throws GuzzleException
     */
    private function askApi($uri, array $options = []): StreamInterface
    {

        $url = EPISCIENCES_API_URL . $uri . '?rvid=' . RVID;

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

