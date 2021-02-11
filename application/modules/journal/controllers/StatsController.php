<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

class StatsController extends Zend_Controller_Action
{

    /**
     * @throws GuzzleException
     */
    public function indexAction()
    {
        $uri = 'papers/stats/nb-submissions';

        /** @var array $data */
        $submissions = json_decode($this->askApi($uri), true);

        $firstKey = array_key_first($submissions);
        $moreDetails = $submissions[$firstKey]['details']['moreDetails'];
        $yearCategories = array_keys($moreDetails);
        $allSubmissions = $submissions[$firstKey]['value'];

        $series = ['nbSubmissions' => [], 'percentage' => []];

        foreach ($yearCategories as $year) {
            $byYearResponse = json_decode($this->askApi($uri, ['query' => ['submissionDate' => $year]]), true)[$firstKey];
            $series['nbSubmissions'][] = $byYearResponse['value'];
            $series['percentage'][] = $byYearResponse['details']['percentage'];

            // submissions by repository
            $repositories = array_keys($moreDetails[$year]);

            foreach ($repositories as $repoId) {
                $byRepoResponse = json_decode($this->askApi($uri, ['query' => ['submissionDate' => $year, 'repoid' => $repoId]]), true)[$firstKey];
                $series['nbSubmissionsByRepo'][Episciences_Repositories::getLabel($repoId)]['nbSubmissions'][] = $byRepoResponse['value'];
                $series['nbSubmissionsByRepo'][Episciences_Repositories::getLabel($repoId)]['percentage'][] = $byRepoResponse['details']['percentage'];
            }
        }

        $seriesJs = //[
            ['label' => $this->view->translate('Soumissions'), 'data' => $series['nbSubmissions']];
        //,
            //['name' => '% ' . RVCODE , 'data' => $series['percentage']]
        //];


        foreach ($series['nbSubmissionsByRepo'] as $repoLabel => $values) {
            //$seriesJs[] = ['name' => $repoLabel, 'data' => $values['nbSubmissions']];
            //$seriesJs[] = ['name' => '% ' . $repoLabel , 'data' => $values['percentage']];
        }


        $this->view->allSubmissionsJs = $allSubmissions;
        $this->view->yearCategoriesJs = $yearCategories;
        $this->view->seriesJs = $seriesJs;
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

        $url = EPISCIENCES_SELF_API . $uri . '?rvid=' . RVID;

        $defaultOptions = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json'
            ]
        ];

        $options = array_merge($defaultOptions, $options);

        $client = new Client();

        return $client->request('GET', $url, $options)->getBody();
    }

}

