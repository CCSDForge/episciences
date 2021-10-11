<?php


class DefaultController extends Zend_Controller_Action
{
    protected function isPostMaxSizeReached(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $serverParams = $request->getServer(); // $_SERVER
        try {
            $postMaxSize = Episciences_Tools::convertToBytes(ini_get('post_max_size'));
        } catch (Exception $e) {
            error_log($e->getMessage());
            return true;
        }

        return (isset($serverParams['CONTENT_LENGTH']) && (int)$serverParams['CONTENT_LENGTH'] > $postMaxSize);
    }


    /**
     *
     * Requesting the file of an unpublished version
     * -> Redirects to the published version
     * (eg in case of access with a previous Docid or access by a paperId)
     * But if there's no published version and the user is not logged in
     * -> Redirect to Auth
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function requestingAnUnpublishedFile(Episciences_Paper $paper): void
    {

        if ($this->hasPermissions($paper)) {

            $paperId = $paper->getPaperid() ?: $paper->getDocid();
            $id = Episciences_PapersManager::getPublishedPaperId($paperId);
            if ($id !== 0) {

                $publishedPaper = Episciences_PapersManager::get($id); // published version

                if (!$publishedPaper) {
                    Episciences_Tools::header('HTTP/1.1 404 Not Found');
                    $this->renderScript('index/notfound.phtml');
                    return;
                }

            } else if (!Episciences_Auth::isLogged()) {
                $this->redirect('/user/login/forward-controller/paper/forward-action/pdf/id/' . $paper->getDocid());
            }

            $message = $this->view->translate("Vous n'avez pas accès à cet article.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_WARNING)->addMessage($message);
            $this->redirect('/');

        }

    }

    /**
     * return an error if user is logged in but does not have not enough permissions
     * @param Episciences_Paper $paper
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function redirectsIfHaveNotEnoughPermissions(Episciences_Paper $paper): void
    {

        if ($this->hasPermissions($paper)){
            $message = $this->view->translate("Vous n'avez pas accès à cet article.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_WARNING)->addMessage($message);
            $this->redirect('/');
        }
    }

    /**
     * checked the access to the page of the unpublished version
     * @param Episciences_Paper $paper
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function hasPermissions(Episciences_Paper $paper): bool
    {
        $journalSettings = Zend_Registry::get('reviewSettings');

        $loggedUid = Episciences_Auth::getUid();

        $isAllowToEditors =
            (
                isset($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_EDITORS]) &&
                $journalSettings[Episciences_Review::SETTING_ENCAPSULATE_EDITORS] === '0'
            ) && Episciences_Auth::isEditor();

        $isAllowToCopyEditors =
            (
                isset($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS]) &&
                $journalSettings[Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS] === '0'
            ) && Episciences_Auth::isCopyEditor();

        return (!$isAllowToEditors || !$isAllowToCopyEditors) && !$paper->isPublished() &&
            !Episciences_Auth::isSecretary() && // nor editorial secretary or user is not chief editor or // nor admin
            !$paper->getEditor($loggedUid) && // assigned editors
            !array_key_exists($loggedUid, $paper->getReviewers()) && // nor reviewer
            $paper->getUid() !== $loggedUid;
    }
}