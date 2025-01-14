<?php

class AdministrategraphabstractController extends Zend_Controller_Action
{
    public const PATH_FILE = REVIEW_PATH . "public";
    public const DOCUMENT_COL = "DOCUMENT";
    public const JSON_PATH_ABS_FILE = Episciences_Paper::JSON_PATH_ABS_FILE;

    public const FILE_SIZE = 100000;

    public const ACCEPTED_MIME = [
        "image/gif",
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/svg+xml",
        "image/webp",
        "image/bmp",
        "gif",
        "jpeg",
        "jpg",
        "png",
        "svg",
        "webp",
        "bmp",
    ];

    public function addgraphabsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ((!$request->isXmlHttpRequest() || !$request->isPost()) && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Erreur: modification non autorisée');
            exit();
        }
        $docId = $request->getPost('docId');
        $upload = new Zend_File_Transfer_Adapter_Http();
        $file = $upload->getFileInfo();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($file['file']['size'] >= self::FILE_SIZE) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Fichier trop volumineux 100 ko max autorisé');
            exit();
        }
        if (!in_array(pathinfo($file['file']['name'])['extension'],self::ACCEPTED_MIME,true)){
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Fichier non accepté');
            exit();
        }
        if (0 < $file['file']['error']) {
            echo 'Error: ' . $file['file']['error'] . '<br>';
        } else {
            if (!is_dir($docPath = self::PATH_FILE . "/documents") && !mkdir($docPath, 0775) && !is_dir($docPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', self::PATH_FILE . "/documents/"));
            }
            if (!is_dir($pathFolder = self::PATH_FILE . "/documents" . "/" . $docId) && !mkdir($pathFolder, 0775) && !is_dir($pathFolder)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', self::PATH_FILE . "/documents/" . $docId));
            }
            $ext = pathinfo($file['file']['name'], PATHINFO_EXTENSION);

            $query = $db->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(".self::DOCUMENT_COL.", ".$db->quote(self::JSON_PATH_ABS_FILE).")) FROM ".T_PAPERS." WHERE DOCID = ?",[$docId]);
            $oldFile = "";
            foreach ($query->fetch() as $val)
            {
                $oldFile = $val;
                $fileName = "graphical_abstract." . $ext;
                if (is_null($val) || $val !== $fileName) {
                    $db->query("UPDATE ".T_PAPERS." SET DOCUMENT = JSON_SET(".self::DOCUMENT_COL.", ".$db->quote(self::JSON_PATH_ABS_FILE).",".$db->quote($fileName)
                        .") WHERE DOCID = ?",[$docId]);
                }
            }
            foreach (glob(self::PATH_FILE . "/documents/" . $docId . "/". $oldFile) as $filename) {
                if (is_file($filename)) {
                    unlink($filename);
                }
            }
            move_uploaded_file($file['file']['tmp_name'], self::PATH_FILE . "/documents/" . $docId . "/graphical_abstract." . $ext);
        }
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage("Ajout de l'abstract graphique réussi");
        exit();
    }

    public function deletegraphabsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ((!$request->isXmlHttpRequest() || !$request->isPost()) && (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Erreur: modification non autorisée');
            exit();
        }
        $docId = $request->getPost('docId');
        $file = $request->getPost('file');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(".self::DOCUMENT_COL.", ".$db->quote(self::JSON_PATH_ABS_FILE).")) FROM ".T_PAPERS." WHERE DOCID = ?",[$docId]);
        if (!empty($fetch = $query->fetch())){
            $fileFetched = '';
            foreach ($fetch as $value) {
                $fileFetched = $value;
            }
            if ($fileFetched === $file){
                $db->query("UPDATE ".T_PAPERS." SET DOCUMENT =
                JSON_REMOVE(".self::DOCUMENT_COL.", ".$db->quote(self::JSON_PATH_ABS_FILE).") WHERE DOCID = ?",[$docId]);
                unlink(self::PATH_FILE . "/documents" . "/" . $docId . '/' . $file);
            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage("Fichier a supprimer inconnu");
                exit();
            }

        }
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage("Suppression de l'abstract graphique réussi");
        exit();
    }
}