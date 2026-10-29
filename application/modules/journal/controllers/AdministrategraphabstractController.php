<?php

class AdministrategraphabstractController extends Zend_Controller_Action
{
    public const PATH_FILE = REVIEW_PATH . "public";
    public const DOCUMENT_COL = "DOCUMENT";
    public const JSON_PATH_ABS_FILE = Episciences_Paper::JSON_PATH_ABS_FILE;

    public const FILE_SIZE = 100000;

    /**
     * Accepted MIME types, detected from the file content, and the extension the file is stored with.
     * SVG is deliberately excluded: it can embed scripts and is served from the journal's domain.
     */
    public const ACCEPTED_MIME = [
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function addgraphabsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        // Reject the request if it is not a proper AJAX POST with a valid request
        // token, OR the user is not authorised.
        if (
            (!$request->isXmlHttpRequest() || !$request->isPost())
            || !Episciences_Csrf_Helper::validateRequestToken($request)
            || !$this->canManagePaper((int)$request->getPost('docId'))
        ) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Erreur: modification non autorisée');
            exit();
        }
        $docId = (int)$request->getPost('docId');
        $upload = new Zend_File_Transfer_Adapter_Http();
        $file = $upload->getFileInfo();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($file['file']['size'] >= self::FILE_SIZE) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Fichier trop volumineux 100 ko max autorisé');
            exit();
        }
        if (0 < $file['file']['error']) {
            echo 'Error: ' . $file['file']['error'] . '<br>';
        } else {
            $ext = self::getAcceptedImageExtension($file['file']);
            if ($ext === null) {
                echo json_encode([false], JSON_THROW_ON_ERROR);
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Fichier non accepté');
                exit();
            }
            if (!is_dir($docPath = self::PATH_FILE . "/documents") && !mkdir($docPath, 0775) && !is_dir($docPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', self::PATH_FILE . "/documents/"));
            }
            if (!is_dir($pathFolder = self::PATH_FILE . "/documents" . "/" . $docId) && !mkdir($pathFolder, 0775) && !is_dir($pathFolder)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', self::PATH_FILE . "/documents/" . $docId));
            }
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
        // Reject the request if it is not a proper AJAX POST with a valid request
        // token, OR the user is not authorised.
        if (
            (!$request->isXmlHttpRequest() || !$request->isPost())
            || !Episciences_Csrf_Helper::validateRequestToken($request)
            || !$this->canManagePaper((int)$request->getPost('docId'))
        ) {
            echo json_encode([false], JSON_THROW_ON_ERROR);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage('Erreur: modification non autorisée');
            exit();
        }
        $docId = (int)$request->getPost('docId');
        $file = basename((string)$request->getPost('file'));
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

    /**
     * Returns the extension to store an uploaded file with, or null when it is not an uploaded
     * image of an accepted type.
     *
     * @param array<string, mixed> $file entry of the uploaded file (name, tmp_name, error, ...)
     */
    private static function getAcceptedImageExtension(array $file): ?string
    {
        $tmpName = (string)($file['tmp_name'] ?? '');

        return $tmpName !== '' && is_uploaded_file($tmpName) ? self::detectImageExtension($tmpName) : null;
    }

    /**
     * Extension derived from the actual content of the file (never from its original name),
     * or null when its MIME type is not accepted.
     */
    private static function detectImageExtension(string $path): ?string
    {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mimeType) ? (self::ACCEPTED_MIME[$mimeType] ?? null) : null;
    }

    private function canManagePaper(int $docId): bool
    {
        if (!Episciences_Auth::isLogged() || $docId <= 0) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId, false, RVID);
        if (!$paper instanceof Episciences_Paper) {
            return false;
        }

        return Episciences_Auth::isSecretary()
            || $paper->isEditor((int)Episciences_Auth::getUid())
            || (!$paper->isPublished() && !$paper->isRemoved() && $paper->isOwnerOrCoAuthor());
    }
}