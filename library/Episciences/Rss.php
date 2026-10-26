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

            $this->setFeed($this->createFeed($review->getName(), (string)$settings["action"]));

            if ($settings["action"] === 'papers') {
                $this->listPapers($settings);
            } elseif ($settings["action"] === 'news') {
                $this->listNews($settings);
            } else {
                trigger_error(sprintf('RSS Undefined method %s', $settings["action"]), E_USER_ERROR);
            }

        }
    }

    /**
     * @param array<mixed> $settings
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     * @throws Zend_Feed_Exception
     */
    public function listPapers(array $settings): void
    {
        $review = $this->getReview();

        $papers = $this->getEpisciences_PapersForRss($settings, $review);
        $feed = $this->getFeed();


        foreach ($papers as $paper) {
            if ($paper->hasDoi()) {
                $link = sprintf('https://doi.org/%s', $paper->getDoi());
            } else {
                $link = APPLICATION_URL . '/' . $paper->getDocid();
            }

            $authors = $paper->getMetadata('authors');
            $abtract = ($paper->getAbstract()) ?: '[...]';
            $entry = $feed->createEntry();
            $entry->setTitle($paper->getTitle());
            $entry->setLink($link);
            if (!empty($authors)) {
                foreach ($authors as $oneAuthor) {
                    $entry->addAuthor([
                        'name' => $oneAuthor
                    ]);
                }
            }
            $publicationDate = strtotime($paper->getPublication_date());
            $entry->setDateModified($publicationDate);
            $entry->setDateCreated($publicationDate);
            $entry->setDescription($abtract);
            $entry->setContent($abtract);
            $feed->addEntry($entry);
        }


        echo $feed->export('rss');
    }

    /**
     * Channel of the feed of an action ('papers' or 'news')
     */
    private function createFeed(string $reviewName, string $action): Feed
    {
        if ($action === 'news') {
            $feedTitle = $this->_translator->translate("Dernières actualités");
            $feedDescription = $feedTitle;
        } else {
            $feedTitle = $action === 'papers' ? $this->_translator->translate("Dernières publications") : '';
            $feedDescription = $this->_translator->translate("Derniers articles");
            $action = 'papers';
        }

        $feed = new Feed;
        $feed->setTitle(sprintf('%s - %s', $reviewName, $feedTitle));
        $feed->setLink(APPLICATION_URL);
        $feed->setFeedLink(APPLICATION_URL . '/rss/' . $action, 'rss');
        $feed->setDescription($feedDescription);
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
     * @param array<mixed> $settings
     * @param Episciences_Review $review
     * @return Episciences_Paper[]
     * @throws Zend_Db_Select_Exception
     */
    private function getEpisciences_PapersForRss(array $settings, Episciences_Review $review): array
    {
        $filters = [
            'is' => ['status' => Episciences_Paper::STATUS_PUBLISHED],
            'order' => 'PUBLICATION_DATE DESC'];

        $filters['limit'] = self::getMaxEntries($settings);

        return $review->getPapers($filters);
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