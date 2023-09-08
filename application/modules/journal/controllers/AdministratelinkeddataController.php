<?php

class AdministratelinkeddataController extends Zend_Controller_Action
{
    public function addldAction(){
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ((!$request->isXmlHttpRequest() || !$request->isPost()) && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace('danger')->addMessage('Modification non autorisé');
            exit();
        }

        $typeLd = $this->getRequest()->getPost('typeld');
        $valueLd = $this->getRequest()->getPost('valueld');
        $docId = $this->getRequest()->getPost('docId');
        $paperId = $this->getRequest()->getPost('paperId');
        $typeLd = Episciences_Tools::checkValueType($valueLd);
        if ($typeLd === false) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace('danger')->addMessage('Format de donnée non reconnu');
            exit();
        }
        if (Episciences_Paper_DatasetsManager::addDatasetFromSubmission($docId,$typeLd,$valueLd) > 0) {
            Episciences_Paper_Logger::log($paperId,$docId,Episciences_Paper_Logger::CODE_LD_ADDED,Episciences_Auth::getUid(), json_encode(['typeLd' => $typeLd,'valueLd' => $valueLd,'docId'=>$docId,'paperId' => $paperId,'username' => Episciences_Auth::getFullName()]));
            echo json_encode([true], JSON_THROW_ON_ERROR);
        }
        $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Ajout de la donnée liée bien prise en compte');
        exit();
    }
    public function removeldAction(){
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ((!$request->isXmlHttpRequest() || !$request->isPost()) && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace('danger')->addMessage('Modification non autorisé');
            exit();
        }

        $docId = $request->getPost('docId');
        $paperId = $request->getPost('paperId');
        $idLd = $request->getPost('id');
        /** @var Episciences_Paper_Dataset $datasetInDb */
        $datasetInDb = Episciences_Paper_DatasetsManager::findById($idLd);
        $typeLd = $datasetInDb->getName();
        $valueLd = $datasetInDb->getValue();
        /** @var Zend_Controller_Request_Http $request */
        if (Episciences_Paper_DatasetsManager::deleteById($idLd)){
            Episciences_Paper_Logger::log($paperId,$docId,Episciences_Paper_Logger::CODE_LD_REMOVED,Episciences_Auth::getUid(), json_encode(['typeLd' => $typeLd,'valueLd' => $valueLd,'docId'=>$docId,'paperId' => $paperId,'username' => Episciences_Auth::getFullName()]));

            echo json_encode([true], JSON_THROW_ON_ERROR);
        } else {
            echo json_encode([false], JSON_THROW_ON_ERROR);
        }
        $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Suppression de la donnée liée bien prise en compte');
        exit();
    }

}