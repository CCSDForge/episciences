<?php

/**
 * Trait for shared "manage this paper" access-control logic.
 * Used by AdministratepaperController and ActivityController so both
 * enforce the exact same editor/copy-editor/secretary permission rules.
 *
 * Requirements for using class:
 * - Must extend PaperDefaultController (provides ADMINISTRATE_PAPER_CONTROLLER)
 * - Must have access to $this->_helper->FlashMessenger
 * - Must have access to $this->_helper->redirector
 * - Must have access to $this->url() (Episciences_Controller_Action override)
 * - Must have access to $this->view
 * - Must have access to $this->getRequest()
 */
trait Episciences_Paper_AccessControlControllerTrait
{
    /**
     * check user permissions according to controller action
     * if access is denied, redirect to another page with an error message
     * @param Episciences_Review $review
     * @param Episciences_Paper $paper
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    protected function checkPermissions(Episciences_Review $review, Episciences_Paper $paper): bool
    {
        // chief editors, administrator and secretary (git #235) can do whatever they want
        if (Episciences_Auth::isSecretary()) {
            return true;
        } // check if editors have sufficient permission for accessing paper or changing its status

        $redirection = $this->buildRedirectionMessage($review, $paper);
        $params = array_key_exists('params', $redirection) ? $redirection['params'] : [];

        if (!empty($redirection) && array_key_exists('message', $redirection)) {
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($redirection['message']);
            // redirect target is always the editor's assigned papers list, regardless of the caller
            $this->_helper->redirector->gotoUrl($this->url(array_merge(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'assigned'], $params)));
        }

        return empty($redirection);
    }

    /**
     * @param Episciences_Review $review
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    private function buildRedirectionMessage(Episciences_Review $review, Episciences_Paper $paper): array
    {
        $redirection = [];

        $isNextTest = true;

        $message = "Vous n'avez pas les droits suffisants pour accéder à cet article";


        if ($paper->getEditor(Episciences_Auth::getUid()) || $paper->getCopyEditor(Episciences_Auth::getUid())) { // assigned
            $isNextTest = false;
        }

        // if editors encapsulation is on, editors who are not assigned to this paper do not have any permission for it: redirect them
        if ($isNextTest && Episciences_Auth::isEditor() && $review->getSetting('encapsulateEditors')) {
            $redirection['message'] = $message;
            $isNextTest = false;
        }

        // if copy editors encapsulation is on, copy editors who are not assigned to this paper do not have any permission for it: redirect them
        if ($isNextTest && Episciences_Auth::isCopyEditor() && $review->getSetting('encapsulateCopyEditors')) {
            $redirection['message'] = $message;
            $redirection['params'] = ['ce' => 1];
            $isNextTest = false;
        }


        if ($isNextTest && Episciences_Auth::isGuestEditor() && !(Episciences_Auth::isEditor() || Episciences_Auth::isCopyEditor())) {
            $redirection['message'] = $message;
            return $redirection;
        }

        // check if journal settings allow editors to take decisions about this paper
        switch ($this->getRequest()->getActionName()) {

            case 'accept':
                if (!$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS)) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour accepter cet article";
                }
                break;

            case 'publish':

                if (
                    !$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS) &&
                    !($paper->isApprovedByAuthor() && $paper->getCopyEditor(Episciences_Auth::getUid()))
                ) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour publier cet article";
                }
                break;

            case 'refuse':
                if (!$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_REJECT_PAPERS)) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour refuser cet article";
                }
                break;

            case 'revision':
                if (!$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS)) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour demander des modifications sur cet article";
                }
                break;
            default: // not action
                break;
        }


        return $redirection;

    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Review $review
     * @return void
     */
    private function redirectWithFlashMessageIfConflictDetected(Episciences_Paper $paper, Episciences_Review $review): void
    {
        $docId = $paper->getDocid();
        $loggedUid = Episciences_Auth::getUid();

        $checkConflictResponse = $paper->checkConflictResponse($loggedUid);

        $isOwnSubmission = $paper->isOwner();
        $isConflictDetected = self::isConflictDetected($paper, $review);

        // check if user has required permissions
        if ($isOwnSubmission || $isConflictDetected) {

            $suUser = Episciences_Auth::getOriginalIdentity();

            $message = '';

            if ($isOwnSubmission) {

                if ($suUser && ($suUser->getUid() !== $loggedUid)) {

                    $message .= $suUser->getScreenName();
                    $message .= ', ';
                    $message .= '<br>';
                    $message .= $this->view->translate("Vous êtes connecté en tant que : ");
                    $message .= Episciences_Auth::getScreenName();
                    $message .= '<br>';
                }


                $message .= $this->view->translate('Vous avez été redirigé, car vous ne pouvez pas gérer un article que vous avez vous-même déposé');
                $url = '/paper/view?id=' . $docId;

            } else {

                $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

                if (
                    isset($session->checkConflictResponseForSu) &&
                    in_array($session->checkConflictResponseForSu, [Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'], Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']], true)
                ) {

                    $message .= $suUser->getScreenName();
                    $message .= ', ';
                    $message .= '<br>';

                    if ($session->checkConflictResponseForSu === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {

                        Episciences_Auth::updateIdentity($suUser);

                        $message .= $this->view->translate("Vous êtes maintenant connecté à votre compte :");
                        $message .= '<br>';
                        $message .= $this->view->translate("Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission");

                    } else {
                        $message .= $this->view->translate("Vous avez vous-même signalé un conflit d'intérêts avec cette soumission.");
                        $message .= '<br>';
                        $message .= $this->view->translate("Vous êtes connecté en tant que : ");
                        $message .= Episciences_Auth::getScreenName();
                        $message .= '<br>';


                        if ($checkConflictResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {
                            $message .= $this->view->translate("Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission");
                        }

                    }

                } elseif ($checkConflictResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {
                    $message = $this->view->translate("Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission");

                } else {
                    $message = $this->view->translate("Vous avez été redirigé, car vous avez déclaré un conflit d'intérêts avec cette soumission.");
                }

                $url = '/coi/report?id=' . $docId;

            }


            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);

        }

    }
}
