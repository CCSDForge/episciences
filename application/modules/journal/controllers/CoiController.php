<?php
require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';


class CoiController extends PaperDefaultController
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
    public function reportAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $docId = (int)(!$request->getPost('id') ? $request->getParam('id') : $request->getPost('id'));

        $paper = Episciences_PapersManager::get($docId);

        // check if paper exists
        if (!$paper || $paper->getRvid() !== RVID) {
            $this->getResponse()?->setHttpResponseCode(404);
            $this->renderScript('index/notfound.phtml');
            return;
        }

        $post = $request->getPost();

        $loggedUid = Episciences_Auth::getUid();

        $checkConflictResponse = $paper->checkConflictResponse($loggedUid);
        $controller = self::PUBLIC_PAPER_CONTROLLER;

        if (self::isConflictDetected($paper)) {

            $form = Episciences_Paper_ConflictsManager::getCoiForm();

            if ($checkConflictResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {

                if (array_key_exists('coiReport', $post) && $request->isPost()) {

                    if ($form->isValid($post)) {
                        $this->conflictProcessing($post, $paper);
                        return;
                    }

                    $form->setDefaults($post);
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($this->view->translate("Ce formulaire comporte des erreurs."));
                }

                $this->view->paper = $paper;
                $this->view->form = $form;
                return;
            }

        } else {
            $controller = self::ADMINISTRATE_PAPER_CONTROLLER;
        }

        $this->_helper->redirector->gotoUrl($this->url(['controller' => $controller, 'action' => 'view', 'id' => $paper->getDocid()]));
        $this->view->metadata = $paper->getDatasetsFromEnrichment();
    }

    /**
     * Save reported conflict
     * @param array $post
     * @param Episciences_Paper $paper
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function conflictProcessing(array $post, Episciences_Paper $paper): void
    {
        $docId = $paper->getDocid();
        $coiReport = $post['coiReport'];
        $message = $post['message'];

        if ((isset($post['message'])) && ($post['message'] !== '')) {

            $message = trim($post['message']);
            $htmlPurifier = new Episciences_HTMLPurifier([
                'HTML.AllowedElements' => ['p', 'b', 'u', 'i', 'a', 'strong', 'em', 'span']
            ]);

            $decodedMessage = html_entity_decode($message);
            $message = $htmlPurifier->purifyHtml($decodedMessage);

        }

        $loggedUid = Episciences_Auth::getUid();
        
        if ($coiReport !== Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {

            $conflict = new Episciences_Paper_Conflict([
                'by' => $loggedUid,
                'paper_id' => $paper->getPaperid(),
                'answer' => $coiReport,
                'message' => $message
            ]);

            $latestInsertId = $conflict->save();

            if ($latestInsertId < 1) {
                $message = sprintf("<strong>%s</strong>", $this->view->translate("Votre réponse n'a pas pu être enregistrée."));
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);

            } else {

                $conflict->setCid($latestInsertId);

                try {
                    $conflict->setDate();

                } catch (Exception $e) {
                    trigger_error($e->getMessage(), E_USER_ERROR);
                }

                $details = ['user' => ['fullname' => Episciences_Auth::getFullName()], 'conflict' => $conflict->toArray()];
                $paper->log(Episciences_Paper_Logger::CODE_COI_REPORTED, $loggedUid, $details);

                // When an editor / copy editor is assigned, if he/she declares a COI => un-assign him/her.
                $url = $this->buildPublicPaperUrl($docId);

                // This unassignment triggers the a notification email to all editors in chief and secretaries
                $ccRecipients = [];
                Episciences_Review::checkReviewNotifications($ccRecipients);

                Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $ccRecipients);


                if ($coiReport === Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']) {

                    if ($paper->getEditor($loggedUid)) {
                        $this->unssignUser($paper, [$loggedUid], $url, Episciences_User_Assignment::ROLE_EDITOR, null, $ccRecipients);
                    }

                    if ($paper->getCopyEditor($loggedUid)) {
                        $this->unssignUser($paper, [$loggedUid], $url, Episciences_User_Assignment::ROLE_COPY_EDITOR, null, $ccRecipients);
                    }

                }

            }

            if ($coiReport === Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']) {
                $url = $this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'view', 'id' => $docId]);
            }

            $message = sprintf("<strong>%s</strong>", $this->view->translate("Votre réponse à bien été enregistrée."));
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);

        }

        $this->_helper->redirector->gotoUrl($this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId ]));
    }

    /**
     * delete conflict
     * @return void
     */
    public function deleteAction(): void
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (Episciences_Auth::isSecretary() && $request->isXmlHttpRequest() && $request->isPost()) {
            $id = (int)$request->getPost('conflictId');
            $docId = (int)$request->getPost('docId');

            $currentConflict = Episciences_Paper_ConflictsManager::findById($id);
            $respond = false;

            try {

                $paper = Episciences_PapersManager::get($docId, false);
                $respond = Episciences_Paper_ConflictsManager::deleteById($id);

                if ($respond) {

                    $details = ['user' => [
                        'fullname' => Episciences_Auth::getFullName()
                    ],
                        'conflict' => $currentConflict ? $currentConflict->toArray() : [],
                        'docid' => $paper->getDocid()
                    ];

                    $paper->log(Episciences_Paper_Logger::CODE_COI_REVERTED, Episciences_Auth::getUid(), $details);
                }


            } catch (Exception $e) {
                trigger_error($e->getMessage());

            }

            $this->_helper->viewRenderer->setNoRender();
            $this->_helper->getHelper('layout')->disableLayout();
            echo $respond;

        } elseif ($request->isXmlHttpRequest()) {
            echo false;

        } else {
            $this->getResponse()?->setHttpResponseCode(404);
            $this->renderScript('index/notfound.phtml');

        }

    }
}
