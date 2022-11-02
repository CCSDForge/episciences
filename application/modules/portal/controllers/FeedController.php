<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Laminas\Feed\Writer\Feed;

class FeedController extends Zend_Controller_Action
{

    /**
     * @throws GuzzleException
     * @throws Zend_Exception
     * @throws JsonException
     */
    public function indexAction(): void
    {

    }

    public function rssAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        header('Content-Type: text/xml');
        $this->getFeedOutput('rss');
    }

    /**
     * @param string $feedType
     * @return void
     * @throws GuzzleException|Zend_Exception
     */
    private function getFeedOutput(string $feedType): void
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $feed = $this->prepareFeedHead($translator, $feedType);

        $solrOutput = $this->getSourceDataForFeed();

        if (empty($solrOutput)) {
            echo $feed->export('rss'); // we prefer the output of an empty feed than an error
            exit;
        }

        foreach ($solrOutput["grouped"]["revue_title_s"]["groups"] as $entry) {

            $journal = $entry["groupValue"];


            foreach ($entry['doclist']["docs"] as $docEntry) {

                $entry = $feed->createEntry();

                if (!empty($docEntry['doi_s'])) {
                    $link = sprintf('https://doi.org/%s', $docEntry['doi_s']);
                } else {
                    $link = $docEntry['es_doc_url_s'];
                }

                $entry->setLink($link);

                $entry->setTitle($docEntry['paper_title_t'][0]);
                $entry->setDescription($docEntry['abstract_t'][0]);

                foreach ($docEntry['author_fullname_s'] as $oneAuthor) {
                    $entry->addAuthor(['name' => $oneAuthor]);
                }

                $entry->addCategory(['term' => $journal]);

                foreach ($docEntry['keyword_t'] as $oneKeyword) {
                    $entry->addCategory(['term' => $oneKeyword]);
                }

                $publicationDate = strtotime($docEntry['publication_date_tdate']);
                $entry->setDateModified($publicationDate);
                $entry->setDateCreated($publicationDate);

                $feed->addEntry($entry);
            }
        }

        echo $feed->export($feedType);
    }

    /**
     * @param $translator
     * @param string $feedType
     * @return Feed
     */
    private function prepareFeedHead($translator, string $feedType): Feed
    {
        $feed = new Feed();

        $feed->setTitle(DOMAIN . ' - ' . $translator->translate("Derniers articles"));
        $feed->setLink(APPLICATION_URL);
        $feed->setFeedLink(APPLICATION_URL . '/feed/' . $feedType, $feedType);
        $feed->setDescription($translator->translate("Derniers articles"));
        $feed->setImage(['uri' => APPLICATION_URL . '/img/episciences_sign_50x50.png', 'title' => DOMAIN, 'link' => APPLICATION_URL]);
        $feed->setGenerator(DOMAIN);

        $feed->addAuthor(['name' => DOMAIN]);
        $feed->setDateModified(time());
        $feed->addHub('http://pubsubhubbub.appspot.com/');
        return $feed;
    }


    /**
     * @return array
     * @throws GuzzleException
     */
    private function getSourceDataForFeed(): array
    {
        $cHeaders = [
            'headers' => ['Content-feedType' => 'application/json']
        ];

        $client = new Client($cHeaders);

        $solrHost = sprintf('%s://%s:%s/solr/%s/select/?', ENDPOINTS_SEARCH_PROTOCOL, ENDPOINTS_SEARCH_HOST, ENDPOINTS_SEARCH_PORT, ENDPOINTS_CORENAME);
        $solrQuery = $solrHost . 'indent=true&q=*:*&group=true&group.field=revue_title_s&group.limit=2&fl=paper_title_t,abstract_t,author_fullname_s,revue_code_t,publication_date_tdate,keyword_t,revue_title_s,doi_s,es_doc_url_s,paperid&sort=publication_date_tdate asc';


        try {
            $response = $client->get($solrQuery);
        } catch (GuzzleException $e) {
            return [];
        }

        try {
            $solrOutput = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $solrOutput = [];
        }

        return $solrOutput;
    }

    public function atomAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        header('Content-Type: text/xml');
        $this->getFeedOutput('atom');
    }

}