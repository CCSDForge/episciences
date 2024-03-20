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
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Modification non autorisé');
            exit();
        }
        $inputTypeLd = $this->getRequest()->getPost('typeld');
        $valueLd = $this->getRequest()->getPost('valueld');
        $docId = $this->getRequest()->getPost('docId');
        $paperId = $this->getRequest()->getPost('paperId');
        $typeLd = Episciences_Tools::checkValueType($valueLd);
        if ($typeLd === false) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Format de donnée non reconnu');
            exit();
        }
        $idMetaDataLastId = null;
        $arraySoftware = [];
        $versionHal = 0;
        // isolate hal id if url is given here
        if ($typeLd === 'url') {
            $getHalIdentifierInUrl = Episciences_Tools::getHalIdInString($valueLd);
            if (!empty($getHalIdentifierInUrl)) {
                $typeLd = 'hal';
                $valueLd = $getHalIdentifierInUrl[0];
                if (isset($getHalIdentifierInUrl[1])) {
                    $valueLd = str_replace($getHalIdentifierInUrl[1],'',$valueLd);
                    $versionHal = (int)str_replace('v','',$getHalIdentifierInUrl[1]);
                }
            }
            //isolate swh in url
            $getSwhDirIdentifierInUrl = Episciences_Tools::getSoftwareHeritageDirId($valueLd);
            if (!empty($getSwhDirIdentifierInUrl)) {
                $typeLd = 'software';
                $valueLd = $getSwhDirIdentifierInUrl[0];
            }
        }
        if ($inputTypeLd === 'software' && $typeLd === 'hal') {
            $citationFull = json_decode(Episciences_SoftwareHeritageTools::getCitationsFullFromHal($valueLd,$versionHal));
            if (!empty($citationFull) && !empty($citationFull->response->docs[0])){
                $citationDocType = $citationFull->response->docs[0]->docType_s;
                if ($citationDocType !== "SOFTWARE"){
                    echo json_encode([false], JSON_THROW_ON_ERROR);
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("L'identifiant HAL n'est pas de type logiciel");
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
            $citationFull = json_decode(Episciences_SoftwareHeritageTools::getCitationsFullFromHal($valueLd,$versionHal));
            $arraySoftware['citationFull'] = $citationFull->response->docs[0]->citationFull_s;
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(json_encode($arraySoftware));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        }
        $checkArxivUrl = Episciences_Tools::checkIsArxivUrl($valueLd);
        if ($checkArxivUrl){
            $typeLd = 'arxiv';
            $valueLd = $checkArxivUrl[1];
        }
        if (($typeLd === 'arxiv' && $inputTypeLd === 'software') ||
            ($typeLd === 'doi' && !empty(Episciences_Tools::checkIsDoiFromArxiv($valueLd)) && $inputTypeLd === 'software')) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("L'archive ArXiv ne contient pas de logiciel");
            exit();
        }


        if($typeLd === 'doi' || Episciences_Tools::isDoiWithUrl($valueLd)) {

            $result = Episciences_DoiTools::getMetadataFromDoi($valueLd);

            if(empty($result)) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_WARNING)->addMessage("Échec de l'ajout de la donnée liée. Veuillez réessayer.");
                return;
            }

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
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage('Ajout de la donnée liée bien prise en compte');
        exit();
    }
    public function removeldAction(){
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ((!$request->isXmlHttpRequest() || !$request->isPost()) && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Modification non autorisé');
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
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage('Suppression de la donnée liée bien prise en compte');
        exit();
    }

}