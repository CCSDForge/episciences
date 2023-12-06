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
        $inputTypeLd = $this->getRequest()->getPost('typeld');
        $valueLd = $this->getRequest()->getPost('valueld');
        $docId = $this->getRequest()->getPost('docId');
        $paperId = $this->getRequest()->getPost('paperId');
        $typeLd = Episciences_Tools::checkValueType($valueLd);
        if ($typeLd === false) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace('danger')->addMessage('Format de donnée non reconnu');
            exit();
        }
        $idMetaDataLastId = "";
        $arraySoftware = [];
        if ($inputTypeLd === 'software' && $typeLd === 'hal'){
            $citationFull = json_decode(Episciences_SoftwareHeritageTools::getCitationsFullFromHal($valueLd));
            if (!empty($citationFull) && !empty($citationFull->response->docs[0])){
                $citationDocType = $citationFull->response->docs[0]->docType_s;
                if ($citationDocType !== "SOFTWARE"){
                    echo json_encode([false], JSON_THROW_ON_ERROR);
                    $this->_helper->FlashMessenger->setNamespace('danger')->addMessage("L'identifiant HAL n'est pas de type logiciel");
                    exit();
                }
                $arraySoftware['citationFull'] = $citationFull->response->docs[0]->citationFull_s;
                if (!empty($citationFull->response->docs[0]->swhidId_s)){
                    $arraySoftware['swhidId'] = $citationFull->response->docs[0]->swhidId_s[0];
                }
            }
            $codeMetaFromHal = Episciences_SoftwareHeritageTools::getCodeMetaFromHal($valueLd);
            $codeMetaFromHal = json_decode($codeMetaFromHal, true);
            if ($codeMetaFromHal !== ''){
                $arraySoftware['codemeta'] = $codeMetaFromHal;
            }
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(json_encode($arraySoftware, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        } elseif (($inputTypeLd === 'dataset' || $inputTypeLd === 'publication') && $typeLd === 'hal') {

            $citationFull = json_decode(Episciences_SoftwareHeritageTools::getCitationsFullFromHal($valueLd));
            $arraySoftware['citationFull'] = $citationFull->response->docs[0]->citationFull_s;
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(json_encode($arraySoftware));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        }

        if ($inputTypeLd === 'software' && $typeLd === 'software' && Episciences_Paper_DatasetsManager::CheckSwhidType($valueLd) === 'dir') {
            $codeMetaFromDir = Episciences_SoftwareHeritageTools::getCodeMetaFromDirSwh($valueLd);
            if ($codeMetaFromDir !== '') {
                $epiDM = new Episciences_Paper_DatasetMetadata();
                $epiDM->setMetatext($codeMetaFromDir);
                $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
            }
        }
        if($typeLd === 'doi' || Episciences_Tools::isDoiWithUrl($valueLd)) {
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(Episciences_DoiTools::getMetadataFromDoi($valueLd));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        }
        if ($typeLd === 'arxiv' && $inputTypeLd !== 'software') {
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(Episciences_DoiTools::getMetadataFromDoi($valueLd));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        }
        
        if ($inputTypeLd === 'software' && $typeLd !== false && $typeLd !== 'hal') {
            $typeLd = 'software';
        }



        if (Episciences_Paper_DatasetsManager::addDatasetFromSubmission($docId, $typeLd, $valueLd, $inputTypeLd, $idMetaDataLastId) > 0) {
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
        if (($ds = $datasetInDb->getIdPaperDatasetsMeta()) !== null){
            $isDeleted = Episciences_Paper_DatasetsMetadataManager::deleteMetaDataAndDatasetsByIdMd((int)$ds);
        } else {
            $isDeleted = Episciences_Paper_DatasetsManager::deleteById((int)$idLd);
        }
        if ($isDeleted) {
            Episciences_Paper_Logger::log($paperId,$docId,Episciences_Paper_Logger::CODE_LD_REMOVED,Episciences_Auth::getUid(), json_encode(['typeLd' => $typeLd,'valueLd' => $valueLd,'docId'=>$docId,'paperId' => $paperId,'username' => Episciences_Auth::getFullName()]));

            echo json_encode([true], JSON_THROW_ON_ERROR);
        } else {
            echo json_encode([false], JSON_THROW_ON_ERROR);
        }
        $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Suppression de la donnée liée bien prise en compte');
        exit();
    }

}