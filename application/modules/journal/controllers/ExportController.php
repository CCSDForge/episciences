<?php

use Episciences\Paper\Export;

/**
 * Class ExportController
 * Export formats of a paper
 */
class ExportController extends Episciences_Controller_Action
{
    const TEXT_XML_CHARSET_UTF_8 = 'text/xml; charset=utf-8';

    /**
     * @throws Zend_Db_Statement_Exception
     */
    public function jsonAction(): void
    {
        $paper = $this->getPaperToExport();
        header('Content-Type: application/json; charset=UTF-8');
        echo Export::getJson($paper);
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
            $this->getResponse()?->setHttpResponseCode(404);
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        /** @var Episciences_Paper $paper */
        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper || $paper->getRvid() != RVID || $paper->getRepoid() == 0) {
            $this->getResponse()?->setHttpResponseCode(404);
            $this->renderScript('index/notfound.phtml');
            echo $this->getResponse()->getBody();
            exit;
        }

        $this->redirectIfNotPublished($request, $paper);
        return $paper;
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
            $paperId = $paper->getPaperid() ?: $paper->getDocid();
            $id = Episciences_PapersManager::getPublishedPaperId($paperId);

            if ($id > 0) {
                // redirection vers la version publiée
                $this->redirect(sprintf('%s%s/%s', PREFIX_URL, $id, $request->getActionName()));
                exit;

            }

            $url = $this->url(['controller' => 'user', 'action' => 'login', 'forward-controller' => $request->getControllerName() , 'id' => $paper->getDocid(), 'forward-action' => $request->getActionName()] );

            // redirection vers la page d'authentification
            $this->redirect($url);
            exit;
        }

    }

    public function jsonv2Action(): void
    {
        $paper = $this->getPaperToExport();
        header('Content-Type: application/json; charset=UTF-8');
        echo $paper->toJson();
    }

    /**
     * exporte en format BIBTEX
     * @throws Zend_Db_Statement_Exception
     */
    public function bibtexAction()
    {
        $paper = $this->getPaperToExport();
        header('Content-Type: text/plain; charset=utf-8');
        echo Export::getBibtex($paper);
    }

    /**
     * Export to DataCite Metadata Schema 4.0
     */
    public function dataciteAction()
    {
        $paper = $this->getPaperToExport();
        return $this->displayXml(Export::getOpenaire($paper));
    }

    /**
     * @param string $output
     * @return bool
     */
    public function displayXml(string $output): bool
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $loadResult = $dom->loadXML($output);
        header('Content-Type: text/xml; charset: utf-8');
        if ($loadResult) {
            $output = $dom->saveXML();
            $output = str_replace(array("\r\n", "\r", "\n"), '', $output);
            echo $output;
            return true;
        }

        echo '<error>Error loading XML source. Please report to Journal Support.</error>';
        trigger_error('XML Fail in export: ' . $output, E_USER_WARNING);
        return false;
    }

    /**
     * Export to Crossref
     */
    public function crossrefAction()
    {
        $paper = $this->getPaperToExport();
        return $this->displayXml(Export::getCrossref($paper));
    }

    /**
     * Export to doaj
     */
    public function doajAction()
    {
        $paper = $this->getPaperToExport();
        return $this->displayXml(Export::getDoaj($paper));
    }


    /**
     * Export to ZbJats
     * https://zbmath.org/zbjats/
     */
    public function zbjatsAction()
    {
        $paper = $this->getPaperToExport();
        return $this->displayXml(Export::getZbjats($paper));
    }

    /**
     * Exporte en format TEI
     * @throws Zend_Db_Statement_Exception
     */

    public function teiAction()
    {
        $paper = $this->getPaperToExport();
        return $this->displayXml(Export::getTei($paper));
    }

    /**
     * Exporte en format DC
     * @throws Zend_Db_Statement_Exception
     */
    public function dcAction()
    {
        $paper = $this->getPaperToExport();
        return $this->displayXml(Export::getDc($paper));
    }

}
