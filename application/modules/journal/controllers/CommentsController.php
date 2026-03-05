<?php

use Episciences\AppRegistry;

include_once APPLICATION_PATH . '/modules/journal/controllers/PaperController.php';

/**
 * Class CommentsController
 */
class CommentsController extends PaperController
{
    /**
     * Remove a file comment
     * Security: Requires POST method with CSRF token
     */
    public function removefilecommentAction(): void
    {

        $this->_helper->getHelper('layout')->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        // SECURITY FIX: Require POST method with CSRF token
        if (!$this->getRequest()->isPost()) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($this->view->translate("Invalid request method."));
            $this->_helper->redirector->gotoUrl('/');
            return;
        }

        // Verify CSRF token using Zend_Form_Element_Hash via helper
        $csrfTokenName = $this->getRequest()->getPost('csrf_token_name');
        if (empty($csrfTokenName)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($this->view->translate("Invalid or expired security token. Please try again."));
            $this->_helper->redirector->gotoUrl('/');
            return;
        }

        // Get the sanitized element name (same logic as in Helper)
        $elementName = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $csrfTokenName);
        $csrfTokenValue = $this->getRequest()->getPost($elementName);

        if (!Episciences_Csrf_Helper::validateToken($csrfTokenName, $csrfTokenValue)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($this->view->translate("Invalid or expired security token. Please try again."));
            $this->_helper->redirector->gotoUrl('/');
            return;
        }

        $docid = (int)$this->getRequest()->getParam('docid');
        $pcid = (int)$this->getRequest()->getParam('pcid');
        $file = $this->getRequest()->getParam('file');

        // SECURITY FIX: Validate filename format (alphanumeric, dots, dashes, underscores only)
        if (!is_string($file) || $file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($this->view->translate("Invalid filename."));
            $this->_helper->redirector->gotoUrl('/');
            return;
        }

        try {
            $paper = Episciences_PapersManager::get($docid, false, RVID);
        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage());
            $paper = null;
        }

        if (!$paper) {
            return;
        }

        $controllerName = 'paper';

        if (
            Episciences_Auth::isAllowedToManagePaper() &&
            $paper->getUid() !== Episciences_Auth::getUid()
        ) {
            $controllerName = 'administratepaper';
        }

        $url = sprintf('%s/view/id/%s', $controllerName, $paper->getLatestVersionId());
        $comment = new Episciences_Comment();

        try {
            if (null === $comment->find($pcid)) {
                $message = $this->view->translate("Le commentaire demandé n’existe pas.");
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->_helper->redirector->gotoUrl($url);
                return;
            }
        } catch (Zend_Json_Exception $e) {
            trigger_error($e->getMessage());
            return;
        }


        if ($comment->getUid() !== Episciences_Auth::getUid()) {
            $message = $this->view->translate("Vous n'avez pas les autorisations nécessaires pour supprimer ce fichier.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);
            return;
        }


        if ($comment->getType() === Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION) {
            $message = $this->view->translate("Ce fichier est attaché à la version temporaire, vous ne pouvez donc pas le supprimer.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);
            return;
        }

        // Special handling for author-editor communication (both directions)
        $isAuthorEditorCommunication = in_array($comment->getType(), [
            Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
            Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR
        ], true);

        if ($isAuthorEditorCommunication) {
            // If a specific file is provided, delete only that file (not the entire comment)
            if ($file !== '0') {
                $isJson = Episciences_Tools::isJson($comment->getFile());

                try {
                    $jFiles = $isJson ? json_decode($comment->getFile(), true, 512, JSON_THROW_ON_ERROR) : (array)$comment->getFile();
                    $jFiles = array_filter($jFiles); // Remove empty values
                } catch (JsonException $e) {
                    trigger_error($e->getMessage());
                    $jFiles = [];
                }

                $dir = $docid . '/comments/';
                $comment_path = REVIEW_FILES_PATH . $dir . $file;

                if (!$this->validateFileDeletePermission($file, $jFiles, $comment_path, $url)) {
                    return;
                }

                $is_file = is_file($comment_path);

                if ($is_file) {
                    $key = array_search($file, $jFiles, true);
                    if ($key !== false) {
                        unset($jFiles[$key]);
                    }
                    $jFiles === [] ? $comment->setFile(null) : $comment->setFile(json_encode($jFiles));
                    unlink($comment_path);

                    // Save the comment (this updates the file field)
                    // For TYPE_AUTHOR_TO_EDITOR, save() automatically creates a log
                    // For TYPE_EDITOR_TO_AUTHOR_RESPONSE, we need to create the log manually (excluded in Comment._excludedCommentsTypes)
                    $comment->save(true);

                    if ($comment->getType() === Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE) {
                        $paper->log(
                            Episciences_Paper_Logger::CODE_PAPER_COMMENT_FROM_EDITOR_TO_AUTHOR,
                            Episciences_Auth::getUid(),
                            [
                                'user' => Episciences_Auth::getUser()->toArray(),
                                'comment' => $comment->toArray(),
                                'file_removed' => $file
                            ]
                        );
                    }

                    $message = $this->view->translate("Le fichier attaché a bien été supprimé.");
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage($message);
                } else {
                    $message = $this->view->translate("Impossible de supprimer le fichier : élément introuvable.");
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }

                $this->_helper->redirector->gotoUrl($url);
                return;
            }

            // No file specified - nothing to delete, redirect back
            $this->_helper->redirector->gotoUrl($url);
            return;
        }

        // Default behavior: delete only the specified file (for other comment types)
        $isJson = Episciences_Tools::isJson($comment->getFile());

        try {
            $jFiles = $isJson ? json_decode($comment->getFile(), true, 512, JSON_THROW_ON_ERROR) : (array)$comment->getFile();
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
            $jFiles = [];
        }

        if ($comment->isCopyEditingComment()) {
            $dir = $comment->getDocid();
            $dir .= DIRECTORY_SEPARATOR;
            $dir .= Episciences_CommentsManager::COPY_EDITING_SOURCES;
            $dir .= DIRECTORY_SEPARATOR;
            $dir .= $comment->getPcid();
            $dir .= DIRECTORY_SEPARATOR;
        } else {
            $dir = $docid . '/comments/';
        }

        $comment_path = REVIEW_FILES_PATH . $dir . $file;

        if (!$this->validateFileDeletePermission($file, $jFiles, $comment_path, $url)) {
            return;
        }

        $is_file = is_file($comment_path);
        if ($file && $is_file) {//note that is_file() returns false if the parent directory doesn't have +x set for you

            $key = array_search($file, $jFiles, true);
            if ($key !== false) {
                unset($jFiles[$key]);
            }
            empty($jFiles) ? $comment->setFile(null) : $comment->setFile(json_encode($jFiles));
            unlink($comment_path);
            $comment->save(true);
            $message = $this->view->translate("Le fichier attaché a bien été supprimé.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage($message);

        } else {
            $message = $this->view->translate("Impossible de supprimer le fichier : élément introuvable.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        }

        $this->_helper->redirector->gotoUrl($url);
    }

    /**
     * Edition d'un commentaire
     */
    public function editcommentAction(): void
    {
        $request = $this->getRequest();

        $pcid = (int)$request?->getParam('pcid');
        $paper = null;
        $url = '/';

        $oldComment = Episciences_CommentsManager::getComment($pcid); // array | false

        if ($oldComment) {
            try {
                $paper = Episciences_PapersManager::get($oldComment['DOCID'], false, RVID);
            } catch (Zend_Db_Statement_Exception $e) {
                AppRegistry::getMonoLogger()?->critical($e->getMessage());
            }
        }

        if (!$paper) {
            $this->_helper->redirector->gotoUrl($url);
            return;
        }

       $this->checkAccess($paper);

        if ($paper->isOwner()) {
            $url = $this->buildPublicPaperUrl($paper->getDocid());
        } elseif (Episciences_Auth::isSecretary()) {
            $url = $this->buildAdminPaperUrl($paper->getDocid());
        }

        if (in_array($paper->getStatus(), $paper::$_noEditableStatus, true)) {
            $this->_helper->redirector->gotoUrl($url);
            return;
        }

        /** @var Zend_Controller_Request_Http $request */
        try {
            $form = Episciences_CommentsManager::getEditAuthorCommentForm($oldComment);
            if (
                $request?->isPost() &&
                $form->isValid($request?->getPost())
            ) {

                $formValues = $form->getValues();
                $newComment = new Episciences_Comment($oldComment);
                $newComment->setMessage($formValues['author_comment'] ?? null);

                if (isset($formValues[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME])) { // Chargement d'un nouveau fichier
                    $newComment->setFile($formValues[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME]);
                }

                if ($newComment->getFile() === $oldComment['FILE'] && $newComment->getMessage() === $oldComment['MESSAGE']) {
                    $this->_helper->redirector->gotoUrl($url);
                    return;
                }

                $newComment = Episciences_CommentsManager::saveCoverLetter($paper, $newComment);

                if (!$newComment) {
                    $message = $this->view->translate("Une erreur est survenue lors de l'enregistrement de votre commentaire.");
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                } else {
                    $this->newCommentNotifyManager($paper, $newComment);
                    $message = $this->view->translate("Vos changements ont été enregistrés.");
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                }

                $this->_helper->redirector->gotoUrl($url);
                return;
            }
        } catch (Zend_Form_Exception|Zend_Exception  $e) {
            AppRegistry::getMonoLogger()?->critical($e->getMessage());
        }


        $this->view->edit_comment_form = $form;
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Json_Exception
     */
    public function addcommentAction(): void
    {
        $request = $this->getRequest();

        $docId = (int)$request?->getParam('docid');

        $paper = Episciences_PapersManager::get($docId, false, RVID);

        $url = '/';

        if ($paper) {

            $this->checkAccess($paper);

            if ($paper->isOwner()) {
                $url = $this->buildPublicPaperUrl($docId);
            } elseif (Episciences_Auth::isSecretary()) {
                $url = $this->buildAdminPaperUrl($docId);
            }

            if (in_array($paper->getStatus(), $paper::$_noEditableStatus, true)) {
                $this->_helper->redirector->gotoUrl($url);
                return;
            }

            $author_comments = Episciences_CommentsManager::getList(
                $paper->getDocid(),
                [
                    'type' => Episciences_CommentsManager::TYPE_AUTHOR_COMMENT
                ]);

            if (!$author_comments) {

                $form = Episciences_CommentsManager::getEditAuthorCommentForm();

                if (
                    $request?->isPost() &&
                    $form->isValid($request?->getPost()
                    )
                ) {

                    $formValues = $form->getValues();

                    $coverLetter = [
                        "message" => $formValues[Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME] ?? '',
                        "attachedFile" => $formValues[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME] ?? null
                    ];

                    $savedComment = Episciences_CommentsManager::saveCoverLetter($paper, $coverLetter);

                    if ($savedComment) {
                        $this->newCommentNotifyManager($paper, $savedComment);
                        $message = $this->view->translate("Vos changements ont été enregistrés.");
                        $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);

                    } else {
                        $message = $this->view->translate("Une erreur est survenue lors de l'enregistrement de votre commentaire.");
                        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                    }

                    $this->_helper->redirector->gotoUrl($url);
                }

                $this->view->form = $form;

            }


        } else {
            $this->_helper->redirector->gotoUrl($url);
        }
    }

    private function checkAccess(Episciences_Paper $paper): void
    {
        // Comments are edited by the author, administrator, editor-in-chief or secretary.
        if (!$paper->isOwner() && !Episciences_Auth::isSecretary()) {
            $message = "Vous avez été redirigé, car vous n'êtes pas l'auteur de ce commentaire.";
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector->gotoUrl('/paper/submitted');
        }
    }

    /**
     * Validate file deletion permission
     * SECURITY FIX: Verify file belongs to comment and path is within allowed directory
     *
     * @param string $file The filename to delete
     * @param array $jFiles The list of files attached to the comment
     * @param string $commentPath The full path to the file
     * @param string $redirectUrl The URL to redirect to on failure
     * @return bool True if validation passes, false otherwise
     */
    private function validateFileDeletePermission(string $file, array $jFiles, string $commentPath, string $redirectUrl): bool
    {
        // SECURITY FIX: Verify file belongs to this comment
        if (!in_array($file, $jFiles, true)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($this->view->translate("File not found in comment."));
            $this->_helper->redirector->gotoUrl($redirectUrl);
            return false;
        }

        // SECURITY FIX: Verify the resolved path is within allowed directory
        $realPath = realpath($commentPath);
        $allowedBasePath = realpath(REVIEW_FILES_PATH);

        if ($realPath === false || $allowedBasePath === false ||
            !str_starts_with($realPath, $allowedBasePath . DIRECTORY_SEPARATOR)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                ->addMessage($this->view->translate("Access denied."));
            $this->_helper->redirector->gotoUrl($redirectUrl);
            return false;
        }

        return true;
    }

}