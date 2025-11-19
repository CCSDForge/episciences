<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class FileController extends DefaultController
{
    public const APPLICATION_OCTET_STREAM = 'application/octet-stream';

    public function indexAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;
        $path = REVIEW_FILES_PATH;
        $filepath = $path . $file;

        $this->loadFile($filepath);
    }

    public function docfilesAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $folder = ($params['folder'] === 'ce') ? Episciences_CommentsManager::COPY_EDITING_SOURCES : $params['folder'];
        $parentCommentId = $params['parentCommentId'] ?? null;
        $docId = $params['docId'];
        $filename = $params['filename'];
        $extension = $params['extension'] ?? null;

        $file = $filename;

        if (!empty($extension)) {
            $file .= '.' . $extension;
        }

        $path = Episciences_PapersManager::buildDocumentPath($docId) . '/' . $folder . '/';

        if (null !== $parentCommentId) {
            $path .= $parentCommentId . '/';
        }

        $filepath = $path . $file;

        $this->loadFile($filepath);
    }

    // load an e-mail attached file

    /**
     * @throws Exception
     */
    public function attachmentsAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $subDirectories = $params['sub_directories'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;
        $path = REVIEW_FILES_PATH . Episciences_Mail_Send::ATTACHMENTS . DIRECTORY_SEPARATOR . $subDirectories;
        $filepath = $path . $file;

        $this->loadFile($filepath, true);
    }

    // Accès à la version temporaire d'un document
    public function tmpAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $docId = $params['docId'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;

        $path = Episciences_PapersManager::buildDocumentPath($docId) . '/tmp/';
        $filepath = $path . $file;

        $this->loadFile($filepath);
    }

    // Accès aux fichiers joints d'un document
    public function paperAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $docId = $params['docId'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;
        $path = Episciences_PapersManager::buildDocumentPath($docId) . '/ratings/';
        $filepath = $path . $file;

        $this->loadFile($filepath);
    }

    // access to a rating report attachment
    public function reportAction(): void
    {
        $params = $this->getRequest()->getParams();

        $docid = $params['docid'];
        $id = $params['id'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;

        // check if report exists
        $report = Episciences_Rating_Report::findById($id);
        if (!$report) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->view->message = "Fichier introuvable";
            $this->view->description = "Le fichier demandé n'existe pas, ou bien vous n'avez pas les autorisations nécessaires pour y accéder.";
            $this->renderScript('error/error.phtml');
            return;
        }

        $filepath = REVIEW_FILES_PATH . $report->getDocid() . '/reports/' . $report->getUid() . '/' . $file;
        if (!file_exists($filepath)) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->view->message = "Fichier introuvable";
            $this->view->description = "Le fichier demandé n'existe pas, ou bien vous n'avez pas les autorisations nécessaires pour y accéder.";
            $this->renderScript('error/error.phtml');
            return;
        }

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $this->loadFile($filepath);
    }

    /**
     * @param string $filepath
     * @param bool $forceDownload
     */
    protected function loadFile(string $filepath, bool $forceDownload = false): void
    {
        if (is_readable($filepath)) {
            $this->openFile($filepath, $forceDownload);
        } else {
            $message = '<strong>' . $this->view->translate("Le fichier n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('notfound', 'index');
        }
    }

    /**
     * Reads a file and writes it to the output buffer
     * @param string $file
     * @param bool $forceDownload
     */
    protected function openFile(string $file, bool $forceDownload = false): void
    {
        $contentType = Episciences_Tools::getMimeType($file);
        $downloadableFilename = '"' . basename($file) . '"';

        if ($contentType === self::APPLICATION_OCTET_STREAM) {
            // force download because application/octet-stream would burn user eyes anyway
            $forceDownload = true;
        } else {
            header("Content-Disposition: inline; filename=$downloadableFilename");
        }

        if ($forceDownload) {
            header('Content-Description: File Transfer');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Disposition: attachment;filename=' . $downloadableFilename);
        }

        header("Content-Type: " . $contentType);
        header("Content-Length: " . filesize($file));
        ob_clean();
        flush();
        readfile($file);
    }

    /**
     *
     */
    public function uploadAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $result = [];

        if ($this->isPostMaxSizeReached()) {
            $message = $this->view->translate('La taille maximale des fichiers que vous pouvez télécharger est limitée à');
            $message .= ' ';
            $message .= '<code>' . Episciences_Tools::toHumanReadable(MAX_FILE_SIZE) . '</code>';
            $result ['status'] = 'error';
            $result ['messages'][] = $message;
        } else {
            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();
            // Copy editing : modifier le path
            $path = (string)$request->getPost('path');
            $pcId = (int)$request->getPost('pcId');
            $docId = (int)$request->getPost('docId');
            $paperId = (int)$request->getPost('paperId');

            try {
                $adapter = new Zend_File_Transfer_Adapter_Http();
                $adapter->addValidators([
                    ['FilesSize', false, MAX_FILE_SIZE],
                    ['Extension', false, ALLOWED_EXTENSIONS],
                    ['MimeType', false, ALLOWED_MIMES_TYPES]
                ]);

                $files = $adapter->getFileInfo();
                foreach ($files as $info) {

                    $filename = Ccsd_Tools::cleanFileName($info['name']);

                    if (!$adapter->isUploaded() || !$adapter->isValid()) {
                        $result ['status'] = 'error';
                        $result['messages'] = $adapter->getMessages();
                        break;
                    }

                    // todo : créer classe upload
                    // todo: créer classe filesManager
                    // todo: prendre le chemin en paramètre

                    $sFolder = $this->buildStorageFolder($path, $paperId, $docId, $pcId);

                    Episciences_Tools::recursiveMkdir($sFolder);

                    // only for files uploaded in attachments directory

                    if (empty($path)) {
                        Episciences_Auth::setCurrentAttachmentsPathInSession($sFolder);
                    } else {
                        Episciences_Auth::resetCurrentAttachmentsPath();
                    }

                    // rotate filename
                    $filename = Episciences_Tools::filenameRotate($sFolder, $filename);

                    // move file to tmp folder
                    if (move_uploaded_file($info['tmp_name'], $sFolder . $filename)) {
                        $result['status'] = 'success';
                        $result['filename'] = $filename;
                        $result['fileUrl'] = $this->buildFileUrl($filename, $path, $paperId, $docId, $pcId);
                    }
                }

            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['messages'] = $e->getMessage();
            }

        }

        echo Zend_Json::encode($result);

    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    public function deleteAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // attachments path
        $path = (string)$request->getPost('path');
        $filename = $request->getPost('file');
        $docId = (int)$request->get('docId');
        $pcId = (int)$request->getPost('pcId');
        $paperId = (int)$request->getpost('paperId');

        $path = $this->buildStorageFolder($path, $paperId, $docId, $pcId, true);
        $filepath = $path . $filename;

        if ($filename && is_file($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * @throws GuzzleException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function oafilesAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $params = $request->getParams();

        $docId = $params['docId'];
        $filename = $params['filename'];
        $extension = $params['first-extension'];

        if (isset($params['latest-extension'])) {
            $extension .= '.';
            $extension .= $params['latest-extension'];
        }

        $file = $filename . '.' . $extension;
        $paper = Episciences_PapersManager::get($docId);

        // check if paper exists
        if (!$paper || !$paper->hasHook || ($paper->getRvid() !== RVID) || ($paper->getRepoid() === 0)) {
            $this->getResponse()?->setHttpResponseCode(404);
            $this->renderScript('index/notfound.phtml');
            return;
        }

        $this->redirectsIfHaveNotEnoughPermissions($paper);

        $this->redirectWithFlashMessageIfPaperIsRemovedOrDeleted($paper);

        /** @var Episciences_Paper_File | null $oFile */
        $oFile = $paper->getFileByName($file);

        if (!$oFile) {
            $message = $this->view->translate("Le document demandé a été supprimé par son auteur.");
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->redirect('/');
            return;
        }

        $url = $oFile->getSelfLink();

        $mainDocumentContent = $this->getMainDocumentContent($paper, $url);

        $this->updatePaperStats($paper, Episciences_Paper_Visits::CONSULT_TYPE_FILE);
        header("Content-Disposition: attachment; filename=$file");
        header('Content-type: ' . $oFile->getFileType());
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $mainDocumentContent;

    }

    /**
     * return storage folder
     * @param string $path
     * @param int $paperId
     * @param int $docId
     * @param int $pcId
     * @return string
     * @throws Exception
     */
    private function buildStorageFolder(string $path = "", int $paperId = 0, int $docId = 0, int $pcId = 0): string
    {
        if ($docId && !$paperId) {
            $paper = Episciences_PapersManager::get($docId);

            if ($paper) {
                $paperId = $paper->getPaperid();
            }
        }

        if ($path === 'tmp_attachments') {
            $folder = $paperId . '/tmp/';
        } elseif ($path === 'comment_attachments') {
            $folder = $docId . '/comments/';
        } elseif ($path === 'ce_attachments') {
            $folder = $docId;
            $folder .= DIRECTORY_SEPARATOR;
            $folder .= Episciences_CommentsManager::COPY_EDITING_SOURCES;
            $folder .= DIRECTORY_SEPARATOR;
            $folder .= $pcId;
            $folder .= DIRECTORY_SEPARATOR;
        } else {
            return Episciences_Tools::getAttachmentsPath((string)$paperId);
        }
        return REVIEW_FILES_PATH . $folder;
    }

    /**
     * return file url
     * @param string $fileName
     * @param string $path
     * @param int $paperId
     * @param int $docId
     * @param int $pcId
     * @return string
     * @throws Exception
     */
    private function buildFileUrl(string $fileName, string $path, int $paperId, int $docId, int $pcId): string
    {
        if ($path === 'tmp_attachments') {
            $fileUrl = '/tmp_files/' . $paperId . '/' . $fileName;
        } elseif ($path === 'comment_attachments') {
            $fileUrl = '/docfiles/comments/' . $docId . '/' . $fileName;
        } elseif ($path === 'ce_attachments') {
            $fileUrl = '/docfiles/ce/' . $docId . '/' . $fileName . '/' . $pcId;
        } else {
            $fileUrl = '/';
            $fileUrl .= substr(Episciences_Tools::getAttachmentsPath((string)$paperId), mb_strlen(REVIEW_FILES_PATH));
            $fileUrl .= $fileName;
        }
        return $fileUrl;
    }

}
