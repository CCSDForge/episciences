<?php

use Episciences\Reviewer\InvitationToken;
use Episciences\Reviewer\InvitedAccountState;
use Episciences\Security\AccessLog;
use Episciences\User\AccountActivationMailer;
use Episciences\User\ActivationLinkOutcome;
use Episciences\User\EmailPolicy;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\TokenPurpose;
use Episciences\User\UserNotFoundException;

require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

class ReviewerController extends PaperDefaultController
{
    /**
     * Posted by the "send me a new activation link" button
     */
    private const SEND_ACTIVATION_PARAM = 'sendactivation';

    /**
     * Seconds before another activation link can be sent from the invitation page
     */
    private const ACTIVATION_RESEND_DELAY = 300;

    /**
     * Fields of the account form accepted when creating an account from an invitation
     */
    private const ACCOUNT_CREATION_FIELDS = [
        'USERNAME', 'PASSWORD', 'FIRSTNAME', 'LASTNAME', 'EMAIL', 'SCREEN_NAME', 'ORCID',
        'AFFILIATIONS', 'SOCIAL_MEDIAS', 'WEB_SITES', 'BIOGRAPHY', 'LANGUEID',
    ];

    /**
     * Set on the return from the login page, when the invitee clicked "accept" while logged out.
     * It only displays a message: the acceptance itself always takes a POST with the CSRF token.
     */
    private const CONFIRM_ACCEPTANCE_PARAM = 'confirm-acceptance';

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

        if (!$assignment) {
            $this->view->errors = array("Cette invitation n'existe pas !");
            return;
        }

        if (!Episciences_Auth::isLogged() && $assignment->isTmp_user()) {
            $tmpUser = Episciences_TmpUsersManager::findById($assignment->getUid()) ?: null;
        }

        // nothing about the invitation or the paper is displayed before this check
        if (!$this->isInvitationRequestAuthorized($request, $invitation, $assignment, $tmpUser)) {
            return;
        }

        // every answer form of the page (accept, decline, account creation, link) posts this token
        $this->view->csrfToken = Episciences_Csrf_Helper::getSessionToken();

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
            $result = $this->checkAndProcessLinkedInvitation($request, $invitation, $assignment, $paper);
        }

        if ($result['isAuthor'] ?? false) {
            $this->view->errors = "Cet article ne peut pas être relu par son auteur";
            return;
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

            if ($isAcceptedToLInk) {
                $this->view->displayLinkedInvitationForm = false;
            } elseif ($isDeclinedToLInk) {
                $this->view->errors = "Cette invitation ne vous est pas destinée";
                $this->view->displayLinkedInvitationForm = false;
                return;
            } else {
                $this->view->errors = "Cette invitation n'est pas liée au compte en cours";
                $this->view->displayLinkedInvitationForm = true;
                return;
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

    /**
     * Whether the request may see and answer the invitation; sets the error to display otherwise.
     *
     * - a status row (accepted, declined, cancelled) is never answered through its own id;
     * - an invitation with a link token requires that token, unless the invitee is logged in;
     * - a legacy invitation (no link token) keeps its former check: md5 of the e-mail for a reviewer without an account.
     */
    private function isInvitationRequestAuthorized(
        Zend_Controller_Request_Http $request,
        Episciences_User_Invitation  $invitation,
        Episciences_User_Assignment  $assignment,
        ?Episciences_User_Tmp        $tmpUser
    ): bool
    {
        $isInvitee = Episciences_Auth::isLogged() &&
            !$assignment->isTmp_user() &&
            (int)$assignment->getUid() === (int)Episciences_Auth::getUid();

        if (!$invitation->isPendingRow()) {
            $this->view->errors = $isInvitee ? 'Vous avez déjà répondu à cette invitation.' : "Il apparaît qu'une réponse a déjà été transmise.";
            return false;
        }

        $invalidLink = 'Lien non valide; le jeton transmis dans la requête est erroné.';
        $rowSecret = $invitation->getToken();

        if ($rowSecret === null) { // legacy invitation
            if (
                !Episciences_Auth::isLogged() &&
                $assignment->isTmp_user() &&
                (!$tmpUser || md5((string)$tmpUser->getEmail()) !== $request->getParam('tmp'))
            ) {
                AccessLog::denied(AccessLog::TOKEN_INVALID, 'legacy_invitation_link', ['invitation_id' => (int)$invitation->getId()]);
                $this->view->errors = $invalidLink;
                return false;
            }

            return true;
        }

        if ($isInvitee) {
            return true;
        }

        try {
            $isValid = $this->getInvitationToken()->isValid((int)$invitation->getId(), $rowSecret, $request->getParam(InvitationToken::URL_PARAM));
        } catch (LogicException $e) {
            // Configuration error (missing signing secret): not a forged token, kept out of the security log
            trigger_error($e->getMessage(), E_USER_WARNING);
            $this->view->errors = $invalidLink;
            return false;
        }

        if (!$isValid || ($assignment->isTmp_user() && !Episciences_Auth::isLogged() && !$tmpUser)) {
            AccessLog::denied(
                AccessLog::TOKEN_INVALID,
                $isValid ? 'unknown_temporary_reviewer' : 'invitation_token',
                ['invitation_id' => (int)$invitation->getId()]
            );
            $this->view->errors = $invalidLink;
            return false;
        }

        return true;
    }

    protected function getInvitationToken(): InvitationToken
    {
        return new InvitationToken();
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
     * @param array<string, mixed> $data the posted answer (CSRF token already checked)
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    protected function saveanswer(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, array $data): void
    {
        // both answers end with a redirect, which ends the request: the message is added before it
        if (array_key_exists('submitaccept', $data)) {

            // accepted invitation (not yet when the invitee has to log in first)
            if ($this->accept($oInvitation, $assignment, $paper, $data)) {
                $this->addAnswerSavedMessage();
                $this->_helper->redirector->gotoUrl($this->_helper->url('rating', 'paper', null, ['id' => $paper->getDocid()]));
            }

        } elseif (array_key_exists('submitrefuse', $data)) {

            // declined invitation
            $this->decline($oInvitation, $assignment, $paper, $data);
            $this->addAnswerSavedMessage();
            $this->redirect('/');

        }
    }

    private function addAnswerSavedMessage(): void
    {
        $this->_helper->FlashMessenger
            ->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)
            ->addMessage($this->view->translate("Votre réponse a bien été enregistrée."));
    }

    /**
     * The request token was refused (expired session, page left open too long): nothing was stored.
     */
    private function addSessionExpiredMessage(): void
    {
        $this->_helper->FlashMessenger
            ->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
            ->addMessage($this->view->translate("Votre session a expiré ou la page est restée ouverte trop longtemps : par sécurité, votre réponse n'a pas été enregistrée. Merci de recommencer."));
    }

    /**
     * @param Episciences_User_Invitation $oInvitation
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_Paper $paper
     * @param array<string, mixed> $data the posted answer, with the account form fields for a reviewer without an account
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @return bool false when the invitee is sent to the login page first: nothing is stored yet
     */
    private function accept(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, array $data): bool
    {
        // update user permissions
        if ($assignment->isTmp_user()) {
            $user = $this->createNewReviewerWithoutAccountProcessing($data);
        } else {
            if (!Episciences_Auth::isLogged()) {
                // user needs to login; once logged in, the invitee confirms the acceptance with the (POST) form
                $this->redirect($this->invitationLoginUrl($oInvitation, true));
                return false;
            }

            $user = $this->createNewReviewerWithExistingAccountProcessing($assignment->getUid());
        }

        // apply all the acceptance side effects (answer, status, assignment, alias,
        // rating report, log, paper status and e-mails) — shared with the editor
        // "accept on behalf" flow in AdministratepaperController.
        $this->performReviewerInvitationAcceptance($oInvitation, $assignment, $paper, $user);

        return true;
    }

    /**
     * The account created from an invitation uses the invited address: it is displayed read-only, with the way to use another one.
     */
    private function lockInvitedEmail(Zend_Form $form): void
    {
        $email = $form->getElement('EMAIL');
        if (!$email) {
            return;
        }

        $email->setAttrib('readonly', 'readonly');
        $email->setAttrib('aria-describedby', 'EMAIL-description');
        $email->setDescription(sprintf(
            $this->view->translate("C'est l'adresse à laquelle l'invitation a été envoyée. Pour utiliser une autre adresse, %scréez d'abord votre compte%s, activez-le, puis rouvrez le lien de l'invitation."),
            '<a href="' . $this->view->escape($this->view->url(['controller' => 'user', 'action' => 'create'], null, true)) . '" target="_blank" rel="noopener">',
            '<span class="sr-only"> ' . $this->view->escape($this->view->translate('(nouvel onglet)')) . '</span></a>'
        ));

        $description = $email->getDecorator('Description');
        if ($description) {
            $description->setOption('escape', false);
            $description->setOption('id', 'EMAIL-description');
        }
    }

    /**
     * Which account, if any, already uses the address a reviewer without an account was invited at.
     *
     */
    protected function invitedAccountState(string $email): InvitedAccountState
    {
        $mapper = new Ccsd_User_Models_UserMapper();
        $email = EmailPolicy::normalize($email);
        $uids = $email === '' ? [] : $mapper->findUidsByEmail($email);

        if ($uids === []) {
            return InvitedAccountState::None;
        }

        return $mapper->findMostRecentValidByEmail($email) !== null
            ? InvitedAccountState::Active
            : InvitedAccountState::Inactive;
    }

    /**
     * "Send me a new activation link": only to the invited address, for an account not activated yet, whatever the
     * application it was created in. Nothing else of the page changes.
     */
    private function sendActivationLink(Zend_Controller_Request_Http $request, string $email, InvitedAccountState $state): void
    {
        if (!Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->addSessionExpiredMessage();
            return;
        }

        if ($state !== InvitedAccountState::Inactive) {
            return;
        }

        [$namespace, $message] = match ($this->sendActivationEmailTo($email)) {
            ActivationLinkOutcome::Sent => [
                Ccsd_View_Helper_Message::MSG_SUCCESS,
                sprintf(
                    $this->view->translate("Un nouveau lien d'activation a été envoyé à %s. Activez votre compte, connectez-vous, puis rouvrez le lien de l'invitation."),
                    $email
                ),
            ],
            ActivationLinkOutcome::AlreadySent => [
                Ccsd_View_Helper_Message::MSG_INFO,
                sprintf(
                    $this->view->translate("Un lien d'activation a déjà été envoyé à %s il y a moins de %d minutes. Vérifiez votre messagerie, y compris les courriers indésirables, avant d'en demander un autre."),
                    $email,
                    intdiv(self::ACTIVATION_RESEND_DELAY, 60)
                ),
            ],
            ActivationLinkOutcome::Failed => [
                Ccsd_View_Helper_Message::MSG_ERROR,
                $this->view->translate("Le lien d'activation n'a pas pu être envoyé. Merci de réessayer dans quelques minutes."),
            ],
        };

        $this->_helper->FlashMessenger->setNamespace($namespace)->addMessage($message);
    }

    /**
     * Sends a new activation link to the account not activated yet that uses this address. The link goes to the
     * address of the account, which is the invited one: receiving the invitation proves this address.
     */
    protected function sendActivationEmailTo(string $email): ActivationLinkOutcome
    {
        try {
            $uid = (new Ccsd_User_Models_UserMapper())->findMostRecentInactiveUidByEmail(EmailPolicy::normalize($email));
            if ($uid === null) { // activated in the meantime
                return ActivationLinkOutcome::Failed;
            }

            $tokens = new AccountTokenRepository();
            if ($tokens->issuedWithin($uid, TokenPurpose::ACCOUNT_ACTIVATION, self::ACTIVATION_RESEND_DELAY)) {
                return ActivationLinkOutcome::AlreadySent;
            }

            // the account may have no Episciences data: its address and names are read from the accounts table
            $user = new Episciences_User();
            $user->findWithCAS($uid);

            $isSent = (new AccountActivationMailer(
                $tokens,
                fn(array $params): string => $this->view->url($params, null, true)
            ))->send($user);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return ActivationLinkOutcome::Failed;
        }

        return $isSent ? ActivationLinkOutcome::Sent : ActivationLinkOutcome::Failed;
    }

    /**
     * Login page that comes back to the invitation.
     *
     * @param bool $confirmAcceptance display only: once back, the invitee is asked to confirm the acceptance
     */
    private function invitationLoginUrl(Episciences_User_Invitation $invitation, bool $confirmAcceptance): string
    {
        $params = [
            'controller' => 'user',
            'action' => 'login',
            'forward-controller' => 'reviewer',
            'forward-action' => 'invitation',
        ] + $this->invitationLinkParams($invitation);

        if ($confirmAcceptance) {
            $params[self::CONFIRM_ACCEPTANCE_PARAM] = '1';
        }

        // reset: no parameter of the current page (e.g. a former confirm-acceptance) is carried over
        return $this->view->url($params, null, true);
    }

    /**
     * The invitation page, as opened from the e-mail link
     */
    private function invitationUrl(Episciences_User_Invitation $invitation): string
    {
        return $this->view->url(['controller' => 'reviewer', 'action' => 'invitation'] + $this->invitationLinkParams($invitation), null, true);
    }

    /**
     * Parameters of the invitation link: its secret is needed again once logged in (the account may not be the
     * invited one)
     *
     * @return array<string, int|string>
     */
    private function invitationLinkParams(Episciences_User_Invitation $invitation): array
    {
        $params = ['id' => (int)$invitation->getId()];

        foreach ([InvitationToken::URL_PARAM, 'tmp'] as $linkParam) {
            $linkValue = $this->getRequest()->getParam($linkParam);
            if (is_string($linkValue) && $linkValue !== '') {
                $params[$linkParam] = $linkValue;
            }
        }

        return $params;
    }

    /**
     * @param Episciences_User_Invitation $oInvitation
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_Paper $paper
     * @param array<string, mixed> $data the posted answer, with the filtered values of the refusal form
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function decline(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, array $data): void
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
     * Keeps only the fields of the account form (keys are matched case-insensitively)
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function filterAccountCreationData(array $data): array
    {
        $allowedFields = array_flip(self::ACCOUNT_CREATION_FIELDS);

        return array_filter(
            $data,
            static fn($key): bool => isset($allowedFields[strtoupper((string)$key)]),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     *  create new user (don't have an account yet)
     * @param array<string, mixed> $data
     * @return Episciences_Reviewer
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    private function createNewReviewerWithoutAccountProcessing(array $data): Episciences_Reviewer
    {
        // Only fields of the account form may reach the model: never let request data set UID, VALID, etc.
        $data = self::filterAccountCreationData($data);

        $user = new Episciences_Reviewer($data);
        $user->setTime_registered();
        $user->setValid(1);

        if ($user->getUid()) { // must always be an INSERT
            throw new RuntimeException('Unexpected UID while creating a new reviewer account');
        }

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
            $invitedAccountState = null;

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
                $this->lockInvitedEmail($user_form);
                $this->view->user_form = $user_form;

                // an account already uses the invited address: accepting means signing in with it, not creating one
                $invitedAccountState = $this->invitedAccountState((string) $tmpUser->getEmail());
                $this->view->invitedAccountState = $invitedAccountState;
            }

            // tells a logged-out invitee that accepting needs an account, before they choose
            if (!Episciences_Auth::isLogged()) {
                $this->view->loginUrl = $this->invitationLoginUrl($invitation, false);

                // accepting with the active account using the invited address: once back, the acceptance is confirmed
                if ($invitedAccountState === InvitedAccountState::Active) {
                    $this->view->acceptLoginUrl = $this->invitationLoginUrl($invitation, true);
                }
            }

            // an answer is only ever read from the POST body: a GET or a URL path segment changes nothing
            $post = $request->isPost() ? $request->getPost() : [];

            // the account is created valid, without an activation e-mail: receiving the invitation proves this address only
            if ($tmpUser && $post !== []) {
                $post['EMAIL'] = $tmpUser->getEmail();
            }

            if ($tmpUser && array_key_exists(self::SEND_ACTIVATION_PARAM, $post)) {
                $this->sendActivationLink($request, (string) $tmpUser->getEmail(), $invitedAccountState);
                // displayed again with a GET: refreshing the page does not send another request
                $this->redirect($this->invitationUrl($invitation));
                return;
            }

            $accepted = array_key_exists('submitaccept', $post);
            $refused = array_key_exists('submitrefuse', $post);

            // the forms post one answer only: both at once would store an acceptance validated as a refusal
            if ($accepted && $refused) {
                $accepted = $refused = false;
            }

            // nothing to confirm while a refusal is being written
            if (
                !$accepted &&
                !$refused &&
                Episciences_Auth::isLogged() &&
                $request->getParam(self::CONFIRM_ACCEPTANCE_PARAM) === '1'
            ) {
                $this->view->confirmAcceptance = true;
            }

            if (($accepted || $refused) && !Episciences_Csrf_Helper::validateRequestToken($request)) {
                $this->addSessionExpiredMessage();
                $this->redisplayTypedAnswer($post, $refused, $refuse_form, $user_form ?? null);
                $accepted = $refused = false;
            }

            // the refusal form is validated and filtered (tags stripped, trimmed) before anything is stored
            $refusedFormIsValid = $refused && $refuse_form->isValid($post);

            if ($accepted || $refused) {

                if (
                    $refusedFormIsValid ||
                    (
                        $accepted &&
                        (
                            !$assignment->isTmp_user() ||
                            (isset($user_form) && $user_form->isValid($post))
                        )
                    )
                ) {

                    $data = $post;

                    if ($refusedFormIsValid) {
                        $data = array_merge($data, $refuse_form->getValues());
                    }

                    $this->saveanswer($invitation, $assignment, $paper, $data);


                } elseif ($refused) {
                    // reopen the refusal form: it now displays its own validation errors
                    Episciences_ReviewersManager::describeRefusalFormErrors($refuse_form);
                    $this->view->invalid_refuse_form = true;
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
     * Displays the answer form again with what was typed, after a refused request token.
     * The form is not validated, so no field error is displayed.
     *
     * @param array<string, mixed> $post
     */
    private function redisplayTypedAnswer(array $post, bool $refused, Zend_Form $refuseForm, ?Zend_Form $userForm): void
    {
        // the token field keeps the current session token, not the refused one
        unset($post['csrf_token']);

        if ($refused) {
            $refuseForm->populate($post);
            $this->view->invalid_refuse_form = true;
        } elseif ($userForm !== null) {
            $userForm->populate($post);
            $this->view->invalid_form = true;
        }
    }

    /**
     * Links the invitation to the logged-in account when it was sent to another one.
     *
     * Deliberate exception to "a GET changes nothing": when the invited address is the one of the
     * logged-in account, the link is made as soon as the page is displayed, without confirmation
     * (#1009). Any other account must hold the link token to get here, then confirm with a POST
     * carrying the CSRF token (processLinkDecision()).
     *
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_User_Invitation $invitation
     * @param Episciences_User_Assignment $assignment
     * @param Episciences_Paper $paper
     * @return array<string, mixed>
     */

    private function checkAndProcessLinkedInvitation(Zend_Controller_Request_Http $request, Episciences_User_Invitation $invitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper): array
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

        // an author can never take over a review invitation of their own paper
        $loggedUid = (int)Episciences_Auth::getUid();
        if ((int)$paper->getUid() === $loggedUid || $paper->isCoAuthorByUid($loggedUid)) {
            return ['isAuthor' => true];
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
            $isAlreadyLinked = $this->linkToLoggedAccount($assignment, $paper, $fromUser, (int)$invitationId, true);
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
        $decision = $this->processLinkDecision($request, $assignment, (int)$invitationId, $session, $paper, $fromUser);

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
     * @param Episciences_Paper $paper
     * @param Episciences_User $fromUser the invited user
     * @return string|null
     */


    private function processLinkDecision(
        Zend_Controller_Request_Http $request,
        Episciences_User_Assignment  $assignment,
        int                          $invitationId,
        Zend_Session_Namespace       $session,
        Episciences_Paper            $paper,
        Episciences_User             $fromUser
    ): ?string
    {
        $post = $request->getPost();

        if (empty($post['linkInvitation'])) {
            return null;
        }

        if (!Episciences_Csrf_Helper::validateRequestToken($request)) {
            // nothing changes, the pending confirmation is kept: the form is displayed again
            $this->addSessionExpiredMessage();
            return null;
        }

        $decision = $post['linkInvitation'];

        // Cleanup: The invitation has been processed (accepted or declined).
        // Done before the redirect below, which exits and would skip any code after it.
        unset($session->linkedInvitationIds[$invitationId]);

        if ($decision === 'acceptToLink') {
            $this->linkToLoggedAccount($assignment, $paper, $fromUser, $invitationId, false);

            $this->_helper->FlashMessenger
                ->setNamespace(Ccsd_View_Helper_Message::MSG_INFO)
                ->addMessage($this->view->translate("L'invitation a été correctement associée à votre compte."));
        } elseif ($decision === 'declineToLink') {
            $this->redirect($this->view->url(['controller' => 'paper', 'action' => 'ratings']));
        }

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

    /**
     * @param Episciences_User $fromUser the invited user, resolved before the assignment is moved to the logged account
     * @param bool $automatic true when the logged account holds the invited e-mail address
     */
    private function linkToLoggedAccount(
        Episciences_User_Assignment $assignment,
        Episciences_Paper           $paper,
        Episciences_User            $fromUser,
        int                         $invitationId,
        bool                        $automatic
    ): bool
    {
        $invitedUid = (int)$assignment->getUid();
        $invitedIsTmp = (bool)$assignment->isTmp_user();

        $assignment->setFrom_uid($assignment->getUid()); // The UID of the account to which the invitation is sent.
        $assignment->setUid(Episciences_Auth::getUid()); // link the assignment to the connected account
        $assignment->setTmp_user(0);
        $result = false;
        try {
            $result = $assignment->save();
        } catch (Zend_Db_Adapter_Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        if ($result) {
            $this->logLinkedInvitation($assignment, $paper, $fromUser, $invitationId, $invitedUid, $invitedIsTmp, $automatic);
        }

        return (bool)$result;
    }

    private function logLinkedInvitation(
        Episciences_User_Assignment $assignment,
        Episciences_Paper           $paper,
        Episciences_User            $fromUser,
        int                         $invitationId,
        int                         $invitedUid,
        bool                        $invitedIsTmp,
        bool                        $automatic
    ): void
    {
        $loggedUid = (int)Episciences_Auth::getUid();
        $linkedTo = [
            'uid' => $loggedUid,
            'fullname' => (string)Episciences_Auth::getFullName(),
            'email' => (string)Episciences_Auth::getEmail(),
        ];

        try {
            $paper->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_LINKED, $loggedUid, [
                'invitation_id' => $invitationId,
                'assignment_id' => $assignment->getId(),
                // displayed in the paper history and the activity timeline
                'user' => $linkedTo + ['SCREEN_NAME' => Episciences_Auth::getScreenName()],
                'invited' => [
                    'uid' => $invitedUid,
                    'fullname' => (string)$fromUser->getFullName(),
                    'email' => (string)$fromUser->getEmail(),
                    'tmp' => $invitedIsTmp,
                ],
                'linked_to' => $linkedTo,
                'automatic' => $automatic,
            ]);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
    }

}
