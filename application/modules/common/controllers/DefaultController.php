<?php
use Episciences\Security\AccessLog;
use Episciences\Upload\SafeFileName;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;


class DefaultController extends Episciences_Controller_Action
{
    protected function isPostMaxSizeReached(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $serverParams = $request->getServer(); // $_SERVER
        try {
            $postMaxSize = Episciences_Tools::convertToBytes(ini_get('post_max_size'));
        } catch (Exception $e) {
            trigger_error($e->getMessage());
            return true;
        }

        return (isset($serverParams['CONTENT_LENGTH']) && (int)$serverParams['CONTENT_LENGTH'] > $postMaxSize);
    }


    /**
     * Requesting the file of an unpublished version: see redirectFromUnpublishedVersion(). The move to the published
     * version is permanent (301).
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function requestingAnUnpublishedFile(Episciences_Paper $paper): void
    {
        $this->redirectFromUnpublishedVersion(
            $paper,
            '/%d/pdf',
            $this->url(['controller' => 'user', 'action' => 'login', 'forward-controller' => 'paper', 'forward-action' => 'pdf', 'id' => $paper->getDocid()]),
            true
        );
    }

    /**
     * An unpublished version is only shown to the users allowed on it (see isRestrictedAccess()). The others are
     * redirected: to the published version when there is one, to the login page when they are not logged in,
     * to the home page otherwise. The redirection ends the request.
     *
     * @param string $publishedUrlFormat path of the published version relative to the journal root, "%d" standing
     * for its docid; the journal prefix is added here
     * @param string $loginUrl login page, coming back to the requested page once logged in
     * @param bool $permanent whether the move to the published version is permanent (301)
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function redirectFromUnpublishedVersion(
        Episciences_Paper $paper,
        string $publishedUrlFormat,
        string $loginUrl,
        bool $permanent = false
    ): void
    {
        if (!$this->isRestrictedAccess($paper)) {
            return;
        }

        $publishedDocId = $this->findPublishedDocId((int)($paper->getPaperid() ?: $paper->getDocid()));

        if ($publishedDocId !== 0) {
            $publishedUrl = sprintf($publishedUrlFormat, $publishedDocId);
            $this->redirectToPublishedVersion($this->prefixUrl($publishedUrl), $permanent);
            return;
        }

        if (!Episciences_Auth::isLogged()) {
            AccessLog::denied(AccessLog::AUTH_REQUIRED, 'unpublished_paper', self::paperLogContext($paper));
            $this->redirect($loginUrl);
            return;
        }

        $this->redirectHomeWithoutAccess($paper);
    }

    /**
     * The docid of the published version of a paper, 0 when none is published or when it cannot be loaded:
     * the request is then refused as for an unpublished paper rather than redirected to a broken page
     * (a 301 would be cached by the browser)
     * @throws Zend_Db_Statement_Exception
     */
    protected function findPublishedDocId(int $paperId): int
    {
        $publishedDocId = Episciences_PapersManager::getPublishedPaperId($paperId);

        if ($publishedDocId === 0 || !Episciences_PapersManager::get($publishedDocId, false)) {
            return 0;
        }

        return $publishedDocId;
    }

    /**
     * return an error if user is logged in but does not have not enough permissions
     * @param Episciences_Paper $paper
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function redirectsIfHaveNotEnoughPermissions(Episciences_Paper $paper): void
    {

        if ($this->isRestrictedAccess($paper)) {
            // Guests too are sent to the home page, not to the login page
            $this->redirectHomeWithoutAccess($paper);
        }
    }

    /**
     * Prepends the journal prefix to a path built from the site root: on path-based deployments
     * the published version is served under /<rvcode>/.
     */
    private function prefixUrl(string $url): string
    {
        return rtrim($this->journalPrefix(), '/') . '/' . ltrim($url, '/');
    }

    /**
     * '/<rvcode>/' for a journal, '/' for the portal
     */
    protected function journalPrefix(): string
    {
        return defined('PREFIX_URL') ? PREFIX_URL : '';
    }

    /**
     * The status code is kept by the shared redirector helper: it is put back for the next redirections
     * (reached only when the redirection does not end the request)
     */
    private function redirectToPublishedVersion(string $url, bool $permanent): void
    {
        /** @var Zend_Controller_Action_Helper_Redirector $redirector */
        $redirector = $this->_helper->getHelper('Redirector');
        $code = $redirector->getCode();

        try {
            $this->redirect($url, $permanent ? ['code' => 301] : []);
        } finally {
            $redirector->setCode($code);
        }
    }

    private function redirectHomeWithoutAccess(Episciences_Paper $paper): void
    {
        AccessLog::denied(AccessLog::ACCESS_REDIRECTED, 'unpublished_paper', self::paperLogContext($paper));
        $message = $this->view->translate("Vous n'avez pas accès à cet article.");
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_WARNING)->addMessage($message);
        $this->redirect($this->url(['controller' => 'index']));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function paperLogContext(Episciences_Paper $paper): array
    {
        return ['docid' => $paper->getDocid(), 'paperid' => $paper->getPaperid()];
    }

    /**
     * checked the access to the page of the unpublished version
     * @param Episciences_Paper $paper
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    protected function isRestrictedAccess(Episciences_Paper $paper): bool
    {
        $loggedUid = Episciences_Auth::getUid();

        return !$paper->isPublished() &&
            !self::isEditorialStaff($paper, Zend_Registry::get('reviewSettings')) &&
            !array_key_exists($loggedUid, $paper->getReviewers()) && // nor reviewer
            !$paper->isOwner() &&
            !$paper->isCoauthor();
    }

    /**
     * The logged user handles the paper for the journal: secretaries and administrators, the editors and copy
     * editors assigned to the paper, the editors and copy editors of a journal that does not restrict them to
     * their assigned papers. An encapsulation setting the journal never saved counts as restrictive.
     *
     * @param array<string, mixed> $journalSettings
     * @throws Zend_Db_Statement_Exception
     */
    protected static function isEditorialStaff(Episciences_Paper $paper, array $journalSettings): bool
    {
        return static::isEditorialStaffOfEveryPaper($journalSettings) ||
            $paper->isAssignedEditorOrCopyEditor((int)Episciences_Auth::getUid());
    }

    /**
     * The part of isEditorialStaff() that does not depend on the paper: the roles of the logged user handle
     * every paper of the journal.
     *
     * @param array<string, mixed> $journalSettings
     */
    protected static function isEditorialStaffOfEveryPaper(array $journalSettings): bool
    {
        return Episciences_Auth::isSecretary() ||
            (($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_EDITORS] ?? null) === '0' && Episciences_Auth::isEditor()) ||
            (($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS] ?? null) === '0' && Episciences_Auth::isCopyEditor());
    }

    protected function redirectWithFlashMessageIfPaperIsRemovedOrDeleted(Episciences_Paper $paper, bool $forceRedirection = true): void
    {
        if ($paper->isDeleted() || $paper->isRemoved()) {
            $message = $paper->isDeleted() ? 'Le document demandé a été supprimé par son auteur.' : 'Le document demandé a été supprimé par la revue.';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($this->view->translate($message));
            if ($forceRedirection) {
                $this->redirect($this->url(['controller' => 'index'])); // redirect and immediately exit
            }
        }
    }


    /**
     * @param array|null $allowedRepositories
     * @param array $options
     * @return string
     * @throws Zend_Exception
     */
    protected function getZSubmitUrl(array $allowedRepositories = null, array $options = []): string
    {

        $zSubmitUrl = EPISCIENCES_Z_SUBMIT['URL'];

        if (empty($allowedRepositories)) {
            $settings = Zend_Registry::get('reviewSettings');
            $allowedRepositories = Episciences_Submit::getRepositoriesLabels($settings);
        }

        if (array_key_exists(Episciences_Repositories::ZENODO_REPO_ID, $allowedRepositories)) {

            try {
                $zSubmitUrl .= '/' . Episciences_Tools::getLocale();

            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
            }

            $zSubmitUrl .= '/deposit';

            if (array_key_exists('newVersion', $options)) {

                $zSubmitUrl .= '/newversionfromepisciences';

                if (isset($options['epi-docid'])) {
                    $zSubmitUrl .= '?epi-docid=' . $options['epi-docid'];
                }

                if (isset($options['epi-rvcode'])) {
                    $zSubmitUrl .= '&epi-rvcode=' . $options['epi-rvcode'];
                }

                if (isset($options['epi-cdoi'])) {

                    $epiCDoi = Episciences_Repositories::getRepoDoiPrefix(Episciences_Repositories::ZENODO_REPO_ID) . '/' . mb_strtolower(Episciences_Repositories::getLabel(Episciences_Repositories::ZENODO_REPO_ID)) . '.';
                    $epiCDoi .= $options['epi-cdoi'];
                    $zSubmitUrl .= '&epi-cdoi=' . $epiCDoi;
                }

                return $zSubmitUrl;

            }
            $zSubmitUrl .= '?epi-rvcode=' . RVCODE;
        }

        return $zSubmitUrl;

    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Review|null $journal
     * @return bool
     */
    public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
    {

        if (!Episciences_Auth::isLogged()) {
            return true;
        }

        if (Episciences_Auth::isRoot()) {
            return false;
        }

        if (!$journal) {

            $review = Episciences_ReviewsManager::find(RVID);

            if ($review) {
                $review->loadSettings();
            }

        }

        $loggedUid = Episciences_Auth::getUid();

        $suUser = Episciences_Auth::getOriginalIdentity();

        $isSignedInAs = $suUser && $suUser->getUid() !== $loggedUid;

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);


        if ($isSignedInAs) {
            if (!$suUser->isRoot() && !$suUser->hasOnlyAdministratorRole()) {
                $checkConflictResponseForSu = $paper->checkConflictResponse($suUser->getUid());
                $session->checkConflictResponseForSu = $checkConflictResponseForSu;
            } else {
                $checkConflictResponseForSu = Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'];
            }

        } else {
            unset($session->checkConflictResponseForSu);
        }

        $checkConflictResponse = $paper->checkConflictResponse($loggedUid);

        $isCoiEnabled = !$journal ? $review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED) : $journal->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED);
        $isCoiEnabled = (boolean)$isCoiEnabled;

        $conflictResponses = [Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']];

        if (!Episciences_Auth::hasOnlyAdministratorRole()) {
            $conflictResponses[] = Episciences_Paper_Conflict::AVAILABLE_ANSWER['later'];
        }

        $isAuthHasConflict = in_array($checkConflictResponse, $conflictResponses, true);


        return $isCoiEnabled && (

                (
                    Episciences_Auth::isAllowedToDeclareConflict() &&
                    (
                        ($isSignedInAs && in_array($checkConflictResponseForSu, $conflictResponses, true))||
                        $isAuthHasConflict
                    )
                ) ||

                (Episciences_Auth::hasOnlyAdministratorRole() && $isAuthHasConflict) // admin not allowed to declare a conflict

            );


    }


    /**
     * * Update paper stats (only if article is published and user is not the contributor)
     * @param Episciences_Paper $paper
     * @param string $consultType
     * @throws Zend_Db_Adapter_Exception
     */
    protected function updatePaperStats(Episciences_Paper $paper, string $consultType = Episciences_Paper_Visits::CONSULT_TYPE_NOTICE): void
    {
        if ($paper->isPublished() && Episciences_Auth::getUid() !== $paper->getUid()) {
            Episciences_Paper_Visits::add($paper->getDocid(), $consultType);
        }

    }

    /**
     * @param Episciences_Paper $paper
     * @param string $url
     * @return string|null
     * @throws GuzzleException
     */
    protected function getMainDocumentContent(Episciences_Paper $paper, string $url): ?string
    {
        $mainDocumentContent = '';
        $paperDocBackup = new Episciences_Paper_DocumentBackup($paper->getDocid(), \Episciences_ReviewsManager::findByRvid($paper->getRvid())->getCode());
        $hasDocumentBackupFile = $paperDocBackup->hasDocumentBackupFile();

        $clientHeaders = [
            'headers' =>
                [
                    'User-Agent' => DOMAIN,
                    'connect_timeout' => 10,
                    'timeout' => 20
                ]
        ];

        Episciences_Tools::mbstringBinarySafeEncoding();

        $client = new Client($clientHeaders);
        $saveCopy = false;

        try {
            $res = $client->get($url);
            $headers = $res->getHeaders();
            $mainDocumentContent = $res->getBody()->getContents();

            $headers = array_change_key_case($headers, CASE_LOWER);

            if (isset($headers['content-length'])){
                $contentLength = is_array($headers['content-length']) ? $headers['content-length'][0] : $headers['content-length'];

                $isPdf = isset($headers['content-type']) && in_array('application/pdf', $headers['content-type'], true);

                if($isPdf && (int)$contentLength <= MAX_PDF_SIZE) {
                    $saveCopy = true;
                }

            }

        } catch (GuzzleHttp\Exception\RequestException $e) {

            // we failed to get content via http, try a local backup
            if ($hasDocumentBackupFile) {
                $mainDocumentContent = $paperDocBackup->getDocumentBackupFile();
            }

            if (empty($mainDocumentContent)) {
                // Attempt to get content via local backup failed
                // exit with error
                $this->view->message = $e->getMessage();
                $this->renderScript('error/http_error.phtml');
                return null;
            }

        }

        Episciences_Tools::resetMbstringEncoding();
        if (
            $saveCopy &&
            !$hasDocumentBackupFile &&
            !empty($mainDocumentContent)
        ) {
            $paperDocBackup->saveDocumentBackupFile($mainDocumentContent);

        }


        return $mainDocumentContent;

    }


    protected function renderFormErrors(Zend_Form $form = null): void{

        if(!$form || empty($errors = $form->getMessages())) {
            return;
        }

        $validationErrors = '<ol  type="i">';
        foreach ($errors as $val) {
            foreach ($val as $v) {
                $v = is_array($v) ? implode(' ', array_values($v)) : $v;
                $validationErrors .= '<li>';
                $validationErrors .= '<code>' . $this->view->escape($this->view->translate($v)) . '</code>';
                $validationErrors .= '</li>';
            }
        }
        $validationErrors .= '</ol>';

        $message = '<strong>';
        $message .= $this->view->translate("Ce formulaire comporte des erreurs");
        $message .= $this->view->translate(' :');
        $message .= $validationErrors;
        $message .= $this->view->translate('Merci de les corriger.');
        $message .= '</strong>';
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        $this->view->error = true;

    }


    protected function buildReachedMessage(): string
    {
        $message = $this->view->translate('Ce formulaire comporte des erreurs.');
        $message .= ' ';
        $message .= $this->view->translate('La taille maximale des fichiers que vous pouvez télécharger est limitée à');
        $message .= ' ';
        $message .= '<code>' . Episciences_Tools::toHumanReadable(MAX_FILE_SIZE) . '</code>. ';
        $message .= $this->view->translate('Merci de les corriger.');
        return $message;
    }

    /**
     * Safely resolves a user-influenced relative path under a trusted base directory.
     *
     * Prevents path traversal (e.g. "../../etc/passwd") by canonicalizing both the
     * base directory and the candidate path with realpath(), then verifying that the
     * resolved path is the base itself or strictly contained within it.
     *
     * Returns the canonical absolute path when it exists and is confined to $baseDir,
     * or null otherwise (non-existent file, traversal attempt, or path outside base).
     *
     * @param string $baseDir Trusted base directory (must be a real, existing directory)
     * @param string $relativePath User-influenced path, relative to $baseDir
     * @return string|null Canonical path inside $baseDir, or null if invalid/outside
     */
    protected function resolveSafePath(string $baseDir, string $relativePath): ?string
    {
        // Reject empty input and any NUL byte / control character
        if ($relativePath === '') {
            return null;
        }

        if (SafeFileName::hasControlCharacter($relativePath)) {
            AccessLog::denied(AccessLog::UNSAFE_PATH, 'control_character', ['path' => $relativePath]);
            return null;
        }

        $realBase = realpath($baseDir);
        if ($realBase === false) {
            return null;
        }

        $real = realpath($realBase . DIRECTORY_SEPARATOR . $relativePath);
        if ($real === false) {
            // A missing file is not an attempt, unless the path climbs above the base directory
            if (self::climbsAboveBase($relativePath)) {
                AccessLog::denied(AccessLog::UNSAFE_PATH, 'outside_base_directory', ['path' => $relativePath]);
            }
            return null;
        }

        // The resolved path must be the base directory itself or strictly inside it
        if ($real !== $realBase && !str_starts_with($real, $realBase . DIRECTORY_SEPARATOR)) {
            AccessLog::denied(AccessLog::UNSAFE_PATH, 'outside_base_directory', ['path' => $relativePath]);
            return null;
        }

        return $real;
    }

    /**
     * Whether the ".." segments of a relative path lead above its starting directory, as written
     * (symbolic links are not followed: realpath() covers them for existing files).
     * Not Symfony's Path::canonicalize(): it expands a leading "~" to the home directory and only
     * splits on backslashes under Windows.
     */
    protected static function climbsAboveBase(string $relativePath): bool
    {
        $depth = 0;

        foreach (preg_split('#[/\\\\]#', $relativePath) ?: [] as $segment) {
            if ($segment === '..') {
                if (--$depth < 0) {
                    return true;
                }
            } elseif ($segment !== '' && $segment !== '.') {
                $depth++;
            }
        }

        return false;
    }

    protected function getSession(): Zend_Session_Namespace
    {
        return new Zend_Session_Namespace(SESSION_NAMESPACE);
    }

}