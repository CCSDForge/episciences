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
            trigger_error($e->getMessage());
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

        if ($this->isRestrictedAccess($paper)) {

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

        if ($this->isRestrictedAccess($paper)) {
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
    protected function isRestrictedAccess(Episciences_Paper $paper): bool
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

        return !$isAllowToEditors && !$isAllowToCopyEditors && !$paper->isPublished() &&
            !Episciences_Auth::isSecretary() && // nor editorial secretary or user is not chief editor or // nor admin
            !$paper->getEditor($loggedUid) &&
            !$paper->getCopyEditor($loggedUid) &&
            !array_key_exists($loggedUid, $paper->getReviewers()) && // nor reviewer
            !$paper->isOwner();
    }

    protected function redirectWithFlashMessageIfPaperIsRemovedOrDeleted(Episciences_Paper $paper, bool $forceRedirection = true): void
    {
        if ($paper->isDeleted() || $paper->isRemoved()) {
            $message = $paper->isDeleted() ? 'Le document demandé a été supprimé par son auteur.' : 'Le document demandé a été supprimé par la revue.';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($this->view->translate($message));
            if ($forceRedirection) {
                $this->redirect('/'); // redirect and immediately exit
            }
        }
    }


    /**
     * @param array|null $allowedRepositories
     * @param array $options
     * @return string
     * @throws Zend_Exception
     */
    protected function getZSubmitUrl(array $allowedRepositories = null, array $options = []): string
    {

        $zSubmitUrl = EPISCIENCES_Z_SUBMIT_URL;

        if (empty($allowedRepositories)) {
            $settings = Zend_Registry::get('reviewSettings');
            $allowedRepositories = Episciences_Submit::getRepositoriesLabels($settings);
        }

        if (array_key_exists(Episciences_Repositories::ZENODO_REPO_ID, $allowedRepositories)) {

            try {
                $zSubmitUrl .= '/' . Episciences_Tools::getLocale();

            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
            }

            $zSubmitUrl .= '/deposit';

            if (array_key_exists('newVersion', $options)) {

                $zSubmitUrl .= '/newversionfromepisciences';

                if (isset($options['epi-docid'])) {
                    $zSubmitUrl .= '?epi-docid=' . $options['epi-docid'];
                }

                if (isset($options['epi-rvcode'])) {
                    $zSubmitUrl .= '&epi-rvcode=' . $options['epi-rvcode'];
                }

                if (isset($options['epi-cdoi'])) {

                    $epiCDoi = Episciences_Repositories::getRepoDoiPrefix(Episciences_Repositories::ZENODO_REPO_ID) . '/' . mb_strtolower(Episciences_Repositories::getLabel(Episciences_Repositories::ZENODO_REPO_ID)) . '.';
                    $epiCDoi .= $options['epi-cdoi'];
                    $zSubmitUrl .= '&epi-cdoi=' . $epiCDoi;
                }

                return $zSubmitUrl;

            }
            $zSubmitUrl .= '?rvcode=' . RVCODE;
        }

        return $zSubmitUrl;

    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Review|null $journal
     * @return bool
     */
    public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
    {
        if(Episciences_Auth::isRoot()){
            return false;
        }

        if (!$journal) {

            $review = Episciences_ReviewsManager::find(RVID);

            if ($review) {
                $review->loadSettings();
            }

        }

        $loggedUid = Episciences_Auth::getUid();

        $suUser = Episciences_Auth::getOriginalIdentity();

        $isSignedInAs = $suUser->getUid() !== $loggedUid;

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

        if ($isSignedInAs) {
            if (!$suUser->isRoot()) {
                $checkConflictResponseForSu = $paper->checkConflictResponse($suUser->getUid());
                $session->checkConflictResponseForSu = $checkConflictResponseForSu;
            } else {
                $checkConflictResponseForSu = Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'];
            }

        } else {
            unset($session->checkConflictResponseForSu);
        }

        $checkConflictResponse = $paper->checkConflictResponse($loggedUid);

        $isCoiEnabled = !$journal ? $review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED) : $journal->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED);
        $isCoiEnabled = (boolean)$isCoiEnabled;

        $conflictResponses = [Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'], Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']];

        return $isCoiEnabled &&
            Episciences_Auth::isAllowedToDeclareConflict() &&
            (
                ($isSignedInAs && in_array($checkConflictResponseForSu, $conflictResponses, true)) ||
                in_array($checkConflictResponse, $conflictResponses, true)
            );

    }


    protected function keepOnlyUsersWithoutConflict(Episciences_Paper $paper, array &$recipients = []): void
    {

        $isCoiEnabled = false;


        try {
            $journalSettings = Zend_Registry::get('reviewSettings');
            $isCoiEnabled = isset($journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED]) && (int)$journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED] === 1;
        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage());
        }


        if ($isCoiEnabled) {

            $cUidS = Episciences_Paper_ConflictsManager::fetchSelectedCol('by', ['answer' => 'no', 'paper_id' => $paper->getPaperid()]);

            foreach ($recipients as $recipient) {
                $rUid = $recipient->getUid();

                if (!in_array($rUid, $cUidS, false)) {
                    unset($recipients[$rUid]);
                }
            }

        }


    }
}