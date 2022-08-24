<?php

class Episciences_Rss
{
    protected Episciences_Review $_review;
    protected array $_data = [];
    private $_translator;

    /**
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     * @throws Zend_Feed_Exception
     */
    public function __construct($settings)
    {
        $this->_translator = Zend_Registry::get('Zend_Translate');

        $review = Episciences_ReviewsManager::find(RVID);

        if ($review) {

            $this->setReview($review);

            $this->setData([
                'title' => $review->getName() . ' - RSS',
                'link' => APPLICATION_URL,
                'charset' => 'utf-8',
                'language' => Zend_Registry::get('Zend_Locale')->toString(),
                'image' => APPLICATION_URL . '/img/episciences_sign_50x50.png',
                'entries' => []]);

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
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     * @throws Zend_Feed_Exception
     */
    public function listPapers($settings): void
    {
        $review = $this->getReview();

        $filters = [
            'is' => ['status' => Episciences_Paper::STATUS_PUBLISHED],
            'order' => 'PUBLICATION_DATE DESC'];

        if (array_key_exists('max', $settings)) {
            $filters['limit'] = $settings['max'];
        } else {
            $filters['limit'] = 20;
        }

        $papers = $review->getPapers($filters);
        $entries = [];

        foreach ($papers as $paper) {
            $entries[] = [
                'title' => $paper->getTitle(),
                'lastUpdate' => strtotime($paper->getPublication_date()),
                'link' => APPLICATION_URL . '/' . $paper->getDocid(),
                'description' => ($paper->getAbstract()) ?: '',
                'content' => $paper->getAbstract()
            ];
        }
        $data = $this->getData();
        $data['description'] = $this->_translator->translate("Derniers articles");
        $data['entries'] = $entries;

        $feed = Zend_Feed::importArray($data, 'rss');
        $feed->send();
    }

    public function getReview(): Episciences_Review
    {
        return $this->_review;
    }

    public function setReview($review): Episciences_Rss
    {
        $this->_review = $review;
        return $this;
    }

    public function getData(): array
    {
        return $this->_data;
    }

    public function setData($data): Episciences_Rss
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * @throws Zend_Feed_Exception
     */
    public function listNews($settings): void
    {
        $max = (array_key_exists('max', $settings)) ? $settings['max'] : 20;
        $entries = [];
        $newsList = new Episciences_News();
        foreach ($newsList->getListNews(false, 0, $max) as $news) {
            $entries[] = [
                'title' => $this->_translator->translate($news['TITLE']),
                'lastUpdate' => strtotime($news['DATE_POST']),
                'link' => APPLICATION_URL . '/news/',
                'description' => $this->_translator->translate($news['CONTENT']),
                'content' => $this->_translator->translate($news['CONTENT']),
            ];
        }

        $data = $this->getData();
        $data['description'] = $this->_translator->translate("Dernières actualités");
        $data['entries'] = $entries;

        $feed = Zend_Feed::importArray($data, 'rss');
        $feed->send();
    }
}