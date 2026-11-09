<?php
use Laminas\Feed\Writer\Feed;

class Episciences_Rss
{
    public const DEFAULT_MAX_ENTRIES = 20;
    public const MAX_ENTRIES = 100;

    protected Episciences_Review $_review;
    protected Feed $_feed;
    private Zend_Translate $_translator;

    /**
     * @param array<mixed> $settings
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     * @throws Zend_Feed_Exception
     */
    public function __construct(array $settings)
    {
        $this->_translator = Zend_Registry::get('Zend_Translate');

        $review = Episciences_ReviewsManager::find(RVID);

        if ($review) {
            $this->setReview($review);

            $this->setFeed($this->createFeed($review->getName()));

            if ($settings["action"] === 'news') {
                $this->listNews($settings);
            } else {
                // The papers feeds are built by the Episciences API (see RssController)
                trigger_error(sprintf('RSS Undefined method %s', $settings["action"]), E_USER_ERROR);
            }

        }
    }

    /**
     * Channel of the news feed
     */
    private function createFeed(string $reviewName): Feed
    {
        $feedTitle = $this->_translator->translate("Dernières actualités");

        $feed = new Feed;
        $feed->setTitle(sprintf('%s - %s', $reviewName, $feedTitle));
        $feed->setLink(APPLICATION_URL);
        $feed->setFeedLink(APPLICATION_URL . '/rss/news', 'rss');
        $feed->setDescription($feedTitle);
        $feed->setImage(['uri' => APPLICATION_URL . '/img/episciences_sign_50x50.png', 'title' => DOMAIN, 'link' => APPLICATION_URL]);
        $feed->setGenerator(DOMAIN);

        $feed->addAuthor([
            'name' => $reviewName
        ]);
        $feed->setDateModified(time());
        $feed->addHub('http://pubsubhubbub.appspot.com/');

        return $feed;
    }

    public function getReview(): Episciences_Review
    {
        return $this->_review;
    }

    public function setReview(Episciences_Review $review): Episciences_Rss
    {
        $this->_review = $review;
        return $this;
    }

    /**
     * Number of entries of a feed: the requested 'max', bounded, or the default one
     * @param array<mixed> $settings
     */
    public static function getMaxEntries(array $settings): int
    {
        return self::parseMaxEntries($settings['max'] ?? null) ?? self::DEFAULT_MAX_ENTRIES;
    }

    /**
     * Requested number of entries: a positive integer, bounded, or null when invalid
     */
    public static function parseMaxEntries(mixed $max): ?int
    {
        $max = filter_var($max, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $max === false ? null : min($max, self::MAX_ENTRIES);
    }

    /**
     * @return Feed
     */
    public function getFeed(): Feed
    {
        return $this->_feed;
    }

    /**
     * @param Feed $feed
     */
    public function setFeed(Feed $feed): void
    {
        $this->_feed = $feed;
    }

    /**
     * @param array<mixed> $settings
     * @throws Zend_Feed_Exception
     */
    public function listNews(array $settings): void
    {
        $newsList = new Episciences_News();
        $feed = $this->getFeed();
        // Only the news put online by the journal
        foreach ($newsList->getListNews(true, 0, self::getMaxEntries($settings)) as $news) {
            $entry = $feed->createEntry();
            $entry->setTitle($this->_translator->translate($news['TITLE']));
            // Anchor of the news on the news page: also the guid of the entry, unique per news
            $entry->setLink(sprintf('%s/news/#news-%d', APPLICATION_URL, $news['NEWSID']));
            $pastDateTime = strtotime($news['DATE_POST']);
            $entry->setDateModified($pastDateTime);
            $entry->setDateCreated($pastDateTime);
            // Plain text, rendered as on the news page
            $postContent = nl2br(htmlspecialchars($this->_translator->translate($news['CONTENT']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $entry->setDescription($postContent);
            $entry->setContent($postContent);
            $feed->addEntry($entry);
        }
        echo $feed->export('rss');
    }


}