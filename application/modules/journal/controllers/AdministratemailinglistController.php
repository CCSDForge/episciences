<?php
declare(strict_types=1);
use Episciences\MailingList\MailingList;
use Episciences\MailingList\Manager as MailingListsManager;

class AdministratemailinglistController extends Zend_Controller_Action
{
    public function init(): void
    {
        // Permission check: available only to epiadmin, chief_editor, administrator, secretary
        $isAllowed = Episciences_Auth::isSecretary() || 
                     Episciences_Auth::isChiefEditor() ||
                     Episciences_Auth::isAdministrator() ||
                     Episciences_Auth::isRoot();

        if (!$isAllowed) {
            $this->_helper->redirector->gotoUrl('/error/deny');
        }
    }

    public function indexAction(): void
    {
        MailingListsManager::ensureMandatoryList(RVID, strtolower((string)RVCODE) . '@' . DOMAIN);
        $this->view->mailingLists = MailingListsManager::getList(RVID);
        $this->view->memberCounts = MailingListsManager::getMemberCounts(RVID);
        $this->view->mandatoryName = strtolower((string)RVCODE) . '@' . DOMAIN;
        $this->view->deleteCsrfToken = Episciences_Csrf_Helper::generateToken('mailing_list_delete');
    }

    public function editAction(): void
    {
        $id = $this->_getParam('id');
        $csrfTokenName = 'mailing_list_edit_' . ($id ?: 'new');

        if ($id) {
            $list = MailingListsManager::getById((int)$id);
            if (!$list || $list->getRvid() !== (int)RVID) {
                $this->_helper->redirector->gotoSimple('index');
                return;
            }
        } else {
            // Check list limit for new lists
            $currentLists = MailingListsManager::getList(RVID);
            if (count($currentLists) >= MailingListsManager::MAX_MAILING_LISTS) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                    ->addMessage($this->view->translate('You have reached the maximum number of mailing lists allowed for this journal.'));
                $this->_helper->redirector->gotoSimple('index');
                return;
            }
            $list = new MailingList();
            $list->setRvid(RVID);
        }

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();

            if (!Episciences_Csrf_Helper::validateToken($csrfTokenName, $params[$csrfTokenName] ?? '')) {
                $this->_helper->redirector->gotoUrl('/error/deny');
                return;
            }

            $name = $params['name'] ?? '';
            $rvcode = strtolower((string)RVCODE);
            $suffix = '@' . DOMAIN;
            $fullName = MailingList::buildFullName($rvcode, $name);

            // 1. Ensure name uniqueness globally (across all journals)
            $existingList = MailingListsManager::getByName($fullName);
            if ($existingList && (!$id || $existingList->getId() !== (int)$id)) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                    ->addMessage($this->view->translate('A mailing list with this name already exists.'));
                $this->view->list = $list;
                $this->view->csrfToken = Episciences_Csrf_Helper::generateToken($csrfTokenName);
                return;
            }

            // 2. Prevent collisions with other journal mandatory names
            // If we are creating 'dev-test@domain', we must check if journal 'dev-test' exists.
            // If we are creating 'dev@domain', we already are journal 'dev' so it's fine.
            if ($name !== '') {
                $potentialCollidingCode = $rvcode . '-' . $name;
                if (MailingListsManager::journalCodeExists($potentialCollidingCode)) {
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                        ->addMessage($this->view->translate('This name collides with an existing journal code.'));
                    $this->view->list = $list;
                    $this->view->csrfToken = Episciences_Csrf_Helper::generateToken($csrfTokenName);
                    return;
                }
            }

            $list->setName($fullName);

            // Mandatory list (rvcode@domain) must be 'open'
            $mandatoryName = $rvcode . $suffix;
            if ($fullName === $mandatoryName) {
                $list->setType('mailing_list_type_open');
            } else {
                $allowedTypes = ['mailing_list_type_open', 'mailing_list_type_subscribers'];
                $type = $params['type'] ?? '';
                if (!in_array($type, $allowedTypes, true)) {
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                        ->addMessage($this->view->translate('Invalid list type.'));
                    $this->view->list = $list;
                    $this->view->csrfToken = Episciences_Csrf_Helper::generateToken($csrfTokenName);
                    return;
                }
                $list->setType($type);
            }

            $status = (int)($params['status'] ?? 1);
            if (!in_array($status, [0, 1], true)) {
                $status = 1;
            }
            $list->setStatus($status);
            
            try {
                $savedId = MailingListsManager::save($list);
            } catch (\OverflowException $e) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                    ->addMessage($this->view->translate('You have reached the maximum number of mailing lists allowed for this journal.'));
                $this->_helper->redirector->gotoSimple('index');
                return;
            }

            if (!$id) {
                // New list created: redirect to member management
                $this->_helper->redirector->gotoSimple('manage', null, null, ['id' => $savedId]);
            } else {
                $this->_helper->redirector->gotoSimple('index');
            }
            return;
        }

        $this->view->list = $list;
        $this->view->csrfToken = Episciences_Csrf_Helper::generateToken($csrfTokenName);
        $this->view->mandatoryName = strtolower((string)RVCODE) . '@' . DOMAIN;
    }

    public function manageAction(): void
    {
        $id = (int)$this->_getParam('id');
        $csrfTokenName = 'mailing_list_manage_' . $id;
        $list = MailingListsManager::getById($id);

        if (!$list || $list->getRvid() !== (int)RVID) {
            $this->_helper->redirector->gotoSimple('index');
            return;
        }

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();

            if (!Episciences_Csrf_Helper::validateToken($csrfTokenName, $params[$csrfTokenName] ?? '')) {
                $this->_helper->redirector->gotoUrl('/error/deny');
                return;
            }

            $rawRoles = is_array($params['roles'] ?? null) ? $params['roles'] : [];
            $rawUids  = is_array($params['uids'] ?? null) ? $params['uids'] : [];
            $list->setRoles(array_slice($rawRoles, 0, MailingListsManager::MAX_ROLES));
            $list->setUsers(array_map('intval', array_slice($rawUids, 0, MailingListsManager::MAX_USERS)));
            
            // Resolve members to check count. If zero, automatically close the list.
            $members = MailingListsManager::resolveMembers($list);
            if (empty($members)) {
                $list->setStatus(0); // Closed
            }

            MailingListsManager::save($list);
            
            $successMessage = $this->view->translate('Mailing list members saved successfully.');
            if (empty($members)) {
                $successMessage .= ' ' . $this->view->translate('The list has been automatically closed because it has no members.');
            }
            
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)
                ->addMessage($successMessage);

            $this->_helper->redirector->gotoSimple('manage', null, null, ['id' => $id]);
            return;
        }

        $this->view->list = $list;
        $this->view->csrfToken = Episciences_Csrf_Helper::generateToken($csrfTokenName);
        $this->view->previewCsrfToken = Episciences_Csrf_Helper::generateToken('mailing_list_preview');

        // Get available roles for the journal
        $acl = new Episciences_Acl();
        $roles = $acl->getRolesCodes();

        // Exclude restricted roles from mailing list selection
        unset(
            $roles[Episciences_Acl::ROLE_MEMBER],
            $roles[Episciences_Acl::ROLE_GUEST],
            $roles[Episciences_Acl::ROLE_REVIEWER],
            $roles[Episciences_Acl::ROLE_AUTHOR],
            $roles[Episciences_Acl::ROLE_ROOT]
        );

        $this->view->availableRoles = $roles;
        
        // Get user counts for each role
        $this->view->roleUserCounts = MailingListsManager::getUserCountByRole(RVID);
        
        // Restricted user list for Individual Members
        /** @var array<string> $allowedRoleIds */
        $allowedRoleIds = array_keys($roles);
        
        $usersWithRoles = Episciences_UsersManager::getUsersWithRoles($allowedRoleIds);
        
        // We also need to include users who are already in the list, even if they don't have the role anymore
        // to make sure they can be displayed and removed from the table.
        $uidsInList = $list->getUsers();
        foreach ($uidsInList as $uid) {
            if (!isset($usersWithRoles[$uid])) {
                $oUser = new Episciences_User();
                // findWithCAS() can return null when the user no longer exists,
                // but ZF1's fetchRow() return type (non-null) prevents PHPStan
                // from seeing the falsy path through the call chain.
                /** @phpstan-ignore if.alwaysTrue */
                if ($oUser->findWithCAS($uid)) {
                    $oUser->loadRoles();
                    $usersWithRoles[$uid] = $oUser;
                }
            }
        }

        $formattedUsers = [];
        $selectableUids = [];
        foreach ($usersWithRoles as $uid => $oUser) {
            $formattedUsers[$uid] = $oUser->toArray();
            
            // Check if user has at least one of the allowed roles
            $userRoles = $oUser->getRoles(RVID);
            if (!empty(array_intersect((array)$userRoles, $allowedRoleIds))) {
                $selectableUids[] = $uid;
            }
        }
        
        // Sort by name for the modal
        uasort($formattedUsers, function($a, $b) {
            return strcasecmp($a['LASTNAME'] ?? '', $b['LASTNAME'] ?? '') ?: strcasecmp($a['FIRSTNAME'] ?? '', $b['FIRSTNAME'] ?? '');
        });

        $this->view->journalUsers = $formattedUsers;
        $this->view->selectableUids = $selectableUids;
        $this->view->acl = $acl;

        // Get current resolved audience
        $this->view->resolvedMembers = MailingListsManager::resolveMembers($list);
    }

    /**
     * AJAX Action to preview the audience based on current form state
     */
    public function previewAction(): void
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->getRequest()->isPost()) {
            $this->getResponse()
                ->setHttpResponseCode(405)
                ->setHeader('Content-Type', 'application/json')
                ->setBody('{"error":"method_not_allowed"}');
            return;
        }

        $params = $this->getRequest()->getPost();

        if (!Episciences_Csrf_Helper::validateToken('mailing_list_preview', $params['mailing_list_preview'] ?? '')) {
            $this->getResponse()
                ->setHttpResponseCode(403)
                ->setHeader('Content-Type', 'application/json')
                ->setBody('{"error":"csrf_invalid"}');
            return;
        }

        $rawRoles = is_array($params['roles'] ?? null) ? $params['roles'] : [];
        $rawUids  = is_array($params['uids'] ?? null) ? $params['uids'] : [];

        // Cap array sizes to prevent oversized SQL IN() clauses
        $roles = array_slice($rawRoles, 0, MailingListsManager::MAX_ROLES);
        $uids  = array_map('intval', array_slice($rawUids, 0, MailingListsManager::MAX_USERS));

        // Validate that submitted UIDs actually belong to this journal
        if (!empty($uids)) {
            $journalUids = MailingListsManager::getJournalUids(RVID);
            $uids = array_values(array_intersect($uids, $journalUids));
        }

        $tempList = new MailingList();
        $tempList->setRvid(RVID);
        $tempList->setRoles($roles);
        $tempList->setUsers($uids);

        $members = MailingListsManager::resolveMembers($tempList);

        // Generate a new token for the next AJAX call (rotating token pattern)
        $nextToken = Episciences_Csrf_Helper::generateToken('mailing_list_preview');

        try {
            $body = json_encode(
                ['members' => $members, 'csrf' => $nextToken],
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            $this->getResponse()
                ->setHttpResponseCode(500)
                ->setHeader('Content-Type', 'application/json')
                ->setBody('{"error":"encoding_failed"}');
            return;
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody($body);
    }

    public function deleteAction(): void
    {
        if (!$this->getRequest()->isPost()) {
            $this->_helper->redirector->gotoSimple('index');
            return;
        }

        $params = $this->getRequest()->getPost();

        if (!Episciences_Csrf_Helper::validateToken('mailing_list_delete', $params['mailing_list_delete'] ?? '')) {
            $this->_helper->redirector->gotoUrl('/error/deny');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $list = MailingListsManager::getById($id);

        if ($list && $list->getRvid() === (int)RVID) {
            // Prevent deletion of mandatory list
            $mandatoryName = strtolower((string)RVCODE) . '@' . DOMAIN;
            if ($list->getName() === $mandatoryName) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)
                    ->addMessage($this->view->translate('The mandatory mailing list cannot be deleted.'));
            } else {
                MailingListsManager::delete($id);
            }
        }

        $this->_helper->redirector->gotoSimple('index');
    }
}
