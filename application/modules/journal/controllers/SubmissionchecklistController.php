<?php

declare(strict_types=1);

use Episciences\Submit\Checklist\ChecklistItem;
use Episciences\Submit\Checklist\ChecklistPostedRows;
use Episciences\Submit\Checklist\ChecklistProvider;
use Episciences\Submit\Checklist\ChecklistRepository;
use Episciences\Submit\Checklist\ChecklistValidator;
use Episciences\Submit\Checklist\JournalChecklist;

/**
 * Journal settings > Submission checklist (/submissionchecklist).
 *
 * - Journal without own list: the default list and a "Customise" button, which opens an editor
 *   pre-filled with the default list. Nothing is written until "Save".
 * - Journal with its own list: its list and an "Edit" button opening the editor (same layout), and
 *   "Back to the default list" (deletes the row).
 *
 * Roles: secretary and above (journal.navigation.json, acl.ini). Every POST checks its form token,
 * then redirects to the page (PRG); a list with errors is shown again, nothing saved.
 *
 */
class SubmissionchecklistController extends Zend_Controller_Action
{
    public const TOKEN_SAVE = 'submission_checklist_save';
    public const TOKEN_RESET = 'submission_checklist_reset';

    private const MSG_SAVED = 'Les modifications ont bien été enregistrées.';
    private const MSG_EXPIRED = 'Votre session a expiré, veuillez réessayer.';
    private const MSG_RESET = 'Your journal now uses the default checklist.';

    private ?ChecklistRepository $repository = null;
    private ?ChecklistProvider $provider = null;

    public function indexAction(): void
    {
        $this->showPage();
    }

    /**
     * Saves the list of the journal: creates its row the first time ("Customise" then "Save").
     */
    public function saveAction(): void
    {
        if (!$this->isValidPost(self::TOKEN_SAVE)) {
            return;
        }

        $rows = ChecklistPostedRows::toRaw($this->getRequest()->getPost('items'));
        $result = (new ChecklistValidator())->validate($rows, $this->currentList());

        if ($result['checklist'] === null) {
            $this->showPage(['rows' => $rows, 'errors' => $result['errors']]);
            $this->_helper->viewRenderer->setRender('index');
            return;
        }

        $this->repository()->save(RVID, $result['checklist']);
        $this->redirectToPage('success', self::MSG_SAVED);
    }

    /**
     * Deletes the journal row: the journal follows the default list again.
     */
    public function resetAction(): void
    {
        if (!$this->isValidPost(self::TOKEN_RESET)) {
            return;
        }

        $this->repository()->delete(RVID);
        $this->redirectToPage('success', self::MSG_RESET);
    }

    /**
     * List the posted keys are checked against: the journal list, or the default list the editor
     * was pre-filled with (its keys are kept, so the checkboxes keep their element IDs).
     */
    private function currentList(): JournalChecklist
    {
        return $this->repository()->find(RVID) ?? new JournalChecklist($this->provider()->defaultItems());
    }

    /**
     * View variables of the page.
     *
     * @param array{rows: list<array<string, mixed>>, errors: list<array{message: string, item: ?int}>}|null $posted
     *        rows and errors of a list posted with errors (null: rows of the stored or default list)
     */
    private function showPage(?array $posted = null): void
    {
        $view = $this->view;
        $isCustomised = $this->provider()->isCustomised(RVID);
        $defaultItems = $this->provider()->defaultItems();

        $view->isCustomised = $isCustomised;
        $view->locale = Episciences_Tools::getLocale() ?? ChecklistItem::DEFAULT_LOCALE;
        $view->maxItems = ChecklistValidator::MAX_ITEMS;
        $view->maxLength = ChecklistValidator::MAX_LABEL_LENGTH;

        // The list shown read-only and in the editor: the journal list, or the default list to start
        // customising from
        $items = $isCustomised ? ($this->repository()->find(RVID)->items ?? []) : $defaultItems;
        $view->listItems = $items;
        $view->rows = $posted['rows'] ?? self::rows($items);
        $view->errors = $posted['errors'] ?? [];
        // The editor opens with "Customise" / "Edit"; a list refused with errors keeps it open
        $view->editorOpen = $posted !== null;

        $view->tokens = [
            'save' => Episciences_Csrf_Helper::generateToken(self::TOKEN_SAVE),
            'reset' => $isCustomised ? Episciences_Csrf_Helper::generateToken(self::TOKEN_RESET) : null,
        ];
    }

    /**
     * A POST carrying the valid form token; otherwise redirects to the page.
     */
    private function isValidPost(string $tokenName): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->redirectToPage();
            return false;
        }

        if (!Episciences_Csrf_Helper::validateToken($tokenName, (string)$request->getPost($tokenName, ''))) {
            $this->redirectToPage(Ccsd_View_Helper_Message::MSG_ERROR, self::MSG_EXPIRED);
            return false;
        }

        return true;
    }

    private function redirectToPage(?string $namespace = null, string $message = ''): void
    {
        if ($namespace !== null) {
            $this->_helper->FlashMessenger->setNamespace($namespace)->addMessage($this->view->translate($message));
        }

        $this->_helper->redirector->gotoUrl($this->_helper->url('index', 'submissionchecklist'));
    }

    /**
     * @param list<ChecklistItem> $items
     * @return list<array{key: ?string, labels: array{en: string, fr: string}}>
     */
    private static function rows(array $items): array
    {
        return array_map(static fn(ChecklistItem $item): array => $item->toArray(), $items);
    }

    private function repository(): ChecklistRepository
    {
        return $this->repository ??= new ChecklistRepository();
    }

    private function provider(): ChecklistProvider
    {
        return $this->provider ??= new ChecklistProvider($this->repository());
    }
}
