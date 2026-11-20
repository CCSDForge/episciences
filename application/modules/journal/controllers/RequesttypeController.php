<?php

declare(strict_types=1);

use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeInput;
use Episciences\Reviewer\RequestTypeMailTemplate;
use Episciences\Reviewer\RequestTypeRepository;

/**
 * Review request types of the journal (#319): review report, quick opinion, and the types created by the journal
 * Each type selects the invitation e-mail sent to the reviewer, and its default due date.
 */
class RequesttypeController extends Zend_Controller_Action
{
    // the types are listed in a section of the rating grids page
    private const LIST_URL = '/grid/list#request-types-heading';

    private ?RequestTypeRepository $repository = null;

    /**
     * Former list page of the types: they are now listed in a section of the rating grids page
     */
    public function listAction(): void
    {
        $this->_helper->redirector->gotoUrl(self::LIST_URL);
    }

    /**
     * Form of a new type (no id) or of a type of the journal; saves it when posted
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     */
    public function editAction(): void
    {
        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = (int)$request->getParam('id');
        $type = $id > 0 ? $this->getRepository()->findById($id) : null;

        if ($id > 0 && ($type === null || $type->getRvid() !== RVID)) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->renderScript('index/notfound.phtml');
            return;
        }

        $languages = array_keys(Episciences_Tools::getLanguages()) ?: [Episciences_Review::DEFAULT_LANG];
        $this->view->type = $type;
        $this->view->languages = $languages;
        $this->view->errors = [];
        $this->view->values = self::formValues($type);
        $this->view->csrfToken = Episciences_Csrf_Helper::getSessionToken();

        if (!$request->isPost()) {
            return;
        }

        if (!Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->flash(Ccsd_View_Helper_Message::MSG_ERROR, 'Votre session a expiré, veuillez réessayer.');
            $this->_helper->redirector->gotoUrl(self::LIST_URL);
            return;
        }

        $post = $request->getPost();
        $input = new RequestTypeInput($post, RVID, $languages, $type);
        $errors = $input->getErrors();
        $saved = null;

        $typeToSave = $input->getType();

        if ($typeToSave !== null) {
            try {
                $saved = $this->getRepository()->save($typeToSave);
            } catch (LogicException) {
                $errors[RequestTypeInput::ERROR_FORM] = "Ce type de demande n'a pas pu être enregistré.";
            }
        }

        if ($saved === null) {
            $this->view->errors = $errors;
            $this->view->values = self::postedValues($post, $type);
            return;
        }

        if ($type === null && !RequestTypeMailTemplate::createFor($saved, (string)RVCODE)) {
            $this->flash(Ccsd_View_Helper_Message::MSG_WARNING, "Le type de demande a été enregistré, mais son modèle de message n'a pas pu être créé.");
        }

        $this->flash(Ccsd_View_Helper_Message::MSG_SUCCESS, 'Le type de demande a été enregistré.');

        if ($type !== null && $type->isLightAnswer() !== $saved->isLightAnswer()) {
            $this->flashPendingInvitationsKeepTheirMode((int)$saved->getId(), $type->isLightAnswer());
        }

        $this->_helper->redirector->gotoUrl(self::LIST_URL);
    }

    /**
     * Tells how many invitations waiting for an answer keep the mode of answer they were sent with, once the option
     * "answered without an account" of their type has changed (#319)
     */
    private function flashPendingInvitationsKeepTheirMode(int $typeId, bool $previousLightAnswer): void
    {
        $count = $this->getRepository()->countPendingInvitations($typeId, $previousLightAnswer, date('Y-m-d H:i:s'));

        if ($count === 0) {
            return;
        }

        $message = $count === 1
            ? $this->view->translate("1 invitation en attente de réponse garde le mode de réponse de son envoi.")
            : sprintf($this->view->translate("%d invitations en attente de réponse gardent le mode de réponse de leur envoi."), $count);
        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_INFO)->addMessage($message);
    }

    /**
     * Deletes a type of the journal that was never used; a used type can only be disabled
     * @throws Zend_Exception
     */
    public function deleteAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->flash(Ccsd_View_Helper_Message::MSG_ERROR, 'Votre session a expiré, veuillez réessayer.');
            $this->_helper->redirector->gotoUrl(self::LIST_URL);
            return;
        }

        $type = $this->getRepository()->findById((int)$request->getPost('id'));

        if ($type !== null && $type->getRvid() === RVID && $this->getRepository()->delete((int)$type->getId(), RVID)) {
            RequestTypeMailTemplate::deleteFor($type, (string)RVCODE);
            // the grid of the type, if the journal created one
            $gridFile = Episciences_GridsManager::filenameOf(Episciences_GridsManager::requestTypeGridKey((int)$type->getId()));
            if (Episciences_GridsManager::gridExists($gridFile)) {
                Episciences_GridsManager::delete($gridFile);
            }
            $this->flash(Ccsd_View_Helper_Message::MSG_SUCCESS, 'Le type de demande a été supprimé.');
        } else {
            $this->flash(Ccsd_View_Helper_Message::MSG_ERROR, 'Ce type de demande ne peut pas être supprimé (type par défaut ou déjà utilisé) : désactivez-le.');
        }

        $this->_helper->redirector->gotoUrl(self::LIST_URL);
    }

    /**
     * @return array{label: array<string, string>, deadline: string, deadline_unit: string, position: string, enabled: bool, light_answer: bool, counts_as_report: bool, visible_to_author: bool, instructions: array<string, string>}
     */
    private static function formValues(?RequestType $type): array
    {
        $deadline = $type?->getDefaultDeadline() !== null
            ? Episciences\Form\Validate\DeadlineUnit::parseStoredInterval($type->getDefaultDeadline())
            : null;

        return [
            'label' => $type?->getLabels() ?? [],
            'deadline' => $deadline !== null ? (string)$deadline[0] : '',
            'deadline_unit' => $deadline[1] ?? 'week',
            'position' => (string)($type?->getPosition() ?? 0),
            'enabled' => $type?->isEnabled() ?? true,
            'light_answer' => $type?->isLightAnswer() ?? false,
            'counts_as_report' => $type?->countsAsReport() ?? false,
            'visible_to_author' => $type?->isVisibleToAuthor() ?? false,
            'instructions' => $type?->getAllInstructions() ?? [],
        ];
    }

    /**
     * Posted values, shown again with the errors
     * @param array<string, mixed> $post
     * @return array{label: array<string, string>, deadline: string, deadline_unit: string, position: string, enabled: bool, light_answer: bool, counts_as_report: bool, visible_to_author: bool, instructions: array<string, string>}
     */
    private static function postedValues(array $post, ?RequestType $type): array
    {
        $labels = [];
        foreach ((array)($post[RequestTypeInput::FIELD_LABEL] ?? []) as $lang => $label) {
            if (is_string($lang) && is_string($label)) {
                $labels[$lang] = $label;
            }
        }

        $string = static fn(mixed $value): string => is_string($value) ? $value : '';

        return [
            'label' => $labels,
            'deadline' => $string($post[RequestTypeInput::FIELD_DEADLINE] ?? ''),
            'deadline_unit' => $string($post[RequestTypeInput::FIELD_DEADLINE . '_unit'] ?? 'week'),
            'position' => $string($post[RequestTypeInput::FIELD_POSITION] ?? ''),
            'enabled' => ($type?->isFullReport() ?? false) || !empty($post[RequestTypeInput::FIELD_ENABLED]),
            'light_answer' => !($type?->isFullReport() ?? false) && !empty($post[RequestTypeInput::FIELD_LIGHT_ANSWER]),
            'counts_as_report' => ($type?->isFullReport() ?? false) || !empty($post[RequestTypeInput::FIELD_COUNTS_AS_REPORT]),
            'visible_to_author' => ($type?->isFullReport() ?? false) || !empty($post[RequestTypeInput::FIELD_VISIBLE_TO_AUTHOR]),
            'instructions' => array_filter((array)($post[RequestTypeInput::FIELD_INSTRUCTIONS] ?? []), 'is_string'),
        ];
    }

    private function flash(string $namespace, string $message): void
    {
        $this->_helper->FlashMessenger->setNamespace($namespace)->addMessage($this->view->translate($message));
    }

    protected function getRepository(): RequestTypeRepository
    {
        return $this->repository ??= new RequestTypeRepository();
    }
}
