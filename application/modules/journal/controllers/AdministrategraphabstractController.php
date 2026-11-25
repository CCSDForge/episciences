<?php

use Episciences\AppRegistry;
use Episciences\Paper\GraphicalAbstract\GraphicalAbstract;
use Episciences\Paper\GraphicalAbstract\GraphicalAbstractPolicy;
use Episciences\Paper\GraphicalAbstract\GraphicalAbstractRepository;
use Episciences\Paper\GraphicalAbstract\GraphicalAbstractValidator;

/**
 * Illustration or graphical abstract of a paper version (AJAX endpoints of paper/paper_graphical_abstract.phtml).
 *
 * Every response is JSON: {status: "success"|"error", messages: {field: message}}.
 */
class AdministrategraphabstractController extends Episciences_Controller_Action
{
    /** Name of the response message that is not tied to a form field */
    private const GLOBAL_MESSAGE = 'form';

    private const STORED_FILE_BASENAME = 'graphical_abstract';

    public function addgraphabsAction(): void
    {
        $this->disableView();

        $paper = $this->loadAuthorizedPaper();

        if ($paper === null) {
            return;
        }

        $docId = (int)$paper->getDocid();
        $request = $this->getRequest();
        $current = GraphicalAbstractRepository::find($docId);

        $text = GraphicalAbstractValidator::validateText(
            $request->getPost(GraphicalAbstractValidator::FIELD_ALT),
            $request->getPost(GraphicalAbstractValidator::FIELD_LICENSE)
        );
        $errors = $text['errors'];

        $upload = $this->getUploadedFile();
        $fileError = $upload === null
            // the file is optional when only the text alternative or the license is updated
            ? ($current === null ? GraphicalAbstractValidator::ERROR_FILE_REQUIRED : null)
            : $this->validateUploadedFile($upload);

        if ($fileError !== null) {
            $errors[GraphicalAbstractValidator::FIELD_FILE] = $fileError;
        }

        if (!empty($errors)) {
            $isTooLarge = $fileError === GraphicalAbstractValidator::ERROR_FILE_TOO_LARGE;
            $this->sendErrors($isTooLarge ? 413 : 400, $this->translateErrors($errors));
            return;
        }

        $file = $current?->file;
        $isNewFile = false;

        try {
            if ($upload !== null) {
                $file = $this->storeUploadedFile($docId, $upload);
                $isNewFile = $current === null || basename($current->file) !== $file;
            }

            GraphicalAbstractRepository::save($docId, new GraphicalAbstract((string)$file, $text['alt'], $text['license']));
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->error(sprintf('Failed to save the graphical abstract of document #%d: %s', $docId, $e->getMessage()));
            // the database still references the previous file: only the new one is dropped
            if ($isNewFile) {
                GraphicalAbstractRepository::removeFile($docId, (string)$file);
            }
            $this->sendErrors(500, [self::GLOBAL_MESSAGE => $this->view->translate("L'illustration n'a pas pu être enregistrée.")]);
            return;
        }

        // the previous file (other image type) is removed once the database no longer references it
        if ($isNewFile && $current !== null) {
            GraphicalAbstractRepository::removeFile($docId, $current->file);
        }

        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage($this->view->translate('Illustration enregistrée'));
        $this->sendJson(200, ['status' => 'success', 'messages' => []]);
    }

    public function deletegraphabsAction(): void
    {
        $this->disableView();

        $paper = $this->loadAuthorizedPaper();

        if ($paper === null) {
            return;
        }

        $docId = (int)$paper->getDocid();
        // the file shown on the page, so that a stale page does not delete a newer illustration
        $file = basename((string)$this->getRequest()->getPost('file'));
        $current = GraphicalAbstractRepository::find($docId);

        if ($current === null || basename($current->file) !== $file) {
            $this->sendErrors(404, [self::GLOBAL_MESSAGE => $this->view->translate('Fichier à supprimer inconnu')]);
            return;
        }

        try {
            GraphicalAbstractRepository::delete($docId);
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->error(sprintf('Failed to delete the graphical abstract of document #%d: %s', $docId, $e->getMessage()));
            $this->sendErrors(500, [self::GLOBAL_MESSAGE => $this->view->translate("L'illustration n'a pas pu être supprimée.")]);
            return;
        }

        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage($this->view->translate('Illustration supprimée'));
        $this->sendJson(200, ['status' => 'success', 'messages' => []]);
    }

    /**
     * Common request guard: AJAX POST with a valid request token, on a paper version of the journal
     * whose illustration the user may change (see GraphicalAbstractPolicy). Sends the error response otherwise.
     */
    private function loadAuthorizedPaper(): ?Episciences_Paper
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest() && $this->exceedsPostMaxSize($request)) {
            $this->sendErrors(413, $this->translateErrors([GraphicalAbstractValidator::FIELD_FILE => GraphicalAbstractValidator::ERROR_FILE_TOO_LARGE]));
            return null;
        }

        if (!$request->isXmlHttpRequest() || !$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->sendForbidden();
            return null;
        }

        $docId = (int)$request->getPost('docId');
        $paper = $docId > 0 ? Episciences_PapersManager::get($docId, false, RVID) : false;

        if (!$paper instanceof Episciences_Paper) {
            $this->sendErrors(404, [self::GLOBAL_MESSAGE => $this->view->translate("L'article demandé n'existe pas.")]);
            return null;
        }

        if (!GraphicalAbstractPolicy::canEdit($paper)) {
            $this->sendForbidden();
            return null;
        }

        return $paper;
    }

    /**
     * PHP empties $_POST and $_FILES when the body is larger than post_max_size, so the request
     * token cannot be read: this case must not be reported as an authorization failure.
     */
    private function exceedsPostMaxSize(Zend_Controller_Request_Http $request): bool
    {
        return $request->isPost()
            && empty($_POST)
            && empty($_FILES)
            && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    /**
     * @return array{name: string, tmp_name: string, error: int}|null null if no file was sent
     */
    private function getUploadedFile(): ?array
    {
        $files = (new Zend_File_Transfer_Adapter_Http())->getFileInfo();
        $info = $files[GraphicalAbstractValidator::FIELD_FILE] ?? null;

        if (!is_array($info) || (int)($info['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name' => (string)($info['name'] ?? ''),
            'tmp_name' => (string)($info['tmp_name'] ?? ''),
            'error' => (int)$info['error'],
        ];
    }

    /**
     * @param array{name: string, tmp_name: string, error: int} $upload
     * @return string|null error code
     */
    private function validateUploadedFile(array $upload): ?string
    {
        if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return GraphicalAbstractValidator::ERROR_FILE_TOO_LARGE;
        }

        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            return GraphicalAbstractValidator::ERROR_FILE_REQUIRED;
        }

        return GraphicalAbstractValidator::validateFile($upload['tmp_name'], $upload['name']);
    }

    /**
     * Moves the (already validated) upload to the paper's public documents directory,
     * named after its actual MIME type. The previous file is left to the caller, which
     * removes it once the new file name is saved.
     *
     * @param array{name: string, tmp_name: string, error: int} $upload
     * @return string stored file name
     */
    private function storeUploadedFile(int $docId, array $upload): string
    {
        $extension = GraphicalAbstractValidator::extensionFor($upload['tmp_name']);

        if ($extension === null) {
            throw new RuntimeException('Unable to determine the image extension');
        }

        $file = self::STORED_FILE_BASENAME . '.' . $extension;
        $target = GraphicalAbstractRepository::ensureDocumentsDir($docId) . $file;

        if (!move_uploaded_file($upload['tmp_name'], $target)) {
            throw new RuntimeException(sprintf('Unable to move the uploaded file to "%s"', $target));
        }

        chmod($target, 0644);

        return $file;
    }

    /**
     * @param array<string, string> $errors field name => error code
     * @return array<string, string> field name => translated message
     */
    private function translateErrors(array $errors): array
    {
        $messages = [];

        foreach ($errors as $field => $code) {
            $message = $this->view->translate(GraphicalAbstractValidator::MESSAGES[$code] ?? $code);
            $argument = GraphicalAbstractValidator::messageArgument($code);
            $messages[$field] = $argument !== null ? sprintf($message, $argument) : $message;
        }

        return $messages;
    }

    private function sendForbidden(): void
    {
        $this->sendErrors(403, [self::GLOBAL_MESSAGE => $this->view->translate('Erreur: modification non autorisée')]);
    }

    /**
     * @param array<string, string> $messages
     */
    private function sendErrors(int $httpCode, array $messages): void
    {
        $this->sendJson($httpCode, ['status' => 'error', 'messages' => $messages]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendJson(int $httpCode, array $payload): void
    {
        $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setHeader('Content-Type', 'application/json', true)
            ->setBody(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function disableView(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
    }
}
