<?php

declare(strict_types=1);

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
        $redirection = $this->buildRoleRedirection($review, $paper);

        if (isset($redirection['isFinal'])) { // guest editors are fully blocked: skip the per-action settings
            unset($redirection['isFinal']);
            return $redirection;
        }

        $actionMessage = $this->buildActionDeniedMessage($review, $paper);

        if ($actionMessage !== null) {
            $redirection['message'] = $actionMessage;
        }

        return $redirection;
    }

    /**
     * Role-based restrictions (editors / copy editors encapsulation, guest editors).
     * The 'isFinal' key marks a redirection that must not be overridden by the per-action checks.
     * @param Episciences_Review $review
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    private function buildRoleRedirection(Episciences_Review $review, Episciences_Paper $paper): array
    {
        $redirection = [];
        $message = "Vous n'avez pas les droits suffisants pour accéder à cet article";

        $isAssigned = $paper->getEditor(Episciences_Auth::getUid()) || $paper->getCopyEditor(Episciences_Auth::getUid());

        if ($isAssigned) {
            return $redirection;
        }

        // if editors encapsulation is on, editors who are not assigned to this paper do not have any permission for it: redirect them
        if (Episciences_Auth::isEditor() && $review->getSetting('encapsulateEditors')) {
            $redirection['message'] = $message;
        } elseif (Episciences_Auth::isCopyEditor() && $review->getSetting('encapsulateCopyEditors')) {
            // same rule for copy editors encapsulation
            $redirection['message'] = $message;
            $redirection['params'] = ['ce' => 1];
        } elseif (Episciences_Auth::isGuestEditor() && !(Episciences_Auth::isEditor() || Episciences_Auth::isCopyEditor())) {
            $redirection['message'] = $message;
            $redirection['isFinal'] = true;
        }

        return $redirection;
    }

    /**
     * Check if journal settings allow editors to take the decision matching the current action.
     * @param Episciences_Review $review
     * @param Episciences_Paper $paper
     * @return string|null a denial message, or null when the action is allowed
     * @throws Zend_Db_Statement_Exception
     */
    private function buildActionDeniedMessage(Episciences_Review $review, Episciences_Paper $paper): ?string
    {
        $settingAndMessageByAction = [
            'accept' => [
                Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS,
                "Vous n'avez pas les droits suffisants pour accepter cet article",
            ],
            'refuse' => [
                Episciences_Review::SETTING_EDITORS_CAN_REJECT_PAPERS,
                "Vous n'avez pas les droits suffisants pour refuser cet article",
            ],
            'revision' => [
                Episciences_Review::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS,
                "Vous n'avez pas les droits suffisants pour demander des modifications sur cet article",
            ],
        ];

        $action = $this->getRequest()->getActionName();

        if ($action === 'publish') {
            // a copy editor assigned to a paper approved by its author may publish it even when
            // the journal settings do not allow editors to publish
            $isAllowed = $review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS) ||
                ($paper->isApprovedByAuthor() && $paper->getCopyEditor(Episciences_Auth::getUid()));

            return $isAllowed ? null : "Vous n'avez pas les droits suffisants pour publier cet article";
        }

        if (isset($settingAndMessageByAction[$action]) && !$review->getSetting($settingAndMessageByAction[$action][0])) {
            return $settingAndMessageByAction[$action][1];
        }

        return null;
    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Review $review
     * @return void
     */
    private function redirectWithFlashMessageIfConflictDetected(Episciences_Paper $paper, Episciences_Review $review): void
    {
        $docId = $paper->getDocid();

        if ($paper->isOwner()) {
            $message = $this->buildOwnSubmissionRedirectMessage();
            $url = $this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]);
        } elseif (self::isConflictDetected($paper, $review)) {
            $message = $this->buildConflictRedirectMessage($paper->checkConflictResponse(Episciences_Auth::getUid()));
            $url = $this->url(['controller' => 'coi', 'action' => 'report', 'id' => $docId]);
        } else { // user has required permissions
            return;
        }

        $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
        $this->_helper->redirector->gotoUrl($url);
    }

    /**
     * Message shown when a user tries to manage their own submission,
     * prefixed with the original identity when the user is switched (su).
     * @return string
     */
    private function buildOwnSubmissionRedirectMessage(): string
    {
        $message = '';
        $suUser = Episciences_Auth::getOriginalIdentity();

        if ($suUser && ($suUser->getUid() !== Episciences_Auth::getUid())) {
            $message .= $suUser->getScreenName();
            $message .= ', ';
            $message .= '<br>';
            $message .= $this->view->translate("Vous êtes connecté en tant que : ");
            $message .= Episciences_Auth::getScreenName();
            $message .= '<br>';
        }

        $message .= $this->view->translate('Vous avez été redirigé, car vous ne pouvez pas gérer un article que vous avez vous-même déposé');

        return $message;
    }

    /**
     * Message shown when a conflict of interest prevents the user from managing the paper.
     * @param string $checkConflictResponse the logged user's answer to the conflict check
     * @return string
     */
    private function buildConflictRedirectMessage(string $checkConflictResponse): string
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $suUser = Episciences_Auth::getOriginalIdentity();
        $suAnswers = [Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'], Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']];

        if (
            $suUser &&
            isset($session->checkConflictResponseForSu) &&
            in_array($session->checkConflictResponseForSu, $suAnswers, true)
        ) {
            return $this->buildSwitchedUserConflictMessage($suUser, $session->checkConflictResponseForSu, $checkConflictResponse);
        }

        if ($checkConflictResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {
            return $this->translateConflictConfirmationRequiredMessage();
        }

        return $this->view->translate("Vous avez été redirigé, car vous avez déclaré un conflit d'intérêts avec cette soumission.");
    }

    /**
     * Conflict message for a switched (su) session, based on the answer given while switched.
     * @param Episciences_User $suUser the original identity
     * @param string $suResponse the answer given while switched
     * @param string $checkConflictResponse the logged user's answer to the conflict check
     * @return string
     */
    private function buildSwitchedUserConflictMessage(Episciences_User $suUser, string $suResponse, string $checkConflictResponse): string
    {
        $message = $suUser->getScreenName();
        $message .= ', ';
        $message .= '<br>';

        if ($suResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {
            // switch back to the original account: the conflict confirmation is expected from it
            Episciences_Auth::updateIdentity($suUser);

            $message .= $this->view->translate("Vous êtes maintenant connecté à votre compte :");
            $message .= '<br>';
            $message .= $this->translateConflictConfirmationRequiredMessage();

            return $message;
        }

        $message .= $this->view->translate("Vous avez vous-même signalé un conflit d'intérêts avec cette soumission.");
        $message .= '<br>';
        $message .= $this->view->translate("Vous êtes connecté en tant que : ");
        $message .= Episciences_Auth::getScreenName();
        $message .= '<br>';

        if ($checkConflictResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {
            $message .= $this->translateConflictConfirmationRequiredMessage();
        }

        return $message;
    }

    /**
     * @return string
     */
    private function translateConflictConfirmationRequiredMessage(): string
    {
        return $this->view->translate("Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission");
    }
}
