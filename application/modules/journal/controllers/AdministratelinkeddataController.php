<?php

class AdministratelinkeddataController extends Episciences_Controller_Action
{
    public function addldAction(){
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ((!$request->isXmlHttpRequest() || !$request->isPost()) && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Erreur: modification non autorisée');
            return;
        }
        $inputTypeLd = filter_var(trim($this->getRequest()->getPost('typeld')), FILTER_SANITIZE_SPECIAL_CHARS);
        $rawValueLd = str_replace(' ','',trim($this->getRequest()->getPost('valueld')));
        $docId = (int)$this->getRequest()->getPost('docId');
        $paperId = filter_var(trim($this->getRequest()->getPost('paperId')), FILTER_SANITIZE_SPECIAL_CHARS);
        $relationship = htmlspecialchars(trim($this->getRequest()->getPost('relationship')), ENT_QUOTES, 'UTF-8');
        
        // Validate the format first with raw input, then sanitize for storage
        $typeLd = Episciences_Tools::checkValueType($rawValueLd);
        if ($typeLd === false) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Format de donnée non reconnu');
            return;
        }
        
        // Now sanitize for safe storage after validation passes
        $valueLd = htmlspecialchars($rawValueLd, ENT_QUOTES, 'UTF-8');
        $idMetaDataLastId = null;
        $arraySoftware = [];
        $versionHal = 0;
        // isolate hal id if url is given here
        if ($typeLd === 'url') {
            $getHalIdentifierInUrl = Episciences_Tools::getHalIdInString($rawValueLd);
            if (!empty($getHalIdentifierInUrl)) {
                $typeLd = 'hal';
                $rawValueLd = $getHalIdentifierInUrl[0];
                $valueLd = htmlspecialchars($rawValueLd, ENT_QUOTES, 'UTF-8');
                if (isset($getHalIdentifierInUrl[1])) {
                    $rawValueLd = str_replace($getHalIdentifierInUrl[1],'',$rawValueLd);
                    $valueLd = htmlspecialchars($rawValueLd, ENT_QUOTES, 'UTF-8');
                    $versionHal = (int)str_replace('v','',$getHalIdentifierInUrl[1]);
                }
            }
            //isolate swh in url
            $getSwhDirIdentifierInUrl = Episciences_Tools::getSoftwareHeritageDirId($rawValueLd);
            if (!empty($getSwhDirIdentifierInUrl)) {
                $typeLd = 'software';
                $rawValueLd = $getSwhDirIdentifierInUrl[0];
                $valueLd = htmlspecialchars($rawValueLd, ENT_QUOTES, 'UTF-8');
            }
        }
        if ($inputTypeLd === 'software' && $typeLd === 'hal') {
            $getHalIdentifierInUrl = Episciences_Tools::getHalIdInString($rawValueLd);
            $valueWithVers = $rawValueLd;
            if (!empty($getHalIdentifierInUrl)) {
                $rawValueLd = $getHalIdentifierInUrl[0];
                if (isset($getHalIdentifierInUrl[1])) {
                    $rawValueLd = str_replace($getHalIdentifierInUrl[1],'',$rawValueLd);
                    $versionHal = (int)str_replace('v','',$getHalIdentifierInUrl[1]);
                }
            }
            $citationFull = json_decode(Episciences_SoftwareHeritageTools::getCitationsFullFromHal($rawValueLd,$versionHal));
            if (!empty($citationFull) && !empty($citationFull->response->docs[0])){
                $citationDocType = $citationFull->response->docs[0]->docType_s;
                if ($citationDocType !== "SOFTWARE"){
                    echo json_encode([false], JSON_THROW_ON_ERROR);
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("L'identifiant HAL n'est pas de type logiciel");
                    return;
                }
                $arraySoftware['citationFull'] = $citationFull->response->docs[0]->citationFull_s;
                if (!empty($citationFull->response->docs[0]->swhidId_s)){
                    $arraySoftware['swhidId'] = $citationFull->response->docs[0]->swhidId_s[0];
                }
            }
            $codeMetaFromHal = Episciences_SoftwareHeritageTools::getCodeMetaFromHal($valueWithVers);
            $codeMetaFromHal = json_decode($codeMetaFromHal, true);
            if ($codeMetaFromHal !== ''){
                $arraySoftware['codemeta'] = $codeMetaFromHal;
            }
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(json_encode($arraySoftware, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        } elseif (($inputTypeLd === 'dataset' || $inputTypeLd === 'publication') && $typeLd === 'hal') {
            $getHalIdentifierInUrl = Episciences_Tools::getHalIdInString($rawValueLd);
            if (!empty($getHalIdentifierInUrl)) {
                $typeLd = 'hal';
                $rawValueLd = $getHalIdentifierInUrl[0];
                if (isset($getHalIdentifierInUrl[1])) {
                    $rawValueLd = str_replace($getHalIdentifierInUrl[1],'',$rawValueLd);
                    $versionHal = (int)str_replace('v','',$getHalIdentifierInUrl[1]);
                }
            }
            $citationFull = json_decode(Episciences_SoftwareHeritageTools::getCitationsFullFromHal($rawValueLd,$versionHal));
            $arraySoftware['citationFull'] = $citationFull->response->docs[0]->citationFull_s;
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(json_encode($arraySoftware));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        }
        $checkArxivUrl = Episciences_Tools::checkIsArxivUrl($rawValueLd);
        if ($checkArxivUrl){
            $typeLd = 'arxiv';
            $rawValueLd = $checkArxivUrl[1];
        }
        if (($typeLd === 'arxiv' && $inputTypeLd === 'software') ||
            ($typeLd === 'doi' && !empty(Episciences_Tools::checkIsDoiFromArxiv($rawValueLd)) && $inputTypeLd === 'software')) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("L'archive ArXiv ne contient pas de logiciel");
           return;
        }


        if($typeLd === 'doi' || Episciences_Tools::isDoiWithUrl($rawValueLd)) {

            $result = Episciences_DoiTools::getMetadataFromDoi($rawValueLd);

            if(empty($result)) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_WARNING)->addMessage("Échec de l'ajout de la donnée liée. Veuillez réessayer.");
                return;
            }

            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(Episciences_DoiTools::getMetadataFromDoi($rawValueLd));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        }
        if ($typeLd === 'arxiv' && $inputTypeLd !== 'software') {
            $epiDM = new Episciences_Paper_DatasetMetadata();
            $epiDM->setMetatext(Episciences_DoiTools::getMetadataFromDoi($rawValueLd));
            $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
        }
        
        if ($inputTypeLd === 'software' && $typeLd !== false && $typeLd !== 'hal') {
            $typeLd = 'software';
        }

        // Final sanitization before database storage
        $valueLd = htmlspecialchars($rawValueLd, ENT_QUOTES, 'UTF-8');

        if (Episciences_Paper_DatasetsManager::addDatasetFromSubmission($docId, $typeLd, $valueLd, $inputTypeLd, $idMetaDataLastId, ['relationship' => $relationship]) > 0) {
            Episciences_PapersManager::updateJsonDocumentData($docId);
            Episciences_Paper_Logger::log($paperId,$docId,Episciences_Paper_Logger::CODE_LD_ADDED,Episciences_Auth::getUid(), json_encode(['typeLd' => $typeLd,'valueLd' => $valueLd,'relationship' => $relationship,'docId'=>$docId,'paperId' => $paperId,'username' => Episciences_Auth::getFullName()]));
            echo json_encode([true], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage('Ajout de la donnée liée bien prise en compte');
        } else {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_WARNING)->addMessage('Donnée liée déjà existante, veuillez changer ses informations via le formulaire');
        }
    }
    public function removeldAction(){
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ((!$request->isXmlHttpRequest() || !$request->isPost()) && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Erreur: modification non autorisée');
            return;
        }

        $docId = (int)$request->getPost('docId');
        $paperId = filter_var(trim($request->getPost('paperId')), FILTER_SANITIZE_SPECIAL_CHARS);
        $idLd = filter_var($request->getPost('id'), FILTER_SANITIZE_NUMBER_INT);
        /** @var Episciences_Paper_Dataset $datasetInDb */
        $datasetInDb = Episciences_Paper_DatasetsManager::findById($idLd);
        $typeLd = htmlspecialchars($datasetInDb->getName(), ENT_QUOTES, 'UTF-8');
        $valueLd = htmlspecialchars($datasetInDb->getValue(), ENT_QUOTES, 'UTF-8');
        if (($ds = $datasetInDb->getIdPaperDatasetsMeta()) !== null){
            $isDeleted = Episciences_Paper_DatasetsMetadataManager::deleteMetaDataAndDatasetsByIdMd((int)$ds);
        } else {
            $isDeleted = Episciences_Paper_DatasetsManager::deleteById((int)$idLd);
        }
        if ($isDeleted) {
            Episciences_PapersManager::updateJsonDocumentData($docId);
            Episciences_Paper_Logger::log($paperId,$docId,Episciences_Paper_Logger::CODE_LD_REMOVED,Episciences_Auth::getUid(), json_encode(['typeLd' => $typeLd,'valueLd' => $valueLd,'docId'=>$docId,'paperId' => $paperId,'username' => Episciences_Auth::getFullName()]));

            echo json_encode([true], JSON_THROW_ON_ERROR);
        } else {
            echo json_encode([false], JSON_THROW_ON_ERROR);
        }
    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage('Suppression de la donnée liée bien prise en compte');
    }

    public function ajaxgetldformAction()
    {
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        if (($request->isXmlHttpRequest() || $request->isPost())
            && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            $valueLd = '';
            $idLd = '';
            $disabledValue = false;
            $idForm = "addld";
            if ($request->getPost('option') !== null){
                $ldOptions = $request->getPost('option');
                if ($ldOptions['modifyForm'] === "true"){
                    $idForm = "modifyLd";
                }
                if (isset($ldOptions['valueLd'])) {
                    $valueLd = $ldOptions['valueLd'];
                    $disabledValue = true;
                }
                if (isset($ldOptions['idLd'])) {
                    $idLd = $ldOptions['idLd'];
                }
            }
            $this->view->supportedRelationShips = Episciences_Paper_Dataset::getSupportedRelationShips();
            $this->view->disabledValue = $disabledValue;
            $this->view->valueLd = $valueLd;
            $this->view->formId = $idForm;
            $this->view->idLd = $idLd;
            $this->view->typeld = $request->getPost('typeld');
            $this->view->placeholder = $request->getPost('placeholder');
            $this->renderScript('paper/paper_manage_datasets.phtml');
        }
    }

    public function setnewinfoldAction() {
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (($request->isXmlHttpRequest() || $request->isPost())
            && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            
            // Sanitize all input parameters
            $ldId = filter_var($request->getPost('ldId'), FILTER_SANITIZE_NUMBER_INT);
            $relationship = htmlspecialchars(trim($request->getPost('relationship')), ENT_QUOTES, 'UTF-8');
            $typeLd = filter_var(trim($request->getPost('typeld')), FILTER_SANITIZE_SPECIAL_CHARS);
            $valueLd = htmlspecialchars(trim($request->getPost('valueLd')), ENT_QUOTES, 'UTF-8');
            $docId = filter_var($request->getPost('docId'), FILTER_SANITIZE_NUMBER_INT);
            $paperId = filter_var(trim($request->getPost('paperId')), FILTER_SANITIZE_SPECIAL_CHARS);
            
            $ld = new Episciences_Paper_Dataset();
            $ld->setId($ldId);
            $ld->setRelationship($relationship);
            $ld->setCode($typeLd);
            $ld->setSourceId(Episciences_Repositories::EPI_USER_ID);
            if (Episciences_Paper_DatasetsManager::updateRelationAndTypeById($ld) > 0){
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage("Modification de la donnée liée bien prise en compte");
                Episciences_Paper_Logger::log($paperId, $docId, Episciences_Paper_Logger::CODE_LD_CHANGED, Episciences_Auth::getUid(),
                    json_encode(['typeLd' => $typeLd,
                        'valueLd' => $valueLd,
                        'relationship' => $relationship,
                        'docId' => $docId,
                        'paperId' => $paperId,
                        'username' => Episciences_Auth::getFullName()]));
                return json_encode([true], JSON_THROW_ON_ERROR);
            }
        }
        return json_encode([false], JSON_THROW_ON_ERROR);
    }

}