<?php

/**
 * Service class for handling author-editor communication
 *
 * Extracted from PaperController to follow separation of concerns principle.
 * Handles all business logic related to author-editor messaging.
 * Used by both PaperController (author view) and AdministratepaperController (editor view).
 */
class Episciences_Paper_AuthorEditorCommunicationService
{
    private readonly Episciences_Paper $paper;
    private readonly Episciences_Review $review;

    public const CONTROLLER_PAPER = 'paper';
    public const CONTROLLER_ADMINISTRATEPAPER = 'administratepaper';

    // Translation keys for messages
    public const MSG_SUCCESS_AUTHOR_SENT = "Votre message a bien été envoyé aux rédacteurs.";
    public const MSG_SUCCESS_AUTHOR_REPLY = "Votre réponse a bien été envoyée.";
    public const MSG_SUCCESS_EDITOR_SENT = "Votre message a bien été envoyé à l'auteur.";
    public const MSG_SUCCESS_EDITOR_REPLY = "Votre réponse a bien été envoyée à l'auteur.";
    public const MSG_ERROR_SEND_FAILED = "Votre message n'a pas pu être envoyé.";
    public const MSG_ERROR_SAVE_FAILED = "Erreur lors de la sauvegarde de votre réponse.";
    public const MSG_ERROR_GENERAL = "Une erreur s'est produite lors de l'envoi du message.";
    public const MSG_ERROR_UNAUTHORIZED_AUTHOR = "Vous n'êtes pas autorisé à répondre.";
    public const MSG_ERROR_UNAUTHORIZED_EDITOR = "Vous n'êtes pas autorisé à répondre à cet auteur.";
    public const MSG_ERROR_UNAUTHORIZED_EDITOR_SEND = "Vous n'êtes pas autorisé à envoyer un message à cet auteur.";
    public const MSG_ERROR_CSRF_INVALID = "Erreur de validation du formulaire (token CSRF invalide). Veuillez réessayer.";
    public const MSG_ERROR_COMMENT_EMPTY = "Le commentaire ne peut pas être vide.";
    public const MSG_ERROR_PARENT_NOT_FOUND = "Le message parent n'a pas été trouvé.";
    public const MSG_ERROR_PARENT_INVALID_TYPE = "Le type du message parent est invalide.";
    public const MSG_ERROR_PARENT_WRONG_PAPER = "Le message parent n'appartient pas à cet article.";

    public function __construct(Episciences_Paper $paper, Episciences_Review $review, private readonly string $controllerPath = self::CONTROLLER_PAPER)
    {
        if (!in_array($controllerPath, [self::CONTROLLER_PAPER, self::CONTROLLER_ADMINISTRATEPAPER], true)) {
            throw new \InvalidArgumentException(
                "Invalid controller path '$controllerPath'. Expected one of: " .
                self::CONTROLLER_PAPER . ', ' . self::CONTROLLER_ADMINISTRATEPAPER
            );
        }
        $this->paper = $paper;
        $this->review = $review;
    }

    /**
     * Check if author can contact editors
     */
    public function canAuthorContactEditors(): bool
    {
        return (bool)$this->review->getSetting(Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS);
    }

    /**
     * Check if editor names should be disclosed to authors
     */
    public function shouldDiscloseEditorNames(): bool
    {
        return (bool)$this->review->getSetting(Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS);
    }

    /**
     * Get assigned editors for the paper
     */
    public function getAssignedEditors(): array
    {
        if (!$this->canAuthorContactEditors() && !$this->shouldDiscloseEditorNames()) {
            return [];
        }
        return $this->paper->getEditors(true, true);
    }

    /**
     * Load author-editor comments for the paper
     *
     * @return array Hierarchical comments structure
     */
    public function loadComments(): array
    {
        $comments = Episciences_CommentsManager::getList(
            $this->paper->getDocid(),
            [
                'types' => [
                    Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                    Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR
                ]
            ]
        );

        if (!empty($comments)) {
            $comments = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);
        }

        return $comments;
    }

    /**
     * Create the main form for author-editor communication
     *
     * @param string $formName Form name (affects CSRF token). Default: 'authorToEditorForm'
     * @throws Zend_Form_Exception
     */
    public function createMainForm(string $formName = 'authorToEditorForm'): Ccsd_Form
    {
        $form = Episciences_CommentsManager::getForm($formName, false, false);
        $form->setAction('/' . $this->controllerPath . '/view?id=' . $this->paper->getDocid());

        return $form;
    }

    /**
     * Create reply forms for author to respond to editor messages
     *
     * @param array $comments Hierarchical comments structure
     * @return array Reply forms indexed by PCID
     * @throws Zend_Form_Exception
     */
    public function createAuthorReplyForms(array $comments): array
    {
        $replyForms = [];
        $this->collectCommentForms(
            $comments,
            $replyForms,
            Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR,
            'author_reply_form_'
        );
        return $replyForms;
    }

    /**
     * Create reply forms for editor to respond to author messages
     *
     * @param array $comments Hierarchical comments structure
     * @return array Reply forms indexed by PCID
     * @throws Zend_Form_Exception
     */
    public function createEditorReplyForms(array $comments): array
    {
        $replyForms = [];
        $this->collectCommentForms(
            $comments,
            $replyForms,
            Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
            'editor_reply_form_'
        );
        return $replyForms;
    }

    /**
     * Collect comments of a specific type and create reply forms
     *
     * Only processes root messages and their direct replies (2-level structure).
     * Does NOT recurse into nested replies to prevent form creation for corrupted hierarchies.
     *
     * @param array $comments Flattened comment structure from processCommentsForTimeline()
     * @param int $targetType Comment type to collect
     * @throws Zend_Form_Exception
     */
    private function collectCommentForms(array $comments, array &$replyForms, int $targetType, string $formPrefix): void
    {
        foreach ($comments as $comment) {
            // Check root message
            if (isset($comment['TYPE']) && isset($comment['PCID']) && (int)$comment['TYPE'] === $targetType) {
                $pcid = $comment['PCID'];
                if (!isset($replyForms[$pcid])) {
                    $replyForms[$pcid] = $this->createReplyForm($pcid, $formPrefix);
                }
            }

            // Only process direct replies (first level), do NOT recurse further
            // This prevents form creation for corrupted nested hierarchies
            if (!empty($comment['replies'])) {
                foreach ($comment['replies'] as $reply) {
                    if (isset($reply['TYPE']) && isset($reply['PCID']) && (int)$reply['TYPE'] === $targetType) {
                        $pcid = $reply['PCID'];
                        if (!isset($replyForms[$pcid])) {
                            $replyForms[$pcid] = $this->createReplyForm($pcid, $formPrefix);
                        }
                    }
                    // Intentionally NOT recursing into $reply['replies']
                }
            }
        }
    }

    /**
     * Create a single reply form
     *
     * @param int $pcid Parent comment ID
     * @param string $formPrefix Form name prefix
     * @throws Zend_Form_Exception
     */
    private function createReplyForm(int $pcid, string $formPrefix): Ccsd_Form
    {
        $formName = $formPrefix . $pcid;
        $form = Episciences_CommentsManager::getForm($formName, false, true, $pcid);
        $form->setAction('/' . $this->controllerPath . '/view?id=' . $this->paper->getDocid());

        $form->addElement('hidden', 'reply_to_pcid', [
            'value' => $pcid,
            'decorators' => ['ViewHelper']
        ]);

        $cssFormId = 'reply-form-' . $pcid;
        $this->configureCancelButton($form, 'btn btn-default cancel-reply-form', $cssFormId);

        return $form;
    }

    /**
     * Configure the cancel button on a form
     */
    private function configureCancelButton(Ccsd_Form $form, string $cssClass, ?string $dataReplyFormId = null): void
    {
        $formActionsDecorator = $form->getDecorator('FormActions');
        if ($formActionsDecorator === null || $formActionsDecorator === false) {
            return;
        }

        if (!isset($formActionsDecorator->_cancel) || $formActionsDecorator->_cancel === null) {
            return;
        }

        $cancelButton = $formActionsDecorator->_cancel;
        $cancelButton->setAttrib('class', $cssClass);
        $cancelButton->setAttrib('type', 'button');
        if ($dataReplyFormId !== null) {
            $cancelButton->setAttrib('data-reply-form-id', $dataReplyFormId);
        }
    }

    // =========================================================================
    // Validation Methods
    // =========================================================================

    /**
     * Validate CSRF token for a form submission
     *
     * @param array $postData POST data containing CSRF token
     * @param int|null $pcid Parent comment ID (for reply forms)
     * @return Episciences_Paper_MessageSubmissionResult|null Null if valid, error result otherwise
     */
    public function validateCsrfToken(array $postData, ?int $pcid = null): ?Episciences_Paper_MessageSubmissionResult
    {
        $csrfElementName = $this->buildCsrfElementName($pcid);

        if (!Episciences_Csrf_Helper::validateFormToken($csrfElementName, $postData)) {
            return Episciences_Paper_MessageSubmissionResult::csrfInvalid(self::MSG_ERROR_CSRF_INVALID);
        }

        return null;
    }

    /**
     * Validate the comment field (not empty after trim)
     *
     * @param array $postData POST data
     * @param int|null $pcid Parent comment ID (for reply forms using comment_<pcid>)
     * @return Episciences_Paper_MessageSubmissionResult|null Null if valid, error result otherwise
     */
    public function validateCommentField(array $postData, ?int $pcid = null): ?Episciences_Paper_MessageSubmissionResult
    {
        $fieldName = $pcid !== null ? 'comment_' . $pcid : 'comment';
        $commentMessage = trim($postData[$fieldName] ?? '');

        if ($commentMessage === '' || $commentMessage === '0') {
            return Episciences_Paper_MessageSubmissionResult::validationError(self::MSG_ERROR_COMMENT_EMPTY);
        }

        return null;
    }

    /**
     * Validate parent comment exists, belongs to this paper, and is the expected type
     *
     * @param int $pcid Parent comment ID
     * @return Episciences_Paper_MessageSubmissionResult|Episciences_Comment Error result or the validated comment
     */
    public function validateParentComment(int $pcid): Episciences_Paper_MessageSubmissionResult|Episciences_Comment
    {
        if ($pcid <= 0) {
            return Episciences_Paper_MessageSubmissionResult::notFound(self::MSG_ERROR_PARENT_NOT_FOUND);
        }

        $parentComment = new Episciences_Comment();
        if (!$parentComment->find($pcid)) {
            trigger_error("Parent comment not found: $pcid");
            return Episciences_Paper_MessageSubmissionResult::notFound(self::MSG_ERROR_PARENT_NOT_FOUND);
        }

        // Validate parent belongs to this paper
        if ((int)$parentComment->getDocid() !== (int)$this->paper->getDocid()) {
            trigger_error("Parent comment $pcid does not belong to paper " . $this->paper->getDocid());
            return Episciences_Paper_MessageSubmissionResult::notFound(self::MSG_ERROR_PARENT_WRONG_PAPER);
        }

        // Validate parent is expected type (author->editor expects editor reply, editor->author expects author reply)
        $expectedParentType = $this->getExpectedParentType();
        if ((int)$parentComment->getType() !== $expectedParentType) {
            trigger_error("Parent comment $pcid is not expected type $expectedParentType (type: " . $parentComment->getType() . ")");
            return Episciences_Paper_MessageSubmissionResult::notFound(self::MSG_ERROR_PARENT_INVALID_TYPE);
        }

        return $parentComment;
    }

    // =========================================================================
    // Processing Methods
    // =========================================================================
    /**
     * Process a main message submission (new conversation, no parent)
     *
     * @param array $postData POST data
     * @param callable $authCheck Authorization check callback: fn() => bool
     */
    public function processMainMessage(array $postData, callable $authCheck): Episciences_Paper_MessageSubmissionResult
    {
        // 1. Authorization check
        if (!$authCheck()) {
            return Episciences_Paper_MessageSubmissionResult::unauthorized($this->getUnauthorizedSendMessage());
        }

        // 2. Validate CSRF
        $csrfResult = $this->validateCsrfToken($postData);
        if ($csrfResult instanceof \Episciences_Paper_MessageSubmissionResult) {
            return $csrfResult;
        }

        // 3. Validate comment field
        $commentResult = $this->validateCommentField($postData);
        if ($commentResult instanceof \Episciences_Paper_MessageSubmissionResult) {
            return $commentResult;
        }

        // 4. Save the comment
        return $this->saveComment(trim((string) $postData['comment']), null, $authCheck);
    }

    /**
     * Process a reply message submission
     *
     * @param array $postData POST data
     * @param int $pcid Parent comment ID
     * @param callable $authCheck Authorization check callback: fn() => bool
     */
    public function processReplyMessage(array $postData, int $pcid, callable $authCheck): Episciences_Paper_MessageSubmissionResult
    {
        // 1. Authorization check
        if (!$authCheck()) {
            return Episciences_Paper_MessageSubmissionResult::unauthorized($this->getUnauthorizedReplyMessage());
        }

        // 2. Validate CSRF
        $csrfResult = $this->validateCsrfToken($postData, $pcid);
        if ($csrfResult instanceof \Episciences_Paper_MessageSubmissionResult) {
            return $csrfResult;
        }

        // 3. Validate comment field
        $commentResult = $this->validateCommentField($postData, $pcid);
        if ($commentResult instanceof \Episciences_Paper_MessageSubmissionResult) {
            return $commentResult;
        }

        // 4. Validate parent comment
        $parentValidation = $this->validateParentComment($pcid);
        if ($parentValidation instanceof Episciences_Paper_MessageSubmissionResult) {
            return $parentValidation;
        }

        // 5. Save the comment
        $commentFieldName = 'comment_' . $pcid;
        return $this->saveComment(trim((string) $postData[$commentFieldName]), $pcid, $authCheck, $parentValidation);
    }

    /**
     * Save a comment to the database
     *
     * @param string $message Comment message (already trimmed)
     * @param int|null $parentId Parent comment ID (null for main messages)
     * @param callable $authCheck Authorization re-check at save time
     * @param Episciences_Comment|null $parentComment Pre-validated parent comment
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     */
    public function saveComment(
        string $message,
        ?int $parentId,
        callable $authCheck,
        ?Episciences_Comment $parentComment = null
    ): Episciences_Paper_MessageSubmissionResult {
        // Re-verify authorization at save time
        if (!$authCheck()) {
            return Episciences_Paper_MessageSubmissionResult::unauthorized($this->getUnauthorizedSendMessage());
        }

        $docId = $this->paper->getDocid();
        $commentType = $this->getCommentType();

        // Create and configure the comment
        $comment = new Episciences_Comment();
        $commentPath = Episciences_PapersManager::buildDocumentPath($docId) . '/comments/';
        $comment->setFilePath($commentPath);
        $comment->setType($commentType);
        $comment->setDocid($docId);
        $comment->setParentid($parentId ?? 0);
        $comment->setMessage($message);

        // Handle file upload
        $this->handleFileUpload($comment, $commentPath, $parentId);

        // Save with ignoreUpload=true since we handle file upload explicitly
        if (!$comment->save(false, null, true)) {
            $errorMessage = $parentId !== null ? self::MSG_ERROR_SAVE_FAILED : self::MSG_ERROR_SEND_FAILED;
            return Episciences_Paper_MessageSubmissionResult::error($errorMessage);
        }

        // Build log data and log the action
        $coAuthors = $this->getCoAuthorsExcludingSender();
        $recipient = $this->getRecipientForLogging($parentComment);
        $editorsNotified = $this->getEditorsForLog();
        $logData = $this->buildLogData($comment, $coAuthors, $recipient, $editorsNotified);

        $this->paper->log(
            $this->getLogCode(),
            Episciences_Auth::getUid(),
            $logData
        );

        $successMessage = $this->getSuccessMessage($parentId !== null);
        return Episciences_Paper_MessageSubmissionResult::success($successMessage, $comment->getPcid(), $comment);
    }



    /**
     * Get co-authors for the paper, excluding the sender
     *
     * @return array Co-authors (may be empty)
     */
    public function getCoAuthorsExcludingSender(): array
    {
        $coAuthors = [];
        try {
            $coAuthors = $this->paper->getCoAuthors();
            // Remove sender from co-authors list
            unset($coAuthors[Episciences_Auth::getUid()]);
        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error('Error fetching co-authors: ' . $e->getMessage());
        }
        return $coAuthors;
    }

    /**
     * Get other assigned editors for the paper, excluding the sender (editor context only)
     * Used for logging when an editor sends a message to the author - other editors receive a copy
     *
     * @return array Other editors (may be empty)
     */
    public function getEditorsForLog(): array
    {
        try {
            $editors = $this->paper->getEditors(true, true);
            if ($this->controllerPath === self::CONTROLLER_ADMINISTRATEPAPER) {
                // For editor->author: exclude the sender (current editor) - they are CC
                unset($editors[Episciences_Auth::getUid()]);
            }
            // For author->editor: return all editors - they are the recipients (To)
            return $editors;
        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error('Error fetching editors for log: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Build log data for paper history
     *
     * @param Episciences_Comment $comment The saved comment
     * @param array $coAuthors Co-authors to notify (already excluding sender)
     * @param Episciences_User|null $recipient The recipient (for editor->author messages)
     * @param array $editorsNotified Other editors who receive a copy (for editor->author messages)
     * @return array Log data structure
     */
    public function buildLogData(Episciences_Comment $comment, array $coAuthors, ?Episciences_User $recipient = null, array $editorsNotified = []): array
    {
        $logData = [
            'comment' => [
                'pcid' => $comment->getPcid(),
                'type' => $comment->getType(),
                'message' => $comment->getMessage(),
                'file' => $comment->getFile(),
                'docid' => $this->paper->getDocid(),
                'uid' => Episciences_Auth::getUid()
            ],
            'user' => [
                'fullname' => Episciences_Auth::getFullName(),
                'SCREEN_NAME' => Episciences_Auth::getScreenName(),
                'email' => Episciences_Auth::getEmail()
            ]
        ];

        // Add parentid if this is a reply
        $parentId = $comment->getParentid();
        if (!empty($parentId)) {
            $logData['comment']['parentid'] = $parentId;
        }

        // Add recipient info (for editor->author messages)
        if ($recipient instanceof \Episciences_User) {
            $logData['recipient'] = [
                'uid' => $recipient->getUid(),
                'email' => $recipient->getEmail(),
                'fullname' => $recipient->getFullName()
            ];
        }

        // Add co-authors notification info
        if ($coAuthors !== []) {
            $coAuthorsNotified = [];
            foreach ($coAuthors as $coAuthor) {
                $coAuthorsNotified[] = [
                    'uid' => $coAuthor->getUid(),
                    'email' => $coAuthor->getEmail(),
                    'fullname' => $coAuthor->getFullName()
                ];
            }
            $logData['co_authors_notified'] = $coAuthorsNotified;
        }

        // Add editors: recipients (To) for author->editor, or CC for editor->author
        if ($editorsNotified !== []) {
            $editorsNotifiedList = [];
            foreach ($editorsNotified as $editor) {
                $editorsNotifiedList[] = [
                    'uid' => $editor->getUid(),
                    'email' => $editor->getEmail(),
                    'fullname' => $editor->getFullName()
                ];
            }
            $logData['editors_notified'] = $editorsNotifiedList;
        }

        return $logData;
    }

    /**
     * Get the comment type based on controller context
     *
     * @return int TYPE_AUTHOR_TO_EDITOR or TYPE_EDITOR_TO_AUTHOR
     */
    public function getCommentType(): int
    {
        return $this->controllerPath === self::CONTROLLER_PAPER
            ? Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR
            : Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR;
    }

    /**
     * Get the expected parent comment type for replies
     *
     * When an author replies, parent should be editor->author.
     * When an editor replies, parent should be author->editor.
     *
     * @return int Expected parent comment type
     */
    public function getExpectedParentType(): int
    {
        return $this->controllerPath === self::CONTROLLER_PAPER
            ? Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR
            : Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR;
    }

    /**
     * Get the log code based on controller context
     *
     * @return string Paper log code
     */
    public function getLogCode(): string
    {
        return $this->controllerPath === self::CONTROLLER_PAPER
            ? Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_AUTHOR_TO_EDITOR
            : Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_EDITOR_TO_AUTHOR;
    }

    /**
     * Build CSRF element name based on context
     *
     * @param int|null $pcid Parent comment ID (for reply forms)
     * @return string CSRF element name
     */
    private function buildCsrfElementName(?int $pcid): string
    {
        if ($pcid !== null) {
            $prefix = $this->controllerPath === self::CONTROLLER_PAPER
                ? 'csrf_author_reply_form_'
                : 'csrf_editor_reply_form_';
            return $prefix . $pcid;
        }

        return $this->controllerPath === self::CONTROLLER_PAPER
            ? 'csrf_authorToEditorForm'
            : 'csrf_editorToAuthorForm';
    }

    /**
     * Get the success message based on context and whether it's a reply
     *
     * @param bool $isReply Whether this is a reply message
     * @return string Translation key
     */
    private function getSuccessMessage(bool $isReply): string
    {
        if ($this->controllerPath === self::CONTROLLER_PAPER) {
            return $isReply ? self::MSG_SUCCESS_AUTHOR_REPLY : self::MSG_SUCCESS_AUTHOR_SENT;
        }
        return $isReply ? self::MSG_SUCCESS_EDITOR_REPLY : self::MSG_SUCCESS_EDITOR_SENT;
    }

    /**
     * Get the unauthorized message for sending
     *
     * @return string Translation key
     */
    private function getUnauthorizedSendMessage(): string
    {
        return $this->controllerPath === self::CONTROLLER_PAPER
            ? self::MSG_ERROR_UNAUTHORIZED_AUTHOR
            : self::MSG_ERROR_UNAUTHORIZED_EDITOR_SEND;
    }

    /**
     * Get the unauthorized message for replying
     *
     * @return string Translation key
     */
    private function getUnauthorizedReplyMessage(): string
    {
        return $this->controllerPath === self::CONTROLLER_PAPER
            ? self::MSG_ERROR_UNAUTHORIZED_AUTHOR
            : self::MSG_ERROR_UNAUTHORIZED_EDITOR;
    }

    /**
     * Handle file upload for a comment
     *
     * @param Episciences_Comment $comment The comment to attach file to
     * @param string $commentPath Path to save file
     * @param int|null $parentId Parent ID (determines field name)
     */
    private function handleFileUpload(Episciences_Comment $comment, string $commentPath, ?int $parentId): void
    {
        $fileFieldName = $parentId !== null ? 'file_' . $parentId : 'file';

        $fileTransfer = new Zend_File_Transfer_Adapter_Http();
        if ($fileTransfer->isUploaded($fileFieldName)) {
            // Security: validate filename before upload to prevent path traversal
            $fileInfo = $fileTransfer->getFileInfo($fileFieldName);
            $originalName = $fileInfo[$fileFieldName]['name'] ?? '';
            if ($originalName !== basename((string) $originalName) || str_contains($originalName, '..')) {
                trigger_error('Path traversal attempt rejected in file upload: ' . basename((string) $originalName));
                return;
            }

            if (!is_dir($commentPath)) {
                mkdir($commentPath, 0755, true);
            }
            $uploads = Episciences_Tools::uploadFiles($commentPath);
            if (isset($uploads[$fileFieldName]) && empty($uploads[$fileFieldName]['errors'])) {
                // Security: use basename() to ensure no path components in stored filename
                $safeName = basename((string) $uploads[$fileFieldName]['name']);
                $comment->setFile($safeName);
            }
        }
    }

    /**
     * Get the recipient user for logging (editor->author messages)
     *
     * @param Episciences_Comment|null $parentComment Parent comment to get author from
     * @return Episciences_User|null Recipient user, or null for author->editor
     */
    private function getRecipientForLogging(?Episciences_Comment $parentComment): ?Episciences_User
    {
        // Only editor->author messages have a specific recipient
        if ($this->controllerPath !== self::CONTROLLER_ADMINISTRATEPAPER) {
            return null;
        }

        // For replies, get author from parent comment
        if ($parentComment instanceof \Episciences_Comment) {
            $author = new Episciences_User();
            $author->findWithCAS($parentComment->getUid());
            return $author;
        }

        // For initial messages, get paper author
        $author = new Episciences_User();
        $author->findWithCAS($this->paper->getUid());
        return $author;
    }

    /**
     * Get the paper instance
     */
    public function getPaper(): Episciences_Paper
    {
        return $this->paper;
    }

    /**
     * Get the controller path
     */
    public function getControllerPath(): string
    {
        return $this->controllerPath;
    }
}