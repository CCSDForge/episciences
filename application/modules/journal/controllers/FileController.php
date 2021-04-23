<?php
require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class FileController extends DefaultController
{
    const APPLICATION_OCTET_STREAM = 'application/octet-stream';

    public function indexAction()
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

    public function docfilesAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $folder = ($params['folder'] === 'ce') ? 'copy_editing_sources' : $params['folder'];
        $parentCommentId = $params['parentCommentId'] ?? null;
        $docId = $params['docId'];
        $filename = $params['filename'];
        $extension = $params['extension'] ?? null;

        $file = $filename;

        if (!empty($extension)) {
            $file .= '.' . $extension;
        }

        $path = REVIEW_FILES_PATH . $docId . '/' . $folder . '/';

        if (null !== $parentCommentId) {
            $path .= $parentCommentId . '/';
        }

        $filepath = $path . $file;

        $this->loadFile($filepath);
    }

    // load an e-mail attached file
    public function attachmentsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;
        $path = REVIEW_FILES_PATH . 'attachments/';
        $filepath = $path . $file;

        $this->loadFile($filepath, true);
    }

    // Accès à la version temporaire d'un document
    public function tmpAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $docId = $params['docId'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;

        $path = REVIEW_FILES_PATH . $docId . '/tmp/';
        $filepath = $path . $file;

        $this->loadFile($filepath);
    }

    // Accès aux fichiers joints d'un document
    public function paperAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $docId = $params['docId'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;
        $path = REVIEW_FILES_PATH . $docId . '/ratings/';
        $filepath = $path . $file;

        $this->loadFile($filepath);
    }

    // access to a rating report attachment
    public function reportAction()
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
            $this->view->message = "Fichier introuvable";
            $this->view->description = "Le fichier demandé n'existe pas, ou bien vous n'avez pas les autorisations nécessaires pour y accéder.";
            $this->renderScript('error/error.phtml');
            return;
        }

        $filepath = REVIEW_FILES_PATH . $report->getDocid() . '/reports/' . $report->getUid() . '/' . $file;
        if (!file_exists($filepath)) {
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
     * @return mixed
     */
    protected function loadFile($filepath, bool $forceDownload = false)
    {
        if (is_readable($filepath)) {
            $this->openFile($filepath, $forceDownload);
        } else {
            $message = '<strong>' . $this->view->translate("Le fichier n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            return $this->_helper->redirector('notfound', 'index');
        }
    }

    /**
     * Reads a file and writes it to the output buffer
     * @param string $file
     * @param bool $forceDownload
     */
    protected function openFile(string $file, bool $forceDownload = false)
    {
        $contentType = Episciences_Tools::getMimeType($file);
        $downloadableFilename = '"' . basename($file) . '"';

        if ($contentType == self::APPLICATION_OCTET_STREAM) {
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
    public function uploadAction()
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
            $path = $request->getPost('path');
            $pcId = (int)$request->getPost('pcId');
            $docId = (int)$request->getPost('docId');
            $paperId = (int)$request->getPost('paperId');

            try {
                /** @var Zend_File_Transfer_Adapter_Http $adapter */
                $adapter = new Zend_File_Transfer_Adapter_Http();
                $adapter->addValidators([
                    ['FilesSize', false, MAX_FILE_SIZE],
                    ['Extension', false, ALLOWED_EXTENSIONS],
                    ['MimeType', false, ALLOWED_MIMES_TYPES]
                ]);

                $files = $adapter->getFileInfo();
                foreach ($files as $info) {

                    $filename = $info['name'];

                    if (!$adapter->isUploaded() || !$adapter->isValid()) {
                        $result ['status'] = 'error';
                        $result['messages'] = $adapter->getMessages();
                        break;
                    }

                    // todo : créer classe upload
                    // todo: créer classe filesManager
                    // todo: prendre le chemin en paramètre

                    $sFolder = $this->buildStorageFolder($path, $paperId, $docId, $pcId);

                    if (!is_dir($sFolder)) {
                        $resMkdir = mkdir($sFolder, 0770, true);
                        if (!$resMkdir) {
                            error_log('Fatal error : unable to create folder: ' . $sFolder);
                        }
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

    public function deleteAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // attachments path
        $path = $request->getPost('path');

        $filename = $request->getPost('file');
        $docId = (int)$request->get('docId');
        $pcId = (int)$request->getPost('pcId');
        $paperId = (int)$request->getpost('paperId');

        $filepath = $this->buildStorageFolder($path, $paperId, $docId, $pcId) . $filename;

        if ($filename && is_file($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * return storage folder
     * @param string $path
     * @param int $paperId
     * @param int $docId
     * @param int $pcId
     * @return string
     */
    private function buildStorageFolder(string $path, int $paperId, int $docId, int $pcId)
    {
        if ($path === 'tmp_attachments') {
            $folder = $paperId . '/tmp/';
        } elseif ($path === 'comment_attachments') {
            $folder = $docId . '/comments/';
        } elseif ($path === 'ce_attachments') {
            $folder = $docId . '/copy_editing_sources/' . $pcId . '/';
        } else {
            $folder = 'attachments/';
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
     */
    private function buildFileUrl(string $fileName, string $path, int $paperId, int $docId, int $pcId)
    {
        if ($path === 'tmp_attachments') {
            $fileUrl = '/tmp_files/' . $paperId . '/' . $fileName;
        } elseif ($path === 'comment_attachments') {
            $fileUrl = '/docfiles/comments/' . $docId . '/' . $fileName;
        } elseif ($path === 'ce_attachments') {
            $fileUrl = '/docfiles/ce/' . $docId . '/' . $fileName . '/' . $pcId;
        } else {
            $fileUrl = '/attachments/' . $fileName;
        }
        return $fileUrl;
    }
}
