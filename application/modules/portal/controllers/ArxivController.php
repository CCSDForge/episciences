<?php

class ArxivController extends Zend_Controller_Action
{

    public function bibfeedAction()
    {
        $locale = 'en';

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        header('Content-Type: text/xml');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $dom = new Ccsd_DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;

        $root = $dom->createElement("preprint");
        $root->setAttribute("xmlns", "http://arxiv.org/doi_feed");
        $root->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $root->setAttribute("identifier", "Episciences.org - arXiv.org DOI feed");
        $root->setAttribute("version", "episciences-arxiv-feed v1.0");
        $root->setAttribute("xsi:schemaLocation", "http://arxiv.org/doi_feed http://arxiv.org/schemas/doi_feed.xsd");

        $date = $dom->createElement('date');
        $date->setAttribute('year', date('Y'));
        $date->setAttribute('month', date('m'));
        $date->setAttribute('day', date('d'));

        $dom->appendChild($root);
        $root->appendChild($date);

        // Récupérer tous les articles publiés dont l'archive est arXiv
        $repoId = Episciences_Repositories::getRepoIdByLabel('arXiv');
        $translator = Zend_Registry::get('Zend_Translate');


        $settings = ['is' => [
            'repoid' => $repoId,
            'status' => Episciences_Paper::STATUS_PUBLISHED]];

        if ($request->getParam('limit') !== 'all') {
            $settings['limit'] = 1;
            $settings['offset'] = 1000;
        }

        $papers = Episciences_PapersManager::getList($settings);

        $reviews = [];

        foreach ($papers as $paper) {
            /** @var $paper Episciences_Paper */

            // Récupération des infos de la revue
            if (!array_key_exists($paper->getRvid(), $reviews)) {
                if (Episciences_Review::exist($paper->getRvid())) {
                    $review = Episciences_ReviewsManager::find($paper->getRvid());
                    $reviews[$paper->getRvid()] = $review;
                    $translator->addTranslation(APPLICATION_PATH . '/../data/' . $review->getCode() . '/languages/');
                } else {
                    continue;
                }
            }

            $article = $dom->createElement('article');
            $article->setAttribute('preprint_id', 'arXiv:' . $paper->getIdentifier());

            if ($paper->getDoi()!='') {
                $article->setAttribute('doi', $paper->getDoi());
            }

            $ref_biblio = $reviews[$paper->getRvid()]->getName();
            $ref_biblio .= ($translator->isTranslated('volume_' . $paper->getVid() . '_title', false, $locale)) ? ', ' . $translator->translate('volume_' . $paper->getVid() . '_title', $locale) : '';
            $ref_biblio .= ($translator->isTranslated('section_' . $paper->getSid() . '_title', false, $locale)) ? ', ' . $translator->translate('section_' . $paper->getSid() . '_title', $locale) : '';
            $ref_biblio .= ' (' . $this->view->date($paper->getPublication_date(), $locale) . ') ';
            $ref_biblio .= $reviews[$paper->getRvid()]->getCode() . ':' . $paper->getPaperid();
            $article->setAttribute('journal_ref', $ref_biblio);
            $root->appendChild($article);
        }

        // Conserve l'indentation
        $dom->formatOutput = true;
        $dom->normalizeDocument();

        echo $dom->saveXML($dom); // Affichage du XML

    }

}