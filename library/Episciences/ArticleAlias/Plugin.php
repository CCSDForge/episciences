<?php

declare(strict_types=1);

/**
 * CLOCKSS transition: the /articles/... aliases take a paper id — the canonical
 * reference displayed by the interface and used by the new front end — whereas the
 * legacy routes take a docid, which identifies one specific version.
 *
 * This plugin translates the "id" parameter of the aliased routes into the docid of
 * the published version, before the request reaches PaperController. The served URL
 * is left untouched (no HTTP redirect): /articles/{paperid} answers 200 with the
 * published version, which is what an archiving agent is expected to harvest.
 *
 * An id that is not the paper id of a published paper is left as is, so an alias
 * built with a docid keeps working, as do the legacy /{docid} routes.
 */
class Episciences_ArticleAlias_Plugin extends Zend_Controller_Plugin_Abstract
{
    /**
     * Routes declared in application/configs/application.ini whose "id" is a paper id.
     */
    public const PAPER_ID_ROUTES = [
        'articles',
        'articles_download',
        'articles_lang',
        'articles_lang_download',
    ];

    public function routeShutdown(Zend_Controller_Request_Abstract $request): void
    {
        if (!in_array($this->getCurrentRouteName(), self::PAPER_ID_ROUTES, true)) {
            return;
        }

        $paperId = (int)$request->getParam('id');

        if ($paperId <= 0) {
            return;
        }

        $docId = $this->getPublishedDocId($paperId);

        if ($docId > 0) {
            $request->setParam('id', $docId);
        }
    }

    /**
     * @return int the docid of the published version, 0 when there is none
     */
    protected function getPublishedDocId(int $paperId): int
    {
        try {
            return Episciences_PapersManager::getPublishedPaperId($paperId);
        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return 0;
        }
    }

    private function getCurrentRouteName(): string
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();

        if (!$router instanceof Zend_Controller_Router_Rewrite) {
            return '';
        }

        try {
            return (string)$router->getCurrentRouteName();
        } catch (Zend_Controller_Router_Exception) {
            return '';
        }
    }
}