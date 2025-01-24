<?php
include_once APPLICATION_PATH . '/modules/journal/controllers/PaperController.php';

/**
 * Class CommentsController
 */
class CommentsController extends PaperController
{
    /**
     * Remove a file comment
     */
    public function removefilecommentAction()
    {

        $this->_helper->getHelper('layout')->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $docid = (int)$this->getRequest()->getParam('docid');
        $pcid = (int)$this->getRequest()->getParam('pcid');
        $file = $this->getRequest()->getParam('file');

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

        $url = $this->url(['controller' => $controllerName, 'action' => 'view', 'id' => $paper->getLatestVersionId() ]);
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
        }


        if($comment->getUid() !== Episciences_Auth::getUid()){
            $message = $this->view->translate("Vous n'avez pas les autorisations nécessaires pour supprimer ce fichier.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);
            return;
        }



        if($comment->getType() === Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION){
            $message = $this->view->translate("Ce fichier est attaché à la version temporaire, vous ne pouvez donc pas le supprimer.");
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);
            return;
        }


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

        $is_file = is_file($comment_path);
        if ($file && $is_file) {//note that is_file() returns false if the parent directory doesn't have +x set for you

            $key = array_search($file, $jFiles, true);
            if ($key !== false) {
                unset($jFiles[$key]);
            }
            !empty($jFiles) ? $comment->setFile(json_encode($jFiles)) : $comment->setFile(null);
            unlink($comment_path);
            $comment->save(true);
            $message = $this->view->translate("Le fichier a bien été supprimé.");
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
    public function editcommentAction()
    {
        $request = $this->getRequest();

        $pcid = (int)$request->getParam('pcid');
        $paper = null;

        $urlOptions = ['controller' => 'index'];

        $oldComment = Episciences_CommentsManager::getComment($pcid); // array | false

        if ($oldComment) {
            $paper = Episciences_PapersManager::get($oldComment['DOCID'], false, RVID);
        }

        if (!$paper) {
            $this->_helper->redirector->gotoUrl($this->url($urlOptions));
            return;
        }

        if (
            Episciences_Auth::isLogged() &&
            (
                $paper->isOwner() ||
                Episciences_Auth::isSecretary()
            )
        ) { // Le commentaire est edité uniquement par son auteur ou l'administrateur ou le rédacteur en chef.
            $form = Episciences_CommentsManager::getEditAuthorCommentForm($oldComment);
            /** @var Zend_Controller_Request_Http $request */
            if (
                $request->isPost() &&
                $form->isValid($request->getPost())
            ) {
                $form_values = $form->getValues();
                $newComment = new Episciences_Comment();

                if (isset($form_values[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME])) { // Chargement d'un nouveau fichier
                    $newComment->setFile($form_values[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME]);
                    $strict = false;
                } else {
                    $newComment->setFile($oldComment['FILE']);
                    $strict = true;
                }
                $newComment->setFilePath(REVIEW_FILES_PATH . $oldComment['DOCID'] . '/comments/');
                $newComment->setType($oldComment['TYPE']);
                $newComment->setDocid($oldComment['DOCID']);
                $newComment->setMessage($form_values[Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME]);
                $newComment->setPcid($oldComment['PCID']);
                if (!$newComment->save($strict)) {
                    $message = $this->view->translate("Une erreur est survenue lors de l'enregistrement de votre commentaire.");
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                } else {

                    if ($paper->isOwner()) {
                        $urlOptions = ['controller' => 'paper', 'action' => 'view', 'id' => $paper->getDocid()];
                    } elseif (Episciences_Auth::isSecretary()) {
                        $urlOptions = ['controller' => PaperDefaultController::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'view', 'id' => $paper->getDocid()];
                    }

                    $message = $this->view->translate("Vos changements ont été enregistrés.");
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                }

                $this->_helper->redirector->gotoUrl($this->url($urlOptions));
                return;
            }

        } else {
            $message = "Vous avez été redirigé, car vous n'êtes pas l'auteur de ce commentaire.";
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'submitted']));
            return;
        }

        $this->view->edit_comment_form = $form;


    }

    /**
     * @return void
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

        $docId = (int)$request->getParam('docid');

        $paper = Episciences_PapersManager::get($docId, false, RVID);

        $url = '/';

        if ($paper) {

            $author_comments = Episciences_CommentsManager::getList(
                $paper->getDocid(),
                [
                    'type' => Episciences_CommentsManager::TYPE_AUTHOR_COMMENT
                ]);

            if (
                !$author_comments &&
                (
                    $paper->isOwner() ||
                    Episciences_Auth::isSecretary()
                )
            ) {

                $form = Episciences_CommentsManager::getEditAuthorCommentForm();


                if (
                    $request->isPost() &&
                    $form->isValid($request->getPost()
                    )
                ) {

                    $formValues = $form->getValues();

                    $coverLetter = [
                        "message" => $formValues[Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME] ?? '',
                        "attachedFile" => $formValues[Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME] ?? null
                    ];

                    if (Episciences_CommentsManager::saveCoverLetter($paper, $coverLetter)) {
                        $message = $this->view->translate("Vos changements ont été enregistrés.");
                        $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);

                    } else {
                        $message = $this->view->translate("Une erreur est survenue lors de l'enregistrement de votre commentaire.");
                        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                    }

                    if ($paper->isOwner()) {
                        $url = $this->buildPublicPaperUrl($docId);
                    } elseif (Episciences_Auth::isSecretary()) {
                        $url = $this->buildAdminPaperUrl($docId);
                    }

                    $this->_helper->redirector->gotoUrl($url);

                }

                $this->view->form = $form;

            }


        } else {
            $this->_helper->redirector->gotoUrl($url);
        }


    }

}