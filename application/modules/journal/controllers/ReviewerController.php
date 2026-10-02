<?php

use Episciences\User\UserNotFoundException;

require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

class ReviewerController extends PaperDefaultController
{
    /**
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function invitationAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $invitationId = $request->getParam('id');

        $tmpUser = null;

        // check if invitation id is valid
        if (!$invitationId || !is_numeric($invitationId)) {
            $this->view->errors = array("Cette invitation n'existe pas !");
            return;
        }

        // fetch invitation
        $invitation = Episciences_User_InvitationsManager::find(array('ID' => $invitationId));

        if (!$invitation) {
            $this->view->errors = array("Cette invitation n'existe pas !");
            return;
        }

        // fetch assignment
        $assignmentId = $invitation->getAid();
        $assignment = Episciences_User_AssignmentsManager::findById($assignmentId);

        if (!Episciences_Auth::isLogged() && $assignment->isTmp_user()) {

            $tmpUser = Episciences_TmpUsersManager::findById($assignment->getUid());

            if (md5($tmpUser->getEmail()) !== $request->getParam('tmp')) {
                $this->view->errors = 'Lien non valide; le jeton transmis dans la requête est erroné.';
                return;
            }

        }

        // fetch reviewer answer (if there is one)
        $invitation->loadAnswer();

        //  error handling
        if (
            $invitation->isAnswered() ||
            $invitation->hasExpired() ||
            $invitation->isCancelled()
        ) {

            $errorMsg = $assignment->getUid() === Episciences_Auth::getUid() ? 'Vous avez déjà répondu à cette invitation.' : "Il apparaît qu'une réponse a déjà été transmise.";

            if (
                $invitation->hasExpired() ||
                $invitation->isCancelled()
            ) {

                if ($invitation->hasExpired()) {
                    $errorMsg = 'Cette invitation a expiré.';
                } else {

                    $errorMsg = "Cette invitation a été annulée, vous n'avez plus besoin d'évaluer cet article.";
                }


            }

            $this->view->displayInvitationDetails = true;
            $this->view->invitation = $invitation;
            $this->view->errors = $errorMsg;
            return;

        }

        // processing of the request
        if (!$invitation->hasExpired() && !$invitation->isAnswered()) {
            $this->view->jQuery()->addJavascriptFile("/js/reviewer/invitation.js");
        }

        $paper = Episciences_PapersManager::get($assignment->getItemid());
        $checkIsAlreadyInvited = $this->checkIsAlreadyInvited($paper);
        $isAlreadyInvited = $checkIsAlreadyInvited['isAlreadyInvited'] ?? false;

        $this->view->isAlreadyInvited = $isAlreadyInvited;
        $this->view->latestInvitationUrl = $checkIsAlreadyInvited['url'] ?? null;

        $result = [];

        if (!$isAlreadyInvited) {
            $result = $this->checkAndProcessLinkedInvitation($request, $invitation, $assignment);
        }


        $isAlreadyLinked = ($result['isAlreadyLinked'] ?? false) && $result['isAlreadyLinked'];

        if ($isAlreadyLinked && $assignment->getFrom_uid() === Episciences_Auth::getUid()) {
            $errorMsg = "Cette invitation vous était initialement destinée, mais elle a déjà été utilisée par un autre compte. Si vous pensez qu’il s’agit d’une erreur, veuillez contacter notre support.";
            $this->view->errors = $errorMsg;
            return;
        }

        $isPreLinked = ($result['isPreLinked'] ?? false) && $result['isPreLinked'];


        if ($isPreLinked) {
            $hasDecision = $result['decision'] ?? false;
            $isDeclinedToLInk = $hasDecision && $result['decision'] === 'declineToLink';
            $isAcceptedToLInk = $hasDecision && $result['decision'] === 'acceptToLink';

            if ($isAcceptedToLInk || $isDeclinedToLInk) {

                if ($isDeclinedToLInk) {
                    $errorMsg = "Cette invitation ne vous est pas destinée";
                    $this->view->errors = $errorMsg;

                }

                $this->view->displayLinkedInvitationForm = false;
            } else {
                $errorMsg = "Cette invitation n'est pas liée au compte en cours";
                $this->view->errors = $errorMsg;
                $this->view->displayLinkedInvitationForm = true;
            }
        }

        // INVITATION
        $this->view->invitation = $invitation;

        $this->view->rating_deadline = $assignment->getDeadline();


        // ARTICLE A RELIRE *******************************************

        $paper->setXslt($paper->getXml(), 'partial_paper');
        $this->view->paper = $paper;

        // Cover letter, git #160

        $author_comments = Episciences_CommentsManager::getList(
            $paper->getDocid(),
            [
                'type' => Episciences_CommentsManager::TYPE_AUTHOR_COMMENT
            ]);

        $this->view->author_comments = $author_comments;

        // check if paper still needs to be reviewed
        $error = $this->checkPaperStatus($paper);
        if ($error) {
            $this->view->errors = array($error);
            return;
        }

        $this->answerProcess($request, $invitation, $assignment, $paper, $tmpUser);

    }

    private function checkPaperStatus(Episciences_Paper $paper): ?string
    {
        $error = null;

        if ($paper->isAccepted()) {
            $error = $this->view->translate("Cet article a déjà été accepté, il n'est plus nécessaire de le relire.");
        } elseif ($paper->isPublished()) {
            $error = $this->view->translate("Cet article a déjà été publié, il n'est plus nécessaire de le relire.");
        } elseif ($paper->isRefused()) {
            $error = $this->view->translate("Cet article a été refusé, il n'est plus nécessaire de le relire.");
        } elseif ($paper->isRemoved() || $paper->isDeleted()) {
            $error = $this->view->translate("Cet article a été supprimé, il n'est plus nécessaire de le relire.");
        } elseif ($paper->isObsolete()) {
            $error = $this->view->translate("Cet article est obsolète, il n'est plus nécessaire de le relire.");
        } elseif ($paper->isRevisionRequested()) {
            $error = $this->view->translate("Cet article est en cours de révision, il n'est plus nécessaire de le relire.");
        }

        return $error;
    }

    /**
     * @param Episciences_User_Invitation $oInvitation
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_Paper $paper
     * @param $data
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function saveanswer(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, $data): void
    {
        if (
            array_key_exists('submitaccept', $data) ||
            (isset($data['is-accepted']) && $data['is-accepted'])
        ) {

            // accepted invitation
            $this->accept($oInvitation, $assignment, $paper, $data);
            $this->_helper->redirector->gotoUrl($this->_helper->url('rating', 'paper', null, ['id' => $paper->getDocid()]));

        } elseif (array_key_exists('submitrefuse', $data)) {

            // declined invitation
            $this->decline($oInvitation, $assignment, $paper, $data);
            $this->redirect('/');

        }
    }

    /**
     * @param Episciences_User_Invitation $oInvitation
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_Paper $paper
     * @param $data
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    private function accept(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, $data): void
    {
        // update user permissions
        if ($assignment->isTmp_user()) {
            $user = $this->createNewReviewerWithoutAccountProcessing($data);
        } else {
            if (!Episciences_Auth::isLogged()) {
                // user needs to login
                $redirect_params = [
                    'controller' => 'user',
                    'action' => 'login',
                    'forward-controller' => 'reviewer',
                    'forward-action' => 'invitation',
                    'id' => $oInvitation->getId(),
                    'is-accepted' => array_key_exists('submitaccept', $data)
                ];
                $this->redirect($this->view->url($redirect_params));
                return;
            }

            $user = $this->createNewReviewerWithExistingAccountProcessing($assignment->getUid());
        }

        // apply all the acceptance side effects (answer, status, assignment, alias,
        // rating report, log, paper status and e-mails) — shared with the editor
        // "accept on behalf" flow in AdministratepaperController.
        $this->performReviewerInvitationAcceptance($oInvitation, $assignment, $paper, $user);
    }

    /**
     * @param Episciences_User_Invitation $oInvitation
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_Paper $paper
     * @param $data
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function decline(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, $data): void
    {
        // save invitation answer
        $oInvitationAnswer = new Episciences_User_InvitationAnswer();
        $oInvitationAnswer->setId($oInvitation->getId());
        $oInvitationAnswer->setAnswer(Episciences_User_InvitationAnswer::ANSWER_NO);
        $oInvitationAnswer->setDetail(Episciences_User_InvitationAnswer::DETAIL_SUGGEST, $data['suggestreviewer']);
        $oInvitationAnswer->setDetail(Episciences_User_InvitationAnswer::DETAIL_COMMENT, $data['comment']);
        $oInvitationAnswer->save();

        // update invitation status
        $oInvitation->setStatus($oInvitation::STATUS_DECLINED);
        $oInvitation->save();

        $uid = $assignment->getUId();
        if ($assignment->isTmp_user()) {
            $user = new Episciences_User_Tmp();

            if (!empty($user->find($uid))) {
                $user->generateScreen_name();
            }

        } else {
            $user = new Episciences_User;
            $user->findWithCAS($uid);
        }

        // save assignment update
        $params = [
            'itemid' => $assignment->getItemid(),
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'status' => Episciences_User_Assignment::STATUS_DECLINED,
            'tmp_user' => $assignment->isTmp_user()
        ];

        $newAssignment = Episciences_UsersManager::unassign($uid, $params)[0];
        $newAssignment->setInvitation_id($oInvitation->getId());
        $newAssignment->save();

        // log reviewer invitation refusal
        $paper->log(
            Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_DECLINED,
            $user->getUid(),
            [
                'invitation_answer_id' => $oInvitationAnswer->getId(),
                'invitation_id' => $oInvitation->getId(),
                'assignment_id' => $newAssignment->getId(),
                'user' => $user->toArray(),
                'reviewer_suggestion' => $data['suggestreviewer'],
                'refusal_reason' => $data['comment']
            ]);

        $this->emailSendingProcessing($user, $paper, $newAssignment, Episciences_User_InvitationAnswer::ANSWER_NO, $data);


    }

    /**
     *  create new user (don't have an account yet)
     * @param array $data
     * @return Episciences_Reviewer
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function createNewReviewerWithoutAccountProcessing(array $data): Episciences_Reviewer
    {
        $user = new Episciences_Reviewer($data);
        $user->setTime_registered();
        $user->setValid(1);
        $uid = $user->save();
        $user->setUid($uid);

        // give him reviewer permissions
        $user->addRole(Episciences_Acl::ROLE_REVIEWER);

        // sign him in
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::setIdentity($user);
        $user->setScreenName();

        return $user;

    }

    /**
     * Create new reviewer (existing account)
     * @param int $uid
     * @return Episciences_Reviewer
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function createNewReviewerWithExistingAccountProcessing(int $uid): Episciences_Reviewer
    {
        $isNecessaryToSaveUser = false;

        $user = new Episciences_Reviewer();
        $user->findWithCAS($uid);

        if (!$user->getScreenName()) {
            $isNecessaryToSaveUser = true;
            $user->setScreenName($user->getFullName());
        }

        if (!$user->getLangueid()) {
            $isNecessaryToSaveUser = true;
            $user->setLangueid(Episciences_Review::DEFAULT_LANG);
        }

        if ($isNecessaryToSaveUser) {
            $user->save();
        }

        $user->addRole(Episciences_Acl::ROLE_REVIEWER);
        return $user;
    }

    /**
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_User_Invitation $invitation
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_Paper $paper
     * @param Episciences_User_Tmp|null $tmpUser
     * @return void
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function answerProcess(
        Zend_Controller_Request_Http $request,
        Episciences_User_Invitation  $invitation,
        Episciences_User_Assignment  $assignment,
        Episciences_Paper            $paper,
        Episciences_User_Tmp         $tmpUser = null
    ): void
    {
        // answer forms **************************************
        if (
            !$invitation->hasExpired() &&
            !$invitation->isAnswered() &&
            !$invitation->isCancelled()) {

            // empty form created for validation only (real form is in viewscript)
            //$accept_form = new Episciences_User_Form_Create();
            $refuse_form = Episciences_ReviewersManager::refuseInvitationForm();

            if ($tmpUser) {

                $this->view->jQuery()->addJavascriptFile("/js/user/affiliations.js");
                $user_form = Episciences_ReviewersManager::acceptInvitationForm();
                $user_form->setDefaults([
                    'SCREEN_NAME' => '',
                    'LASTNAME' => '',
                    'FIRSTNAME' => '',
                    'EMAIL' => $tmpUser->getEmail(),
                    'LANGUEID' => $tmpUser->getLangueid(true)
                ]);
                $this->view->user_form = $user_form;
            }

            $accepted = (
                array_key_exists('submitaccept', $request->getPost()) ||
                (
                    Episciences_Auth::isLogged() &&
                    $request->getParam('is-accepted') &&
                    $request->getParam('is-accepted') === '1'
                )
            );

            $refused = (array_key_exists('submitrefuse', $request->getPost()));

            if ($accepted || $refused) {

                if (
                    $refused ||
                    (
                        $accepted &&
                        (
                            !$assignment->isTmp_user() ||
                            (isset($user_form) && $user_form->isValid($request->getPost()))
                        )
                    )
                ) {

                    $data = $request->isPost() ? $request->getPost() : ['is-accepted' => $accepted];

                    $this->saveanswer($invitation, $assignment, $paper, $data);
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($this->view->translate("Votre réponse a bien été enregistrée."));


                } else {
                    $this->view->invalid_form = true;
                }
            }

            $this->view->is_tmp_user = $assignment->isTmp_user();
            $this->view->refuse_form = $refuse_form;
            $this->view->metadata = $paper->getDatasetsFromEnrichment();
        }
    }

    /**
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_User_Invitation $invitation
     * @param Episciences_User_Assignment $assignment
     * @return array
     */

    private function checkAndProcessLinkedInvitation(Zend_Controller_Request_Http $request, Episciences_User_Invitation $invitation, Episciences_User_Assignment $assignment): array
    {

        if (
            !Episciences_Auth::isLogged() ||
            $invitation->hasExpired() ||
            $invitation->isAnswered() ||
            $invitation->isCancelled() ||
            Episciences_Auth::getUid() === $assignment->getUid()
        ) {
            return [];
        }

        if ($assignment->getFrom_uid()) { // linked to
            return ['isAlreadyLinked' => true];
        }

        $invitationId = $invitation->getId();
        $session = $this->getSession();

        // Check whether this invitation has already been marked as "pre-linked" for this account
        $isPreLinked = $session->linkedInvitationIds[$invitationId]['isPreLinked'] ?? false;

        try {
            $fromUser = $assignment->resolveFromUser();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return ['isPreLinked' => $isPreLinked];
        }

        if ($fromUser->getEmail() === Episciences_Auth::getEmail()) { //Email matches the logged user; automatic linking
            $isAlreadyLinked = $this->linkToLoggedAccount($assignment);
            return ['isAlreadyLinked' => $isAlreadyLinked];
        }

        // prepare view data
        $this->view->fromScreeName = $fromUser->getScreenName();
        $this->view->fromEmail = $fromUser->getEmail();
        // First visit: marked as "pre-linked"; pending confirmation
        if (!$isPreLinked) {
            $session->linkedInvitationIds[$invitationId] = ['isPreLinked' => true];

            return [
                'isPreLinked' => true,
                'decision' => null
            ];
        }

        // Next step: processing the user's decision
        $decision = $this->processLinkDecision($request, $assignment, $invitationId, $session);

        return [
            'isPreLinked' => $isPreLinked,
            'decision' => $decision,
        ];

    }

    /**
     * Handles the user's decision (accept or reject the link)
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_User_Assignment $assignment
     * @param int $invitationId
     * @param Zend_Session_Namespace $session
     * @return string|null
     */


    private function processLinkDecision(
        Zend_Controller_Request_Http $request,
        Episciences_User_Assignment  $assignment,
        int                          $invitationId,
        Zend_Session_Namespace       $session
    ): ?string
    {
        $post = $request->getPost();

        if (empty($post['linkInvitation'])) {
            return null;
        }

        $decision = $post['linkInvitation'];

        if ($decision === 'acceptToLink') {
            $this->linkToLoggedAccount($assignment);

            $this->_helper->FlashMessenger
                ->setNamespace(Ccsd_View_Helper_Message::MSG_INFO)
                ->addMessage($this->view->translate("L'invitation a été correctement associée à votre compte."));
        } elseif ($decision === 'declineToLink') {
            $this->redirect($this->view->url(['controller' => 'paper', 'action' => 'ratings']));
        }

        // Cleanup: The invitation has been processed (accepted or declined)
        unset($session->linkedInvitationIds[$invitationId]);
        return $decision;
    }

    /**
     * check if he has already been invited by this account
     * @param Episciences_Paper $paper
     * @return array
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     */

    private function checkIsAlreadyInvited(Episciences_Paper $paper): array
    {

        $result ['isAlreadyInvited'] = false;

        $reviewers = $paper->getReviewers();
        $isReviewer = isset($reviewers[Episciences_Auth::getUid()]);

        if ($isReviewer) {
            $ratingUrlUrl = $this->view->url([
                'controller' => 'paper',
                'action' => 'rating',
                'id' => $paper->getDocid()
            ]);

            $result ['isAlreadyInvited'] = true;
            $result ['url'] = $ratingUrlUrl;
            $result['isReviewer'] = true;

            return $result;
        }

        // is invitations sent to the logged-in account
        $pendingInvitation = null;
        $paperInvitations = $paper->getInvitations([Episciences_User_Assignment::STATUS_PENDING], true)[Episciences_User_Assignment::STATUS_PENDING];

        foreach ($paperInvitations as $arrayInvitation) {

            if ((int)$arrayInvitation['UID'] === Episciences_Auth::getUid()) {

                /** @var Episciences_User_Invitation $pendingInvitation */
                $pendingInvitation = Episciences_User_InvitationsManager::find(['ID' => $arrayInvitation['INVITATION_ID']]);
                break;
            }
        }

        if ($pendingInvitation && $pendingInvitation->getId()) {
            $invitationUrl = $this->view->url([
                'controller' => 'reviewer',
                'action' => 'invitation',
                'id' => $pendingInvitation->getId(),
                'lang' => Episciences_Auth::getLangueid()
            ]);

            $result ['isAlreadyInvited'] = true;
            $result ['url'] = $invitationUrl;
        }

        return $result;

    }

    private function linkToLoggedAccount(Episciences_User_Assignment $assignment): bool
    {

        $assignment->setFrom_uid($assignment->getUid()); // The UID of the account to which the invitation is sent.
        $assignment->setUid(Episciences_Auth::getUid()); // link the assignment to the connected account
        $assignment->setTmp_user(0);
        $result = false;
        try {
            $result = $assignment->save();
        } catch (Zend_Db_Adapter_Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return $result;
    }

}
