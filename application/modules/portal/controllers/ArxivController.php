<?php

class ArxivController extends Zend_Controller_Action
{
    private string $locale = 'en';

    public function bibfeedAction(): void
    {
        $this->disableLayoutAndRender();
        $this->setResponseHeader();

        $dom = $this->initializeDomDocument();
        $root = $this->createRootElement($dom);
        $date = $this->createDateElement($dom);
        $root->appendChild($date);

        $settings = $this->getSettings();
        $papers = Episciences_PapersManager::getList($settings);
        $reviews = $this->getReviews($papers);

        foreach ($papers as $paper) {
            $article = $this->createArticleElement($dom, $paper, $reviews);
            $root->appendChild($article);
        }

        $this->outputXml($dom);
    }

    private function disableLayoutAndRender(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
    }

    private function setResponseHeader(): void
    {
        header('Content-Type: text/xml');
    }

    private function initializeDomDocument(): Ccsd_DOMDocument
    {
        $dom = new Ccsd_DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        return $dom;
    }

    private function createRootElement($dom)
    {
        $root = $dom->createElement("preprint");
        $root->setAttribute("xmlns", "http://arxiv.org/doi_feed");
        $root->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $root->setAttribute("identifier", "Episciences.org - arXiv.org DOI feed");
        $root->setAttribute("version", "episciences-arxiv-feed v1.0");
        $root->setAttribute("xsi:schemaLocation", "http://arxiv.org/doi_feed http://arxiv.org/schemas/doi_feed.xsd");
        $dom->appendChild($root);
        return $root;
    }

    private function createDateElement($dom)
    {
        $date = $dom->createElement('date');
        $date->setAttribute('year', date('Y'));
        $date->setAttribute('month', date('m'));
        $date->setAttribute('day', date('d'));
        return $date;
    }

    private function getSettings(): array
    {
        $request = $this->getRequest();
        $settings = [
            'is' => [
                'repoid' => Episciences_Repositories::getRepoIdByLabel('arXiv'),
                'status' => Episciences_Paper::STATUS_PUBLISHED
            ]
        ];

        if ($request->getParam('limit') !== 'all') {
            $settings['limit'] = 1000;
            $settings['offset'] = 1;
        }

        return $settings;
    }

    private function getReviews($papers): array
    {
        $reviews = [];

        foreach ($papers as $paper) {
            $journalId = $paper->getRvid();
            if (!array_key_exists($journalId, $reviews)) {
                if (Episciences_Review::exist($journalId)) {
                    $review = Episciences_ReviewsManager::find($journalId);
                    $reviews[$journalId] = $review;
                }
            }
        }

        return $reviews;
    }

    private function createArticleElement($dom, $paper, $reviews)
    {
        $article = $dom->createElement('article');
        $article->setAttribute('preprint_id', 'arXiv:' . $paper->getIdentifier());

        if ($paper->getDoi() != '') {
            $article->setAttribute('doi', $paper->getDoi());
        }

        $volumeName = Episciences_VolumesManager::translateVolumeKey('volume_' . $paper->getVid() . '_title', $this->locale, false);
        $sectionName = Episciences_SectionsManager::translateSectionKey('section_' . $paper->getSid() . '_title', $this->locale, false);

        $volumeName = $volumeName ? ', ' . $volumeName : '';
        $sectionName = $sectionName ? ', ' . $sectionName : '';

        $publicationDate = $this->view->date($paper->getPublication_date(), $this->locale);
        $journalId = $paper->getRvid();
        $journalCode = $reviews[$journalId]->getCode();
        $journalName = $reviews[$journalId]->getName();

        $ref_biblio = sprintf("%s%s%s (%s) %s:%s", $journalName, $volumeName, $sectionName, $publicationDate, $journalCode, $paper->getPaperid());
        $article->setAttribute('journal_ref', $ref_biblio);

        return $article;
    }

    private function outputXml($dom): void
    {
        $dom->formatOutput = false;
        $dom->normalizeDocument();
        echo $dom->saveXML();
    }
}
