<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Episciences\AppRegistry;
use Episciences\Paper\Files\DocumentFilePolicy;
use Episciences\Security\AccessLog;
use Episciences\Upload\SafeFileName;

require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

class FileController extends DefaultController
{
    /** Content types the browser may display instead of downloading */
    private const INLINE_CONTENT_TYPES = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function indexAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $filename = (string)($params['filename'] ?? '');
        $extension = (string)($params['extension'] ?? '');

        // Only serve flat file names (same shape as the "files" route).
        if (
            !preg_match('/^[\w ]+$/D', $filename) ||
            !preg_match('/^\w+$/D', $extension) ||
            str_ends_with($filename, '-crypto')
        ) {
            $this->logUnsafeFlatName($filename, $extension);
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->loadFile(REVIEW_FILES_PATH, $filename . '.' . $extension);
    }

    /**
     * The names refused by indexAction() are only logged when they try to reach another file:
     * a directory part, a control character or a reserved file (arXiv encryption key).
     */
    private function logUnsafeFlatName(string $filename, string $extension): void
    {
        if (str_ends_with($filename, '-crypto')) {
            AccessLog::denied(AccessLog::UNSAFE_PATH, 'reserved_file_name');
        } elseif (
            SafeFileName::isUnsafe($filename . '.' . $extension) ||
            SafeFileName::hasControlCharacter($filename . $extension)
        ) {
            AccessLog::denied(AccessLog::UNSAFE_PATH, 'unsafe_file_name');
        }
    }

    public function docfilesAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $folder = $params['folder'] ?? '';
        if ($folder === 'ce') {
            $folder = Episciences_CommentsManager::COPY_EDITING_SOURCES;
        }

        // Other sub-directories of the document (reports, ratings, tmp...) have their own access rules
        if (!in_array($folder, DocumentFilePolicy::FOLDERS, true)) {
            if ($folder !== '') {
                AccessLog::denied(AccessLog::UNSAFE_PATH, 'unexpected_folder', ['folder' => $folder]);
            }
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $parentCommentId = $params['parentCommentId'] ?? null;
        $docId = (int)$params['docId'];
        $filename = $params['filename'] ?? null;
        $extension = $params['extension'] ?? null;

        // Missing name, or forged as an array (filename[]=...)
        if (!is_string($filename) || ($extension !== null && !is_string($extension))) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $file = $filename;

        if (!empty($extension)) {
            $file .= '.' . $extension;
        }

        // The folder allow-list would be pointless if a segment could climb out of it (e.g. "../reports").
        // Only copy-editing files are stored by comment: elsewhere the access check, made on the file name,
        // would not be about the file served.
        if (!SafeFileName::isBare($file)) {
            if (SafeFileName::isUnsafe($file)) {
                AccessLog::denied(AccessLog::UNSAFE_PATH, 'unsafe_file_name', ['file' => $file]);
            }
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        if (
            null !== $parentCommentId &&
            ($folder !== DocumentFilePolicy::FOLDER_COPY_EDITING || !preg_match('/^\d+$/D', (string)$parentCommentId))
        ) {
            AccessLog::denied(AccessLog::UNSAFE_PATH, 'unexpected_parent_comment_id', [
                'folder' => $folder,
                'parent_comment_id' => $parentCommentId,
            ]);
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        // Trusted base: the document directory. The folder / parentCommentId / file
        // segments are user-influenced and confined under it by resolveSafePath().
        $relativePath = $folder . '/';

        if (null !== $parentCommentId) {
            $relativePath .= $parentCommentId . '/';
        }

        $relativePath .= $file;

        // 404 rather than 403: do not disclose that the file exists
        if (!$this->isAllowedToReadDocumentFile($docId, $folder, $file)) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->loadFile(Episciences_PapersManager::buildDocumentPath($docId), $relativePath);
    }

    /**
     * The files of a paper version are only given to the users who see them on the paper pages
     * (see DocumentFilePolicy).
     */
    protected function isAllowedToReadDocumentFile(int $docId, string $folder, string $file): bool
    {
        try {
            $paper = $this->findPaper($docId);

            if (!$paper) {
                return false;
            }

            // Public file: the user's roles do not matter
            if (DocumentFilePolicy::isPublic($folder, $paper->isPublished())) {
                return true;
            }

            $review = $this->findJournal();
            $uid = (int)Episciences_Auth::getUid();

            $isOwnerOrCoAuthor = $paper->isOwnerOrCoAuthor();
            $isReviewer = array_key_exists($uid, $paper->getReviewers());

            // The author and the reviewers only see some of the comments: the type of the comment carrying the file decides
            $commentTypes = ($isOwnerOrCoAuthor || $isReviewer) && $folder === DocumentFilePolicy::FOLDER_COMMENTS
                ? $this->findCommentTypes($docId, $file)
                : [];

            $isAllowed = DocumentFilePolicy::isAllowed(
                $folder,
                $paper->isPublished(),
                $isOwnerOrCoAuthor,
                static::isConflictDetected($paper, $review),
                static::isEditorialStaff($paper, $review->getSettings()),
                $isReviewer,
                $commentTypes
            );

            if (!$isAllowed) {
                AccessLog::denied(AccessLog::NOT_FOUND_MASKED, 'document_file_access', [
                    'docid' => $docId,
                    'folder' => $folder,
                    'file' => $file,
                ]);
            }

            return $isAllowed;
        } catch (Exception $e) {
            // Refused, but not to be mistaken for a missing file
            AppRegistry::getMonoLogger()?->warning('Document file access check failed', [
                'docId' => $docId,
                'folder' => $folder,
                'file' => $file,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * The files of the temporary versions are shared by all the versions of the paper: the user must be allowed
     * on one of them (see DocumentFilePolicy::isTemporaryVersionFileAllowed()).
     */
    protected function isAllowedToReadTemporaryVersionFile(int $paperId): bool
    {
        return $this->isAllowedOnTemporaryVersionFiles($paperId, false);
    }

    /**
     * @param bool $toWrite upload or removal (see DocumentFilePolicy::isTemporaryVersionFileWritable()), else reading
     */
    private function isAllowedOnTemporaryVersionFiles(int $paperId, bool $toWrite): bool
    {
        try {
            $review = $this->findJournal();
            $uid = (int)Episciences_Auth::getUid();
            $hasConflict = null;
            $hasVersion = false;
            // Only the assignment to the paper depends on the version
            $isStaffOfEveryPaper = static::isEditorialStaffOfEveryPaper($review->getSettings());

            // The versions are loaded one at a time, the latest first: the first one granting access ends the check
            foreach ($this->findPaperVersions($paperId) as $paper) {
                $hasVersion = true;
                $isOwnerOrCoAuthor = $paper->isOwnerOrCoAuthor();
                // Not isAssignedEditorOrCopyEditor(), which loads the latest version again: it is one of the versions
                $isEditorialStaff = $isStaffOfEveryPaper || $paper->getEditor($uid) || $paper->getCopyEditor($uid);

                // Only withdraws the access of the editorial staff: not looked up for the others. Declared for the
                // paper, not for a version: looked up once.
                if ($isEditorialStaff) {
                    $hasConflict ??= static::isConflictDetected($paper, $review);
                }

                $isAllowed = $toWrite
                    ? DocumentFilePolicy::isTemporaryVersionFileWritable($isOwnerOrCoAuthor, (bool)$hasConflict, $isEditorialStaff)
                    : DocumentFilePolicy::isTemporaryVersionFileAllowed(
                        $isOwnerOrCoAuthor,
                        (bool)$hasConflict,
                        $isEditorialStaff,
                        array_key_exists($uid, $paper->getReviewers())
                    );

                if ($isAllowed) {
                    return true;
                }
            }

            // No version: the paper does not exist, nothing was refused
            if ($hasVersion) {
                // Reading is refused with a 404, upload and removal with a 403
                AccessLog::denied(
                    $toWrite ? AccessLog::HTTP_FORBIDDEN : AccessLog::NOT_FOUND_MASKED,
                    $toWrite ? 'temporary_version_file_write' : 'temporary_version_file_access',
                    ['paperid' => $paperId]
                );
            }

            return false;
        } catch (Exception $e) {
            // Refused, but not to be mistaken for a missing file
            AppRegistry::getMonoLogger()?->warning('Temporary version file access check failed', [
                'paperId' => $paperId,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Versions of the paper in the current journal, the latest first (see
     * Episciences_PapersManager::getDocIdsByPaperIdInJournal()), each one loaded when it is reached
     *
     * @return iterable<Episciences_Paper>
     * @throws Exception
     */
    protected function findPaperVersions(int $paperId): iterable
    {
        foreach (Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID) as $docId) {
            $paper = $this->findPaper($docId);

            if ($paper) {
                yield $paper;
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function findPaper(int $docId): ?Episciences_Paper
    {
        return Episciences_PapersManager::get($docId, false) ?: null;
    }

    /**
     * @throws Exception
     */
    protected function findJournal(): Episciences_Review
    {
        $review = Episciences_ReviewsManager::find(RVID);

        if (!$review) {
            throw new RuntimeException(sprintf('Journal #%d not found', RVID));
        }

        $review->loadSettings();
        return $review;
    }

    /**
     * @return int[]
     */
    protected function findCommentTypes(int $docId, string $file): array
    {
        return Episciences_CommentsManager::findTypesByAttachment($docId, $file);
    }

    // load an e-mail attached file

    /**
     * @throws Exception
     */
    public function attachmentsAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        // E-mail attachments are not public: require an authenticated user.
        if (!Episciences_Auth::isLogged()) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        $params = $this->getRequest()->getParams();

        $subDirectories = $params['sub_directories'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;

        // Trusted base: the attachments directory. sub_directories and file are
        // user-influenced and confined under it by resolveSafePath().
        $baseDir = REVIEW_FILES_PATH . Episciences_Mail_Send::ATTACHMENTS;
        $relativePath = DIRECTORY_SEPARATOR . rtrim($subDirectories, '/\\') . DIRECTORY_SEPARATOR . $file;

        $this->loadFile($baseDir, $relativePath, true);
    }

    /**
     * Files of the temporary versions of a paper: the route segment is the paper id, the files being stored by paper
     */
    public function tmpAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->getRequest()->getParams();

        $paperId = (int)($params['docId'] ?? 0);
        $filename = $params['filename'] ?? '';
        $extension = $params['extension'] ?? '';

        // Forged as an array (filename[]=...)
        if (!is_string($filename) || !is_string($extension)) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $file = $filename . '.' . $extension;

        if (!SafeFileName::isBare($file)) {
            if (SafeFileName::isUnsafe($file)) {
                AccessLog::denied(AccessLog::UNSAFE_PATH, 'unsafe_file_name', ['file' => $file]);
            }
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        // 404 rather than 403: do not disclose that the file exists
        if ($paperId <= 0 || !$this->isAllowedToReadTemporaryVersionFile($paperId)) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->loadFile(Episciences_PapersManager::buildDocumentPath($paperId) . '/tmp', $file);
    }

    // access to a rating report attachment
    public function reportAction(): void
    {
        $params = $this->getRequest()->getParams();

        $id = (int)$params['id'];
        $filename = $params['filename'];
        $extension = $params['extension'];
        $file = $filename . '.' . $extension;

        // check if report exists
        $report = Episciences_Rating_Report::findById($id);
        if (!$report) {
            $this->renderNotFound();
            return;
        }

        // Rating reports are confidential: access control aligns with the report
        // display gate that filters by user role and criterion visibility.
        $paper = Episciences_PapersManager::get($report->getDocid());
        if (!$paper) {
            $this->renderNotFound();
            return;
        }

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        // Resolve all role-related flags here (controller context) to keep
        // the Access class free of static dependencies and fully testable.
        // Note: isReportsVisibleToAuthor() already calls isOwner() internally,
        // which handles the "switch user" (su) case via getOriginalIdentity().
        $uid = Episciences_Auth::isLogged() ? Episciences_Auth::getUid() : null;

        // Same conflict of interest check as the other paper pages, including the
        // answer of the original user when signed in as another one (su).
        // A visitor is not logged in: no role grants them access anyway.
        $hasConflict = $uid !== null && static::isConflictDetected($paper, $review);

        $isAllowed = Episciences_Rating_Report_Access::mayDownloadAttachment(
            paper: $paper,
            report: $report,
            filename: $file,
            uid: $uid,
            review: $review,
            isSecretary: Episciences_Auth::isSecretary(),
            isEditor: Episciences_Auth::isEditor(),
            isCopyEditor: Episciences_Auth::isCopyEditor(),
            isReportsVisibleToAuthor: $paper->isReportsVisibleToAuthor(),
            hasConflict: $hasConflict
        );

        if (!$isAllowed) {
            // Return 404 rather than 403 to avoid disclosing the report's existence.
            $this->renderNotFound('rating_report_access');
            return;
        }

        // Trusted base derived from the persisted report (docid/uid), the file name is
        // user-influenced and confined under it by resolveSafePath().
        $baseDir = REVIEW_FILES_PATH . $report->getDocid() . '/reports/' . $report->getUid();
        $filepath = $this->resolveSafePath($baseDir, $file);

        if ($filepath === null) {
            $this->renderNotFound();
            return;
        }

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $this->openFile($filepath);
    }

    /**
     * Renders a generic 404 "file not found" error page.
     *
     * @param string $deniedReason set when the 404 hides an access refusal rather than a missing file
     */
    private function renderNotFound(string $deniedReason = ''): void
    {
        if ($deniedReason !== '') {
            AccessLog::denied(AccessLog::NOT_FOUND_MASKED, $deniedReason);
        }

        $this->getResponse()->setHttpResponseCode(404);
        $this->view->message = "Fichier introuvable";
        $this->view->description = "Le fichier demandé n'existe pas, ou bien vous n'avez pas les autorisations nécessaires pour y accéder.";
        $this->renderScript('error/error.phtml');
    }

    /**
     * Resolves a user-influenced relative path under a trusted base directory
     * (preventing path traversal) and streams the file if it is readable.
     *
     * @param string $baseDir Trusted base directory
     * @param string $relativePath User-influenced path, relative to $baseDir
     * @param bool $forceDownload
     */
    protected function loadFile(string $baseDir, string $relativePath, bool $forceDownload = false): void
    {
        $filepath = $this->resolveSafePath($baseDir, $relativePath);

        if ($filepath !== null && is_readable($filepath)) {
            $this->openFile($filepath, $forceDownload);
        } else {
            $message = '<strong>' . $this->view->translate("Le fichier n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('notfound', null, null, [PREFIX_ROUTE => RVCODE]);
        }
    }

    /**
     * Whether a file of this content type may be displayed by the browser instead of downloaded
     *
     * @param string $contentType Value returned by Episciences_Tools::getMimeType() (may carry a charset)
     */
    public static function isInlineContentType(string $contentType): bool
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));

        return in_array($type, self::INLINE_CONTENT_TYPES, true);
    }

    /**
     * Builds the response headers used to serve a file
     *
     * Only PDF and raster images are displayed in the browser: anything else (SVG, XML, text,
     * application/octet-stream...) is downloaded, because it could run script on the journal origin.
     *
     * @param string $filename Base name of the file
     * @param string $contentType Value returned by Episciences_Tools::getMimeType()
     * @param int $size File size in bytes
     * @param bool $forceDownload Download even if the browser could display the file
     * @return list<string>
     */
    public static function buildFileHeaders(string $filename, string $contentType, int $size, bool $forceDownload = false): array
    {
        $downloadableFilename = '"' . $filename . '"';
        $headers = ['X-Content-Type-Options: nosniff'];

        if ($forceDownload || !self::isInlineContentType($contentType)) {
            $headers[] = 'Content-Description: File Transfer';
            $headers[] = 'Expires: 0';
            $headers[] = 'Cache-Control: must-revalidate';
            $headers[] = 'Pragma: public';
            $headers[] = 'Content-Disposition: attachment;filename=' . $downloadableFilename;
        } else {
            $headers[] = 'Content-Disposition: inline; filename=' . $downloadableFilename;
        }

        $headers[] = 'Content-Type: ' . $contentType;
        $headers[] = 'Content-Length: ' . $size;

        return $headers;
    }

    /**
     * Reads a file and writes it to the output buffer
     * @param string $file
     * @param bool $forceDownload
     */
    protected function openFile(string $file, bool $forceDownload = false): void
    {
        $headers = self::buildFileHeaders(
            basename($file),
            Episciences_Tools::getMimeType($file),
            (int)filesize($file),
            $forceDownload
        );

        foreach ($headers as $header) {
            header($header);
        }

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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // Uploading files is reserved to authenticated users (submission /
        // paper-management flows) and requires a valid request token.
        if (!Episciences_Auth::isLogged() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->getResponse()->setHttpResponseCode(403);
            echo Zend_Json::encode(['status' => 'error']);
            return;
        }

        $result = [];

        if ($this->isPostMaxSizeReached()) {
            $result ['status'] = 'error';
            $result ['messages'][] = $this->buildReachedMessage();
        } else {
            // Copy editing : modifier le path
            $path = (string)$request->getPost('path');
            $pcId = (int)$request->getPost('pcId');
            $docId = (int)$request->getPost('docId');
            $paperId = $this->resolvePaperId($docId, (int)$request->getPost('paperId'));

            if ($paperId === null || !$this->isAllowedToHandleDocumentFiles($path, $docId, $paperId)) {
                $this->getResponse()->setHttpResponseCode(403);
                echo Zend_Json::encode(['status' => 'error']);
                return;
            }

            try {
                $adapter = new Zend_File_Transfer_Adapter_Http();
                $adapter->addValidators([
                    ['FilesSize', false, MAX_FILE_SIZE],
                    ['Extension', false, ALLOWED_EXTENSIONS],
                    new Episciences_Form_Validate_MimeType()
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

        // Mutating action: require POST, an authenticated user and a valid
        // request token.
        if (!$request->isPost() || !Episciences_Auth::isLogged()) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        if (!Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        // attachments path
        $path = (string)$request->getPost('path');
        $filename = (string)$request->getPost('file');
        $docId = (int)$request->getPost('docId');
        $pcId = (int)$request->getPost('pcId');
        $paperId = $this->resolvePaperId($docId, (int)$request->getPost('paperId'));

        // Only a flat file name is expected here; strip any directory component
        // to neutralise path traversal (e.g. "../../somefile").
        if (SafeFileName::isUnsafe($filename)) {
            AccessLog::denied(AccessLog::UNSAFE_PATH, 'unsafe_file_name', ['file' => $filename]);
        }
        $filename = basename($filename);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        if ($paperId === null || !$this->isAllowedToHandleDocumentFiles($path, $docId, $paperId)) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        // Only the uploads not submitted yet can be removed: the files of a saved answer are read by the editors
        if ($path === 'tmp_attachments' && $this->isSubmittedTemporaryVersionFile($paperId, $filename)) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        $baseDir = $this->buildStorageFolder($path, $paperId, $docId, $pcId);
        $filepath = $this->resolveSafePath($baseDir, $filename);

        if ($filepath !== null && is_file($filepath)) {
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
        if (!$paper || !$paper->hasFilesEnrichment() || ($paper->getRvid() !== RVID) || ($paper->getRepoid() === 0)) {
            $this->getResponse()->setHttpResponseCode(404);
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
            $this->redirect($this->url(['controller' => 'index']));
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
     * When an upload or a removal targets a document's storage (docId or paperId
     * given), the user must be related to the paper: contributor, reviewer,
     * copy editor or editor of the paper, or allowed to manage it.
     * The files of the temporary versions have their own rule (see isAllowedToHandleTemporaryVersionFiles()).
     */
    protected function isAllowedToHandleDocumentFiles(string $path, int $docId, int $paperId): bool
    {
        if ($path === 'tmp_attachments') {
            return $this->isAllowedToHandleTemporaryVersionFiles($paperId);
        }

        if (!$docId && !$paperId) {
            // session-scoped attachments directory: no document involved
            return true;
        }

        try {
            $paper = $docId
                ? Episciences_PapersManager::get($docId, false)
                : Episciences_PapersManager::getLastPaper($paperId, true);
        } catch (Exception $e) {
            return false;
        }

        if (!$paper) {
            return false;
        }

        $uid = Episciences_Auth::getUid();

        return $paper->isOwnerOrCoAuthor()
            || Episciences_Auth::isAllowedToManagePaper()
            || $paper->getEditor($uid)
            || $paper->getCopyEditor($uid)
            || $paper->getReviewer($uid);
    }

    /**
     * The paper id of the version when a docId is given, else the paper id given: resolved once for the access
     * check, the storage folder and the link to the file, so that they cannot disagree. A paper id given with the
     * docId must be the one of the version.
     * Like the save paths of the temporary versions, a version without paper id falls back to its docId.
     *
     * @return int|null null when the version cannot be loaded or does not belong to the paper id given
     */
    protected function resolvePaperId(int $docId, int $paperId): ?int
    {
        if (!$docId) {
            return $paperId;
        }

        try {
            $paper = $this->findPaper($docId);
        } catch (Exception $e) {
            return null;
        }

        if (!$paper) {
            return null;
        }

        $versionPaperId = (int)$paper->getPaperid() ?: (int)$paper->getDocid();

        return !$paperId || $paperId === $versionPaperId ? $versionPaperId : null;
    }

    /**
     * The file is attached to an answer already saved with a temporary version of the paper
     * (see Episciences_CommentsManager::isTemporaryVersionAnswerAttachment()). Considered submitted when the
     * lookup fails, so that it is not removed.
     */
    protected function isSubmittedTemporaryVersionFile(int $paperId, string $file): bool
    {
        try {
            return Episciences_CommentsManager::isTemporaryVersionAnswerAttachment(
                Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID),
                $file
            );
        } catch (Exception $e) {
            AppRegistry::getMonoLogger()?->warning('Temporary version file lookup failed', [
                'paperId' => $paperId,
                'file' => $file,
                'exception' => $e->getMessage(),
            ]);
            return true;
        }
    }

    /**
     * The files of the temporary versions are stored by paper (see buildStorageFolder()): uploading or removing
     * one is reserved to the authors and the editorial staff, the reviewers only read them.
     */
    private function isAllowedToHandleTemporaryVersionFiles(int $paperId): bool
    {
        // Without a paper, the files would be stored in a "0/tmp/" directory
        return $paperId > 0 && $this->isAllowedOnTemporaryVersionFiles($paperId, true);
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
            // Relative to PREFIX_URL, like the other links built here
            $fileUrl = ltrim(Episciences_Tools::buildTmpFileUrl($paperId, $fileName), '/');
        } elseif ($path === 'comment_attachments') {
            $fileUrl = 'docfiles/comments/' . $docId . '/' . $fileName;
        } elseif ($path === 'ce_attachments') {
            $fileUrl = 'docfiles/ce/' . $docId . '/' . $fileName . '/' . $pcId;
        } else {
            $fileUrl = substr(Episciences_Tools::getAttachmentsPath((string)$paperId), mb_strlen(REVIEW_FILES_PATH));
            $fileUrl .= $fileName;
        }
        return PREFIX_URL . $fileUrl;
    }
}
