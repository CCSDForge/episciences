<?php

class SitemapController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $sitemapEntries = $this->getSitemapEntries();

        header('Content-Type: text/xml; charset: utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        echo $sitemapEntries;
        echo '</urlset>' . PHP_EOL;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getSitemapEntries(): string
    {
        $sitemapEntries = '';
        $query = 'q=*%3A*';
        $query .= '&sort=publication_date_tdate+desc&wt=phps&omitHeader=true&rows=50000&fl=docid,publication_date_tdate';

        if (RVID && RVID != 0) {
            $query .= '&fq=revue_id_i:' . RVID;
        }

        $result = Episciences_Tools::solrCurl($query);
        if ($result) {
            $result = unserialize($result, ['allowed_classes' => false]);
            if (!empty($result['response']['docs'])) {
                foreach ($result['response']['docs'] as $paper) {
                    $sitemapEntries .= $this->createOneSitemapEntry($paper);
                }
            }
        }
        return $sitemapEntries;
    }

    /**
     * @param $paper
     * @return string
     */
    private function createOneSitemapEntry($paper): string
    {
        $entry = "<url>";
        $entry .= "<loc>" . APPLICATION_URL . '/' . $paper['docid'] . "</loc>";
        $entry .= "</url>" . PHP_EOL;
        return $entry;
    }

}

