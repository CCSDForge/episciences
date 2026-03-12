<?php

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
        $this->view->mailingLists = MailingListsManager::getList(RVID);
        $this->view->memberCounts = MailingListsManager::getMemberCounts(RVID);
    }

    public function editAction(): void
    {
        $id = $this->_getParam('id');
        if ($id) {
            $list = MailingListsManager::getById((int)$id);
            if (!$list || $list->getRvid() != RVID) {
                $this->_helper->redirector->gotoSimple('index');
                return;
            }
        } else {
            $list = new MailingList();
            $list->setRvid(RVID);
        }

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $name = $params['name'] ?? '';
            
            // Sanitize user part: only alphanumeric, dots, dashes, underscores
            $name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name);
            $name = strtolower((string)$name);

            // Automatically build the final email-like name: rvcode-name@domain
            $prefix = strtolower((string)RVCODE) . '-';
            $suffix = '@' . DOMAIN;
            
            // Strip existing prefix/suffix if user pasted them by mistake
            if (stripos($name, $prefix) === 0) {
                $name = substr($name, strlen($prefix));
            }
            if (strpos($name, $suffix) !== false) {
                $name = str_replace($suffix, '', $name);
            }

            $list->setName($prefix . $name . $suffix);
            $list->setType($params['type'] ?? '');
            $list->setStatus((int)($params['status'] ?? 1));
            
            MailingListsManager::save($list);
            $this->_helper->redirector->gotoSimple('index');
            return;
        }

        $this->view->list = $list;
    }

    public function manageAction(): void
    {
        $id = (int)$this->_getParam('id');
        $list = MailingListsManager::getById($id);

        if (!$list || $list->getRvid() != RVID) {
            $this->_helper->redirector->gotoSimple('index');
            return;
        }

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $list->setRoles($params['roles'] ?? []);
            $list->setUsers($params['uids'] ?? []);
            
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
                /** @phpstan-ignore-next-line */
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
            $found = false;
            foreach ($userRoles as $roleId) {
                foreach ($allowedRoleIds as $allowedId) {
                    if ((string)$roleId === (string)$allowedId) {
                        $found = true;
                        break 2;
                    }
                }
            }
            
            if ($found) {
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
            return;
        }

        $params = $this->getRequest()->getPost();
        $roles = $params['roles'] ?? [];
        $uids = $params['uids'] ?? [];

        $tempList = new MailingList();
        $tempList->setRvid(RVID);
        $tempList->setRoles($roles);
        $tempList->setUsers($uids);

        $members = MailingListsManager::resolveMembers($tempList);
        
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($members));
    }

    public function deleteAction(): void
    {
        $id = (int)$this->_getParam('id');
        $list = MailingListsManager::getById($id);

        if ($list && $list->getRvid() == RVID) {
            MailingListsManager::delete($id);
        }

        $this->_helper->redirector->gotoSimple('index');
    }
}
