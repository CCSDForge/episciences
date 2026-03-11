<?php

class IndexController extends Episciences_Controller_Action
{

    // Homepage
    public function indexAction(): void
    {
        $settings = [
            'is' => [
                'is_new_front_switched' => 'yes',
                'status' => Episciences_Review::ENABLED
            ],
            'isNot' => [
                'rvid' => 0
            ]
        ];

        $reviews = Episciences_ReviewsManager::getList($settings);

        $itemsPerPage =20;
        $page = $this->getRequest()->getParam('page', 1);

        $paginator = Zend_Paginator::factory($reviews);
        $paginator->setItemCountPerPage($itemsPerPage);
        $paginator->setCurrentPageNumber($page);

        $reviewData = [];
        /** @var Episciences_Review $review */

        foreach ($paginator as $review) {
            $url = $review->getUrl();
            $reviewData[] = [
                'name' => $review->getName(),
                'code' => $review->getCode(),
                'url' => $url,
                'logo' => sprintf('%s./logos/logo-%s.svg', $url, $review->getCode())
            ];
        }

        $this->view->reviewData = $reviewData;
        $this->view->paginator = $paginator;
    }
}