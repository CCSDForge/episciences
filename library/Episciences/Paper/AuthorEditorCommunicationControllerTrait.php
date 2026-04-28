<?php

/**
 * Trait for shared author-editor communication controller logic
 * Used by PaperController (author side) and AdministratepaperController (editor side)
 *
 * Requirements for using class:
 * - Must extend PaperDefaultController (provides newCommentNotifyManager)
 * - Must have access to $this->_helper->FlashMessenger
 * - Must have access to $this->_helper->redirector
 * - Must have access to $this->view
 * - Must have access to $this->getRequest()
 */
trait Episciences_Paper_AuthorEditorCommunicationControllerTrait
{
    /**
     * Handle both main message and reply submissions for author-editor communication
     *
     * @param Episciences_Paper $paper The paper being discussed
     * @param Episciences_Paper_AuthorEditorCommunicationService $service Communication service
     * @param callable $authCallback Authorization check returning bool
     * @param string $redirectController Controller name for redirect ('paper' or 'administratepaper')
     */
    private function handleCommunicationSubmission(
        Episciences_Paper                                  $paper,
        Episciences_Paper_AuthorEditorCommunicationService $service,
        callable                                           $authCallback,
        string                                             $redirectController
    ): void
    {
        $postData = $this->getRequest()->getPost();

        if (!$this->getRequest()->isPost() || !isset($postData['postComment'])) {
            return;
        }

        try {
            $isReply = !empty($postData['reply_to_pcid']);

            $result = $isReply
                ? $service->processReplyMessage($postData, (int)$postData['reply_to_pcid'], $authCallback)
                : $service->processMainMessage($postData, $authCallback);

            if ($result->isSuccess() && $result->getComment() instanceof \Episciences_Comment) {
                try {
                    $this->newCommentNotifyManager(
                        $paper,
                        $result->getComment(),
                        [],
                        [],
                        ['coAuthors' => $service->getCoAuthorsExcludingSender()]
                    );
                } catch (Exception $notifyException) {
                    // Log notification failure but don't prevent success flow
                    // The message was saved, notifications may have partially failed
                    trigger_error('Notification error (message saved): ' . $notifyException->getMessage());
                }
            }

            $this->handleCommunicationResult($result, $paper, $redirectController);
        } catch (Exception $e) {
            $this->_helper->FlashMessenger->setNamespace('error')->addMessage(
                $this->view->translate(Episciences_Paper_AuthorEditorCommunicationService::MSG_ERROR_GENERAL)
            );
            trigger_error('Error in author-editor communication: ' . $e->getMessage());
            // Redirect to show the saved message if any
            $this->_helper->redirector->gotoUrl('/' . $redirectController . '/view?id=' . $paper->getDocid());
        }
    }

    /**
     * Handle the result of a communication submission (flash message + redirect)
     *
     * @param Episciences_Paper_MessageSubmissionResult $result Submission result
     * @param Episciences_Paper $paper The paper
     * @param string $redirectController Controller name for redirect URL
     */
    private function handleCommunicationResult(
        Episciences_Paper_MessageSubmissionResult $result,
        Episciences_Paper                         $paper,
        string                                    $redirectController
    ): void
    {
        $this->_helper->FlashMessenger
            ->setNamespace($result->getFlashNamespace())
            ->addMessage($this->view->translate($result->getMessageKey()));

        if ($result->shouldRedirect()) {
            $this->_helper->redirector->gotoUrl('/' . $redirectController . '/view?id=' . $paper->getDocid());
        }
    }
}