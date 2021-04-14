<?php

class SitemapController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: text/xml; charset: utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;
        
        $query  = 'q=*%3A*';
        $query .= '&sort=publication_date_tdate+desc&wt=phps&omitHeader=true';        
        if (RVID && RVID != 0) {
        	$query .= '&fq=revue_id_i:'.RVID;
        }
        $result = Episciences_Tools::solrCurl($query, 'episciences', 'select', true);
        if ($result) {
        	$result = unserialize($result, ['allowed_classes' => false]);
        	foreach ($result['response']['docs'] as $paper) {
        		echo "\t" . "<url>" . PHP_EOL;
        		echo "\t\t" . "<loc>" . APPLICATION_URL.'/'.$paper['docid'] . "</loc>" . PHP_EOL; 		// URL de l'article
        		echo "\t\t" . "<lastmod>" . $paper['publication_date_tdate'] . "</lastmod>" . PHP_EOL;	// Date de publication de l'article
        		echo "\t\t" . "<changefreq>daily</changefreq>" . PHP_EOL;
        		echo "\t\t" . "<priority>1</priority>" . PHP_EOL;
        		echo "\t" . "</url>" . PHP_EOL;
        	}
        }
        echo '</urlset>'.PHP_EOL;
    }

}

