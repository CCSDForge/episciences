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
        $isTmp = (boolean)$this->getRequest()->getParam('istmp');
        $paper = Episciences_PapersManager::get($docid);

        $controllerName = 'paper';

        if ($paper->getUid() != Episciences_Auth::getUid() && Episciences_Auth::isAllowedToManagePaper()) {
            $controllerName = 'administratepaper';
        }

        $url = '/' . $controllerName . '/view/id/' . $docid;


        if (!$paper) {
            $message = $this->view->translate("Le document demande n’existe pas.");
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);
            return;
        }

        $comment = new Episciences_Comment();

        if (null == $comment->find($pcid)) {
            $message = $this->view->translate("Le commentaire demandé n’existe pas.");
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);
            return;
        }

        $isJson = Episciences_Tools::isJson($comment->getFile());

        $jFiles = $isJson ? json_decode($comment->getFile(), true) : (array)$comment->getFile();

        $paperId = ($paper->getPaperid()) ? $paper->getPaperid() : $paper->getDocid();

        if($isTmp){ // fichiers joints aux  commentaires des versions temporaires
            $dir = $paperId . '/tmp/';
        } elseif ($comment->isCopyEditingComment()) {
            $dir = $comment->getDocid() . '/copy_editing_sources/' . $comment->getPcid() . '/';
        } else {
            $dir = $docid . '/comments/';
        }

        $comment_path = REVIEW_FILES_PATH . $dir . $file;

        $is_file = is_file($comment_path);
        if ($file && $is_file) {//note that is_file() returns false if the parent directory doesn't have +x set for you
            if (Episciences_Auth::isLogged() && Episciences_Auth::getUid() == $comment->getUid()) {
                $key = array_search($file, $jFiles);
                unset($jFiles[$key]);
                !empty($jFiles) ? $comment->setFile(json_encode($jFiles)) : $comment->setFile(null);
                unlink($comment_path);
                $comment->save('update', true);
                $message = $this->view->translate("Le fichier a bien été supprimé.");
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
            }
        } else {
            $message = $this->view->translate("Impossible de supprimer le fichier : élément introuvable.");
            $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
        }

        $this->_helper->redirector->gotoUrl($url);
    }

    /**
     * Edition d'un commentaire
     */
    public function editcommentAction()
    {
        $pcid = (int)$this->getRequest()
            ->getParam('pcid');
        if ($pcid) {
            $oldComment = Episciences_CommentsManager::getComment($pcid); // array
            if (Episciences_Auth::isLogged() && ($oldComment['UID'] == Episciences_Auth::getUid() || Episciences_Auth::isSecretary())) { // Le commentaire est edité uniquement par son auteur ou l'administrateur ou le rédacteur en chef.
                $form = Episciences_CommentsManager::getEditAuthorCommentForm($oldComment);
                /** @var Zend_Controller_Request_Http $request */
                $request = $this->getRequest();
                if ($request->isPost() && $form->isValid($request->getPost())) {
                    $form_values = $form->getValues();
                    $newComment = new Episciences_Comment();

                    if (isset($form_values['file_comment_author'])) { // Chargement d'un nouveau fichier
                        $newComment->setFile($form_values['file_comment_author']);
                        $strict = false;
                    } else {
                        $newComment->setFile($oldComment['FILE']);
                        $strict = true;
                    }
                    $newComment->setFilePath(REVIEW_FILES_PATH . $oldComment['DOCID'] . '/comments/');
                    $newComment->setType($oldComment['TYPE']);
                    $newComment->setDocid($oldComment['DOCID']);
                    $newComment->setMessage($form_values['author_comment']);
                    $newComment->setPcid($oldComment['PCID']);
                    if (!$newComment->save('update', $strict)) {
                        $message = $this->view->translate("Une erreur est survenue lors de l'enregistrement de votre commentaire.");
                        $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
                    } else {
                        $url = Episciences_Auth::isSecretary() ? $this->buildAdminPaperUrl($oldComment['DOCID']) : $this->buildPublicPaperUrl($oldComment['DOCID']);
                        $message = $this->view->translate("Vos changements ont été enregistrés.");
                        $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                    }

                    $this->_helper->redirector->gotoUrl($url);
                    return;
                }
                $this->view->edit_comment_form = $form;

            } else {
                $message = "Vous avez été redirigé, car vous n'êtes pas l'auteur de ce commentaire.";
                $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
                $this->_helper->redirector->gotoUrl('/paper/submitted');
            }
        } else {
            $this->_helper->redirector->gotoUrl('/');
        }// end if pcid
    }
}