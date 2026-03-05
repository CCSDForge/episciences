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

    public function __construct(Episciences_Paper $paper, Episciences_Review $review, private readonly string $controllerPath = self::CONTROLLER_PAPER)
    {
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
                    Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE
                ]
            ]
        );

        if (!empty($comments)) {
            $comments = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);
        }

        return $comments;
    }

    /**
     * Create the main author to editor form
     *
     * @throws Zend_Form_Exception
     */
    public function createMainForm(): Ccsd_Form
    {
        $form = Episciences_CommentsManager::getForm('authorToEditorForm', false, true);
        $form->setAction('/' . $this->controllerPath . '/view?id=' . $this->paper->getDocid());

        $this->configureCancelButton($form, 'btn btn-default cancel-author-form');

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
            Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
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
            if (isset($comment['TYPE']) && (int)$comment['TYPE'] === $targetType) {
                $pcid = $comment['PCID'];
                if (!isset($replyForms[$pcid])) {
                    $replyForms[$pcid] = $this->createReplyForm($pcid, $formPrefix);
                }
            }

            // Only process direct replies (first level), do NOT recurse further
            // This prevents form creation for corrupted nested hierarchies
            if (!empty($comment['replies'])) {
                foreach ($comment['replies'] as $reply) {
                    if (isset($reply['TYPE']) && (int)$reply['TYPE'] === $targetType) {
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
}