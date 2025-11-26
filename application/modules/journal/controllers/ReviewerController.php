<?php
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

        $doRating = true;

        // fetch assignment
        $assignmentId = $invitation->getAid();
        $assignment = Episciences_User_AssignmentsManager::findById($assignmentId);

        if (
            Episciences_Auth::isLogged() &&
            Episciences_Auth::getUid() !== $assignment->getUid()
        ) {

            $result = $this->checkAndProcessLinkedInvitation($request, $invitation, $assignment, $doRating);

        } elseif ($assignment->isTmp_user()) {

            $tmpUser = Episciences_TmpUsersManager::findById($assignment->getUid());

            if (!$tmpUser || md5($tmpUser->getEmail()) !== $request->getParam('tmp')) {
                $doRating = false;
            }

        }

        if (!$doRating) {

            $message = "Cette invitation ne vous est pas destinée !";

            if (isset($result['isPreLinked']) && $result['isPreLinked']) {

                if (isset($result['decision']) && $result['decision'] === "declineToLink") {
                    $this->view->displayLinkedInvitationForm = false;
                } else {
                    $message = "Cette invitation n'est pas liée au compte en cours !";
                    $this->view->displayLinkedInvitationForm = true;
                }

            }

            $message = $this->view->translate($message);

            $this->view->errors = array($message);

            return;
        }


        // fetch reviewer answer (if there is one)
        $invitation->loadAnswer();

        // INVITATION
        $this->view->invitation = $invitation;

        $this->view->rating_deadline = $assignment->getDeadline();


        // ARTICLE A RELIRE *******************************************
        $paper = Episciences_PapersManager::get($assignment->getItemid());
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
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'ratings']));

        } elseif (array_key_exists('submitrefuse', $data)) {

            // declined invitation
            $this->decline($oInvitation, $assignment, $paper, $data);
            $this->redirect($this->url(['controller' => 'index']));

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

        // save invitation answer
        $oInvitationAnswer = new Episciences_User_InvitationAnswer();
        $oInvitationAnswer->setId($oInvitation->getId());
        $oInvitationAnswer->setAnswer(Episciences_User_InvitationAnswer::ANSWER_YES);
        $oInvitationAnswer->save();

        // update invitation status
        $oInvitation->setStatus($oInvitation::STATUS_ACCEPTED);
        $oInvitation->save();

        // paper assignment
        /** @var Episciences_User_Assignment $newAssignment */
        $newAssignment = $user->assign($assignment->getItemid(), array('deadline' => $assignment->getDeadline()))[0];
        $newAssignment->setInvitation_id($oInvitation->getId());
        $newAssignment->save();
        $itemId = $assignment->getItemid();

        // if needed, create an alias
        if ($paper->getPaperid() !== $itemId) { // new version
            if ($user->hasAlias($itemId, false)) {// already has an alias for at least one version
                $user->createAlias($itemId, $user->getAlias($itemId, false));
            } else {
                $user->createAlias($itemId);
            }
        } elseif (!$user->hasAlias($itemId)) { // first version
            $user->createAlias($itemId);
        }

        $uid = $user->getUid();

        // create rating report
        $this->createRatingReport($paper, $uid);

        // log reviewer assignment to paper
        $paper->log(
            Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_ACCEPTED,
            $uid,
            [
                'invitation_answer_id' => $oInvitationAnswer->getId(),
                'invitation_id' => $oInvitation->getId(),
                'assignment_id' => $newAssignment->getId(),
                'user' => array_merge($user->toArray(), ['alias' => $user->getAlias($assignment->getItemid())]),
            ]);

        // update paper status
        $paper->ratingRefreshPaperStatus();

        $this->emailSendingProcessing($user, $paper, $newAssignment);
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
     * send e-mails for reviewer and editorial committee
     * @param Episciences_User $user
     * @param Episciences_paper $paper
     * @param Episciences_User_Assignment $assignment
     * @param string $reviewerAnswer
     * @param array $data
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    private function emailSendingProcessing(Episciences_User $user, Episciences_paper $paper, Episciences_User_Assignment $assignment, string $reviewerAnswer = Episciences_User_InvitationAnswer::ANSWER_YES, array $data = []): void
    {
        $locale = $user->getLangueid(true);

        $docId = $paper->getDocid();
        $reviewerUid = $user->getUid();

        $ratingUrl = $this->view->url(['controller' => 'paper', 'action' => 'rating', 'id' => $docId]);
        $ratingUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $ratingUrl;

        $adminPaperUrl = $this->view->url(['controller' => 'administratepaper', 'action' => 'view', 'id' => $docId]);
        $adminPaperUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $adminPaperUrl;

        $reviewerTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_ACCEPTATION_REVIEWER_COPY;
        $editorialCommitteeTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_ACCEPTATION_EDITOR_COPY;

        $commonTags = [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId,
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata()
        ];


        $editorialCommitteeTags = [
            Episciences_Mail_Tags::TAG_REVIEWER_FULLNAME => $user->getScreenName(),
            Episciences_Mail_Tags::TAG_REVIEWER_SCREEN_NAME => $user->getScreenName(),
            Episciences_Mail_Tags::TAG_PAPER_URL => $adminPaperUrl
        ];

        $reviewerTags = [Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true)];

        if ($reviewerAnswer === Episciences_User_InvitationAnswer::ANSWER_NO) { // declined

            $reviewerTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REFUSAL_REVIEWER_COPY;
            $editorialCommitteeTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REFUSAL_EDITOR_COPY;

            if (isset($data['suggestreviewer'])) {
                $commonTags[Episciences_Mail_Tags::TAG_REVIEWER_SUGGESTION] = $data['suggestreviewer'];
            }

            if (isset($data['comment'])) {
                $commonTags[Episciences_Mail_Tags::TAG_REFUSAL_REASON] = $data['comment'];

            }

        } else {

            $reviewerTags = array_merge(
                $reviewerTags, [
                Episciences_Mail_Tags::TAG_PAPER_URL => $ratingUrl,
                Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $locale),
                Episciences_Mail_Tags::TAG_RATING_DEADLINE => $this->view->Date($assignment->getDeadline(), $locale)
            ]);

        }

        $reviewerTags = array_merge($commonTags, $reviewerTags);

        $editorialCommitteeTags = array_merge($commonTags, $editorialCommitteeTags);

        Episciences_Mail_Send::sendMailFromReview($user, $reviewerTemplateType, $reviewerTags, $paper);

        //  > editors + admins + secretaries + chief editors notifications
        $recipients = $paper->getEditors(true, true);
        Episciences_Review::checkReviewNotifications($recipients);

        Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);

        $CC = $paper->extractCCRecipients($recipients);

        if (empty($recipients)) {
            $recipients = $CC;
            $CC = [];
        }

        /** @var Episciences_User $recipient */
        foreach ($recipients as $recipient) {

            if ($reviewerUid === $recipient->getUid()) { // has already been notified as a reviewer
                continue;
            }

            $locale = $recipient->getLangueid(true);

            if ($reviewerAnswer === Episciences_User_InvitationAnswer::ANSWER_YES) {
                $editorialCommitteeTags [Episciences_Mail_Tags::TAG_RATING_DEADLINE] = $this->view->Date($assignment->getDeadline(), $locale);
            }

            $editorialCommitteeTags += [
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $locale)
            ];

            Episciences_Mail_Send::sendMailFromReview($recipient, $editorialCommitteeTemplateType, $editorialCommitteeTags,
                $paper, null, [], false, $CC, null
            );
            //reset $CC
            $CC = [];

        }

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
     * @param bool $doRating
     * @return array
     * @throws JsonException
     * @throws Zend_Controller_Exception
     * @throws Zend_Controller_Request_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */

    private function checkAndProcessLinkedInvitation(Zend_Controller_Request_Http $request, Episciences_User_Invitation $invitation, Episciences_User_Assignment $assignment, bool &$doRating): array
    {

        $invitationId = $invitation->getId();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

        // l'invitation en cours n'est pas encore attachée au compte connecté
        $isPreLinked = $session->linkedInvitationIds[$invitationId]['isPreLinked'] ?? false;
        $decision = null;
        $doRating = false;
        $fromUid = $assignment->getUid();

        $isAssignedToTmpUser = (bool)$assignment->isTmp_user();

        if ($isAssignedToTmpUser) {
            // User to which the invitation is sent
            $fromUser = Episciences_TmpUsersManager::findById($fromUid);
        } else {
            $fromUser = new Episciences_User();
            $fromUser->findWithCAS($fromUid);
        }

        $this->view->fromScreeName = $fromUser->getScreenName();
        $this->view->fromEmail = $fromUser->getEmail();

        if (!$isPreLinked) { // Add invitation ID
            $session->linkedInvitationIds[$invitationId] = ['isPreLinked' => true];
            $isPreLinked = true;
        } else { // already attached to the logged-in account

            $post = $request->getPost();

            if (isset($post['linkInvitation'])) {
                if ($post['linkInvitation'] === 'acceptToLink') {

                    // The UID of the account to which the invitation is sent.
                    $assignment->setFrom_uid($fromUid);
                    // link the assignment to the connected account
                    $assignment->setUid(Episciences_Auth::getUid());

                    if ($isAssignedToTmpUser) {
                        $assignment->setTmp_user(0);
                    }

                    $assignment->save();
                    $doRating = true;

                    $infoMessage = $this->view->translate("L’invitation a été correctement associée à votre compte.");
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_INFO)->addMessage($infoMessage);

                } elseif ($post['linkInvitation'] === 'declineToLink') {
                    $decision = 'declineToLink';
                }
                // Il serait alors possible de proposer à nouveau que l'invitation soit liée au compte connecté.
                unset($session->linkedInvitationIds[$invitationId]);
            }
        }

        return ['isPreLinked' => $isPreLinked, 'decision' => $decision];
    }
}
