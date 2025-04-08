<?php

class DoiController extends Episciences_Controller_Action
{
    public function indexAction()
    {
        $this->renderScript('index/submenu.phtml');
    }


    public function settingsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $journalDoiSettings = Episciences_Review_DoiSettingsManager::findByJournal(RVID);

        if (($request->isPost() && array_key_exists('submit', $request->getPost()))) {
            $defaults = $request->getPost();
        } else {
            $defaults = $journalDoiSettings->__toArray();
        }

        $form = Episciences_Review_DoiSettingsManager::getSettingsForm();

        if ($request->isPost() && array_key_exists('submit', $request->getPost())) {
            if ($form->isValid($request->getPost())) {
                $journalDoiSettings->setOptions($form->getValues());


                if (Episciences_Review_DoiSettingsManager::save($journalDoiSettings, RVID)) {
                    $message = sprintf("<strong>%s</strong>", $this->view->translate("Les modifications ont bien été enregistrées."));
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                    $url = $this->url(['action' => $this->getRequest()->getActionName(), 'controller' =>$this->getRequest()->getControllerName()]);
                    $this->_helper->redirector->gotoUrl($url);
                } else {
                    $message = sprintf("<strong>%s</strong>", $this->view->translate("Les modifications n'ont pas pu être enregistrées."));
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
            } else {
                $message = sprintf("<strong>%s</strong>", $this->view->translate("Le formulaire comporte des erreurs."));
                $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            }
        }

        if ($defaults) {
            $form->setDefaults($defaults);
        }
        $this->view->form = $form;
    }
}
