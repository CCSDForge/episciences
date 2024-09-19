<?php

class SitemapController extends Episciences_Controller_Action
{

    public function indexAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: text/xml; charset: utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;
        
        $reviews = Episciences_ReviewsManager::getList();
        foreach ($reviews as $review) {
        	if ($review->getRvid() == 0) {
        		continue;
        	}
        	echo "\t" . "<url>" . PHP_EOL;
        	echo "\t\t" . "<loc>".$review->getUrl()."</loc>" . PHP_EOL;				// URL de la revue
        	echo "\t\t" . "<lastmod>".$review->getCreation()."</lastmod>" . PHP_EOL;	// Date de création de la revue
        	echo "\t\t" . "<changefreq>monthly</changefreq>" . PHP_EOL;
        	echo "\t\t" . "<priority>0.8</priority>" . PHP_EOL;
        	echo "\t" . "</url>" . PHP_EOL;
        }
        
        echo '</urlset>'.PHP_EOL;
    }

}

