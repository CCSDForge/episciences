<?php

class IndexController extends Episciences_Controller_Action
{

    // Homepage
    public function indexAction(): void
    {
        $reviews = Episciences_ReviewsManager::getList();

        $itemsPerPage =5;
        $page = $this->getRequest()->getParam('page', 1);

        $paginator = Zend_Paginator::factory($reviews);
        $paginator->setItemCountPerPage($itemsPerPage);
        $paginator->setCurrentPageNumber($page);

        $reviewData = [];

        foreach ($paginator as $review) {
            $reviewData[] = [
                'name' => $review->getName(),
                'code' => $review->getCode(),
                'url' => 'https://' . $review->getCode() . '.episciences.org',
                'logo' => 'https://' . $review->getCode() . '.episciences.org'.'/logos/logo-' . $review->getCode() . '-small.svg'
            ];
        }

        $this->view->reviewData = $reviewData;
        $this->view->paginator = $paginator;
    }
}