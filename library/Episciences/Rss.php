<?php

class Episciences_Rss
{
	protected $_review;
	protected $_data = array();
	
	private $_translator;
	
	public function __construct($settings) 
	{
		$this->_translator = Zend_Registry::get('Zend_Translate');
		
		$review = Episciences_ReviewsManager::find(RVID);
		$this->setReview($review);
		
		$this->setData(array(
				'title' => $review->getName() . ' - ' . $this->_translator->translate ('RSS'),
				'link' => APPLICATION_URL,
				'charset' => 'utf-8' ,
				'language' => Zend_Registry::get('Zend_Locale')->toString(),
				'image' => APPLICATION_URL . '/img/episciences_sign_50x50.png' ,
				'entries' => array()));
		
		$method = 'list' . ucfirst(strtolower($settings["action"]));
		if (method_exists($this, $method)) {
			$this->{$method}($settings);
		}
	}
	
	public function getData()
	{
		return $this->_data;
	}
	
	public function getReview()
	{
		return $this->_review;
	}
	
	public function setData($data) 
	{
		$this->_data = $data;
		return $this;
	}
	public function setReview($review)
	{
		$this->_review = $review;
		return $this;
	}
	
	public function listPapers($settings)
	{
		$review = $this->getReview();
		
		$filters = array(
				'is' 	=>	array('status'=> Episciences_Paper::STATUS_PUBLISHED),
				'order'	=>	'PUBLICATION_DATE DESC');
		
		if (array_key_exists('max', $settings)) {
			$filters['limit'] = $settings['max'];
		} else {
			$filters['limit'] = 20;
		}
		
		$papers = $review->getPapers($filters);
		$entries = array();
		
		foreach ($papers as $paper) {
			$entries[] = array(
					'title'			=>	$paper->getTitle(),
					'lastUpdate'	=>	strtotime($paper->getPublication_date()),
					'link'			=>	APPLICATION_URL . '/' . $paper->getDocid(),
					'description' 	=>	($paper->getAbstract()) ? $paper->getAbstract() : '',
					'content'		=>	$paper->getAbstract()
			);
		}
		$data = $this->getData();
		$data['description'] = $this->_translator->translate("Derniers articles");
		$data['entries'] = $entries;
		
		$feed = Zend_Feed::importArray($data, 'rss');
		$feed->send();
	}
	
	public function listNews($settings) 
	{
		$max = (array_key_exists('max', $settings)) ? $settings['max'] : 20;
		
		$newsList = new Episciences_News();
		foreach ($newsList->getListNews(false, 0, $max) as $news) {
			$entries[] = array(
					'title'			=>	$this->_translator->translate($news['TITLE']),
					'lastUpdate'	=>	strtotime($news['DATE_POST']),
					'link'			=>	APPLICATION_URL . '/news/',
					'description' 	=>	$this->_translator->translate($news['CONTENT']),
					'content'		=>	$this->_translator->translate($news['CONTENT']),
			);
		}
		
		$data = $this->getData();
		$data['description'] = $this->_translator->translate("Dernières actualités");
		$data['entries'] = $entries;
		
		$feed = Zend_Feed::importArray($data, 'rss');
		$feed->send();
	}
}