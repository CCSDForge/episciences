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

        foreach ($paginator as $review) {
            $reviewData[] = [
                'name' => $review->getName(),
                'code' => $review->getCode(),
                'url' => 'https://' . $review->getCode() . '.' . DOMAIN,
                'logo' => 'https://' . $review->getCode() . '.' . DOMAIN.'/logos/logo-' . $review->getCode() . '-small.svg'
            ];
        }

        $this->view->reviewData = $reviewData;
        $this->view->paginator = $paginator;
    }
}