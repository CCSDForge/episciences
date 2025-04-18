<?php

class IndexController extends Episciences_Controller_Action
{

    // Homepage
    public function indexAction(): void
    {
        $reviews = Episciences_ReviewsManager::getList();

        $reviews = $this->sortReviewsByName($reviews);

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
                'url' => 'https://' . $review->getCode() . '.episciences.org',
                'logo' => 'https://' . $review->getCode() . '.episciences.org'.'/logos/logo-' . $review->getCode() . '-small.svg'
            ];
        }

        $this->view->reviewData = $reviewData;
        $this->view->paginator = $paginator;
    }

    /**
     * Sort reviews alphabetically by name
     *
     * @param mixed $reviews List of review objects
     * @return array Sorted list of reviews
     */
    private function sortReviewsByName($reviews)
    {
        // Check the type of $reviews and act accordingly
        if (is_object($reviews) && method_exists($reviews, 'toArray')) {
            $reviewsArray = $reviews->toArray();
        } else {
            $reviewsArray = $reviews;
        }

        // Sort alphabetically by name
        usort($reviewsArray, function($a, $b) {
            $nameA = is_object($a) ? $a->getName() : $a['name'];
            $nameB = is_object($b) ? $b->getName() : $b['name'];
            return strcasecmp($nameA, $nameB);
        });

        return $reviewsArray;
    }
}