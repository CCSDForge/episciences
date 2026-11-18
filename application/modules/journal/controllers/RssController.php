<?php

use Episciences\Api\FeedApiClient;
use Episciences\AppRegistry;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class RssController extends Zend_Controller_Action
{
    private const FEED_CONTENT_TYPE = 'text/xml; charset=UTF-8';
    // Delay before asking again for a feed the Episciences API could not provide (seconds)
    public const RETRY_AFTER = 600;

    public function init(): void
    {
        if (in_array($this->getRequest()->getActionName(), ['papers', 'atom', 'news'], true)) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
        }
    }

    public function indexAction(): void
    {
        // view here
    }

    /**
     * RSS feed of the published papers, built by the Episciences API
     */
    public function papersAction(): void
    {
        $this->renderApiFeed(FeedApiClient::FORMAT_RSS);
    }

    /**
     * Atom feed of the published papers, built by the Episciences API
     */
    public function atomAction(): void
    {
        $this->renderApiFeed(FeedApiClient::FORMAT_ATOM);
    }

    /**
     * RSS feed of the news of the journal (the Episciences API has no news feed)
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     * @throws Zend_Feed_Exception
     */
    public function newsAction(): void
    {
        $params = $this->getRequest()->getParams();
        // An invalid 'max' falls back to the journal setting
        if (Episciences_Rss::parseMaxEntries($params['max'] ?? null) === null) {
            $page = new Episciences_Website_Navigation_Page_Rss;
            $page->load();
            $params['max'] = $page->getNbResults();
        }
        $this->getResponse()->setHeader('Content-Type', self::FEED_CONTENT_TYPE, true);
        new Episciences_Rss($params);
    }

    protected function getFeedApiClient(): FeedApiClient
    {
        return new FeedApiClient(
            new Client(),
            new FilesystemAdapter('journalFeeds', FeedApiClient::CACHE_TTL, dirname(APPLICATION_PATH) . '/cache/'),
            AppRegistry::getMonoLogger() ?? new NullLogger(),
            (string)EPISCIENCES_API_URL
        );
    }

    private function renderApiFeed(string $format): void
    {
        $response = $this->getResponse();
        $feed = $this->getFeedApiClient()->fetch($format, (string)RVCODE);

        if ($feed === null) {
            $response->setHttpResponseCode(503)
                ->setHeader('Retry-After', (string)self::RETRY_AFTER, true)
                ->setHeader('Content-Type', 'text/plain; charset=UTF-8', true)
                ->setBody('Feed temporarily unavailable');
            return;
        }

        $response->setHeader('Content-Type', self::FEED_CONTENT_TYPE, true)->setBody($feed);
    }
}