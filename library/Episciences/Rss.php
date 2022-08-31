<?php
use Laminas\Feed\Writer\Feed;

class Episciences_Rss
{
    protected Episciences_Review $_review;
    protected Feed $_feed;
    private Zend_Translate $_translator;

    /**
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

            $feed = new Feed;
            if ($settings["action"] === 'papers') {
                $feedTitle = $this->_translator->translate("Dernières publications");
            } elseif ($settings["action"] === 'news') {
                $feedTitle = $this->_translator->translate("Dernières actualités");
            } else {
                $feedTitle = '';
            }
            $feed->setTitle(sprintf('%s - %s', $review->getName(), $feedTitle));
            $feed->setLink(APPLICATION_URL);
            $feed->setFeedLink(APPLICATION_URL . '/rss/papers', 'rss');
            $feed->setDescription($this->_translator->translate("Derniers articles"));
            $feed->setImage(['uri' => APPLICATION_URL . '/img/episciences_sign_50x50.png', 'title' => DOMAIN, 'link' => APPLICATION_URL]);
            $feed->setGenerator(DOMAIN);

            $feed->addAuthor([
                'name' => $review->getName()
            ]);
            $feed->setDateModified(time());
            $feed->addHub('http://pubsubhubbub.appspot.com/');

            $this->setFeed($feed);


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

        if (array_key_exists('max', $settings)) {
            $filters['limit'] = $settings['max'];
        } else {
            $filters['limit'] = 20;
        }

        return $review->getPapers($filters);
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
        $max = (array_key_exists('max', $settings)) ? $settings['max'] : 20;
        $newsList = new Episciences_News();
        $feed = $this->getFeed();
        foreach ($newsList->getListNews(false, 0, $max) as $news) {
            $entry = $feed->createEntry();
            $entry->setTitle($this->_translator->translate($news['TITLE']));
            $entry->setLink(APPLICATION_URL . '/news/');
            $pastDateTime = strtotime($news['DATE_POST']);
            $entry->setDateModified($pastDateTime);
            $entry->setDateCreated($pastDateTime);
            $postContent = $this->_translator->translate($news['CONTENT']);
            $entry->setDescription($postContent);
            $entry->setContent($postContent);
            $feed->addEntry($entry);
        }
        echo $feed->export('rss');
    }


}