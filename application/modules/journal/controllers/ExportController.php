<?php

/**
 * Class ExportController
 * Export formats of a paper
 */

class ExportController extends Zend_Controller_Action
{

    /**
     * exporte en format BIBTEX
     * @throws Zend_Db_Statement_Exception
     */
    public function jsonAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $docId = $request->getParam('id');

        if (!is_numeric($docId)) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        /** @var Episciences_Paper $paper */
        $paper = Episciences_PapersManager::get($docId);

        if (!$paper || $paper->getRvid() != RVID || $paper->getRepoid() == 0) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        $this->redirectIfNotPublished($request, $paper);

        $export = $this->exportTo($paper, 'json');

        if ($export) {
            echo $export;
        }

        exit;

    }

    /**
     * exporte en format BIBTEX
     * @throws Zend_Db_Statement_Exception
     */
    public function bibtexAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $docId = $request->getParam('id');

        if (!is_numeric($docId)) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        /** @var Episciences_Paper $paper */
        $paper = Episciences_PapersManager::get($docId);

        if (!$paper || $paper->getRvid() != RVID || $paper->getRepoid() == 0) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        $this->redirectIfNotPublished($request, $paper);

        $export = $this->exportTo($paper, 'bibtex');

        if ($export) {
            echo $export;
        }

        exit;

    }

    /**
     * redirige vers la page de l'article s'il est publié, sinon vers la page de l'authenfification
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_Paper $paper
     * @throws Zend_Db_Statement_Exception
     */
    private function redirectIfNotPublished(Zend_Controller_Request_Http $request, Episciences_Paper $paper)
    {

        if (!$paper->isPublished() && !Episciences_Auth::isLogged()) {
            $paperId = $paper->getPaperid() ? $paper->getPaperid() : $paper->getDocid();
            $id = Episciences_PapersManager::getPublishedPaperId($paperId);

            if ($id != 0) {
                // redirection vers la version publiée
                $this->redirect('/' . $id . '/' . $request->getActionName());
                exit;

            }

            // redirection vers la page d'authentification
            $this->redirect('/user/login/forward-controller/' . $request->getControllerName() . '/id/' . $paper->getDocid() . '/forward-action/' . $request->getActionName());
            exit;
        }

    }

    /**
     * @param Episciences_Paper $paper
     * @param string $format
     * @return bool|false|string
     */
    private function exportTo(Episciences_Paper $paper, string $format)
    {

        if ($format === 'bibtex') {
            header('Content-Type: text/plain; charset: utf-8');
        } elseif ($format === 'json') {
            header('Content-Type: text/json; charset: utf-8');
        } else {
            header('Content-Type: text/xml; charset: utf-8');
        }

        return $paper->get($format);
    }

    /**
     * Export to DataCite Metadata Schema 4.0
     */
    public function dataciteAction()
    {
        return $this->xmlExport('datacite');
    }

    /**
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function xmlExport($format = ''): bool
    {

        if ($format === 'voldoaj') {
            $request = $this->getRequest();
            $params = $request->getParams();
            $getVolume = Episciences_VolumesManager::find($params['vid']);
            $review = Episciences_ReviewsManager::find(Episciences_Review::getCurrentReviewId());
            $volume = '';
            $section = '';

            $listOfPaper = $getVolume->getPaperListFromVolume();
            foreach ($listOfPaper as $key => $value) {
                if (!$value->isPublished()) {
                    unset($listOfPaper[$key]);
                }
            }

            $journal = $review;
            $journal->loadSettings();

            $this->view->listOfPaper = $listOfPaper;
            $this->view->journal = $journal;
            $this->view->volume = $getVolume->getName('en', true);
            
            header('Content-Type: text/xml; charset: utf-8');
            
            $output = $this->view->render('export/volumesdoaj.phtml');

            return $this->displayXml($output);
        }

        $paper = $this->getPaperToExport();

        $previousVersionsUrl = [];
        $previousVersions = $paper->getPreviousVersions(false, false);
        if (!empty($previousVersions)) {
            foreach ($previousVersions as $paperVersions) {
                /** Episciences_Paper $paperVersions */
                if ($paperVersions instanceof Episciences_Paper) {
                    $previousVersionsUrl[] = $paperVersions->getDocUrl();
                }
            }
        }

        $volume = '';
        $section = '';

        if ($paper->getVid()) {
            /* @var $oVolume Episciences_Volume */
            $oVolume = Episciences_VolumesManager::find($paper->getVid());
            if ($oVolume) {
                $volume = $oVolume->getName('en', true);
            }
        }


        if ($paper->getSid()) {
            /* @var $oSection Episciences_Section */
            $oSection = Episciences_SectionsManager::find($paper->getSid());
            if ($oSection) {
                $section = $oSection->getName('en', true);
            }
        }


        // Récupération des infos de la revue
        $journal = Episciences_ReviewsManager::find($paper->getRvid());
        $journal->loadSettings();

        // Create new DOI if none exist
        if ($paper->getDoi() == '') {
            $journalDoi = $journal->getDoiSettings();
            $doi = $journalDoi->createDoiWithTemplate($paper);
        } else {
            $doi = $paper->getDoi();
        }

        $this->view->volume = $volume;
        $this->view->section = $section;
        $this->view->journal = $journal;
        $this->view->paper = $paper;
        $this->view->doi = $doi;
        $this->view->previousVersionsUrl = $previousVersionsUrl;

        $paperLanguage = $paper->getMetadata('language');


        $nbPages = $this->getDocumentBackupNbOfPages($paper);
        $this->view->nbPages = $nbPages;



        if ($paperLanguage == '') {
            $paperLanguage = 'eng';
            // TODO temporary fix see https://gitlab.ccsd.cnrs.fr/ccsd/episciences/issues/215
            // this attribute is required by the datacite schema
            //arxiv doesnt have it, we need to fix this by asking the author additional information
        }

        $this->view->paperLanguage = $paperLanguage;

        header('Content-Type: text/xml; charset: utf-8');

        switch ($format) {
            case 'crossref':
                $output = $this->view->render('export/crossref.phtml');
                break;
            case 'doaj':
                $output = $this->view->render('export/doaj.phtml');
                break;
            case 'zbjats':
                $output = $this->view->render('export/zbjats.phtml');
                break;
            case 'datacite':
                //break omitted
            default:
                $output = $this->view->render('export/datacite.phtml');
                break;

        }


        return $this->displayXml($output);

    }

    /**
     * Export to Crossref
     */
    public function crossrefAction()
    {
        return $this->xmlExport('crossref');
    }


    /**
     * Export to doaj
     */
    public function doajAction()
    {
        return $this->xmlExport('doaj');
    }

    /**
     * Export to DOAJ from a volume
     */
    public function volumesdoajAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $this->xmlExport('voldoaj');
    }


    /**
     * Export to ZbJats
     * https://zbmath.org/zbjats/
     */
    public function zbjatsAction()
    {
        return $this->xmlExport('zbjats');
    }


    /**
     * Exporte en format TEI
     * @throws Zend_Db_Statement_Exception
     */

    public function teiAction()
    {
        $paper = $this->getPaperToExport();

        $export = $this->exportTo($paper, 'tei');

        if ($export) {
            echo $export;
        }

        exit;

    }

    /**
     * Exporte en format DC
     * @throws Zend_Db_Statement_Exception
     */
    public function dcAction()
    {
        $paper = $this->getPaperToExport();

        $export = $this->exportTo($paper, 'dc');

        if ($export) {
            echo $export;
        }

        exit;
    }

    /**
     * @return Episciences_Paper|void
     * @throws Zend_Db_Statement_Exception
     */
    protected function getPaperToExport()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */

        $request = $this->getRequest();
        $docId = $request->getParam('id');

        if (!is_numeric($docId)) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        /** @var Episciences_Paper $paper */
        $paper = Episciences_PapersManager::get($docId);

        if (!$paper || $paper->getRvid() != RVID || $paper->getRepoid() == 0) {
            Episciences_Tools::header('HTTP/1.1 404 Not Found');
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        $this->redirectIfNotPublished($request, $paper);
        return $paper;
    }

    /**
     * @param Episciences_Paper $paper
     * @return int
     */
    private function getDocumentBackupNbOfPages(Episciences_Paper $paper): int
    {
        $paperDocBackup = new Episciences_Paper_DocumentBackup($paper->getDocid());
        $nbPages = 0;
        if ($paperDocBackup->hasDocumentBackupFile()) {
            $parser = new \Smalot\PdfParser\Parser();
            try {
                $pdf = $parser->parseFile($paperDocBackup->getPathFileName());
                $pdfMeta = $pdf->getDetails();
            } catch (Exception $exception) {
                // Fail, meh
            }

            if (!empty($pdfMeta['Pages'])) {
                $nbPages = (int) $pdfMeta['Pages'];
            }
        }
        return $nbPages;
    }

    /**
     * @param string $output
     * @return bool
     */
    public function displayXml(string $output): bool
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $loadResult = $dom->loadXML($output);

        $dom->encoding = 'utf-8';
        $dom->xmlVersion = '1.0';

        if ($loadResult) {
            $output = $dom->saveXML();
            echo $output;
            return true;
        }

        echo '<error>Error loading XML source. Please report to Journal Support.</error>';
        return false;
    }

}
