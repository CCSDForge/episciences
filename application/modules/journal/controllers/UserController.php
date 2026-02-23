<?php

require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

/**
 * Class UserController
 */
class UserController extends UserDefaultController
{
    public const MY_SUBMISSIONS_STR = 'my_submissions';
    public const ASSIGNED_ARTICLES_STR = 'assigned_articles';

    /**
     * Page d'accueil d'un utilisateur connecté
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception|JsonException
     */
    public function dashboardAction(): void
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $this->view->review = $review;

        // Bloc Mon compte
        /** @var Episciences_User $user */
        $user = Episciences_Auth::getInstance()->getIdentity();
        $this->view->user = $user->toArray();
        $this->view->user['editorSections'] = null;

        // Bloc "Gérer la revue"
        if (Episciences_Auth::isSecretary() || (Episciences_Auth::isEditor(RVID, true) && !$review->getSetting('encapsulateEditors'))) {
            $settings = [
                'isNot' =>
                    [
                        'status' => Episciences_Paper::NOT_LISTED_STATUS
                    ]
            ];

            $this->view->allPapers = $review->getPapers($settings, false, ['DOCID','STATUS']);

            if (Episciences_Auth::isSecretary()) { // Alert on the existence of papers without assigned editors

                $settings['is']['status'] = array_diff(Episciences_PapersManager::getAllStatus(RVID, 'ASC'), Episciences_Paper::$_noEditableStatus);

                if (!empty($settings['is']['status'])) {
                    $settings['is']['editors'] = [Episciences_View_Helper_PaperFilter::NONE_KEY];

                    $this->view->onlyEditablePapersWithoutEditors = $review->getPapers($settings, false, ['DOCID','STATUS']);
                }
            }
        }

        // Bloc "Articles assignés"
        if (Episciences_Auth::isChiefEditor() || Episciences_Auth::isEditor() || Episciences_Auth::isGuestEditor()) {
            $editor = new Episciences_Editor(Episciences_Auth::getUser()->toArray());
            /** Episciences_Editor $editor */
            try {
                $editor->loadAssignedPapers(['isNot' => ['status' => Episciences_Paper::NOT_LISTED_STATUS]]);
                $assignedPapers = $editor->getAssignedPapers();
            } catch (Zend_Exception $e) {
                trigger_error('FAILED_TO_LOAD_ASSIGNED_PAPERS_TO_EDITOR_' . $editor->getUid() . ' : ' . $e, E_USER_WARNING);
                $assignedPapers = [];
            }

            $this->view->assignedPapers = $assignedPapers;
        }

        // Les articles assignés pour le copy editing

        $copyEditor = new Episciences_CopyEditor(Episciences_Auth::getUser()->toArray());
        try {
            $copyEditor->loadAssignedPapers(['isNot' => ['status' => Episciences_Paper::NOT_LISTED_STATUS]]);
            $assignedPapersToCopyEditing = $copyEditor->getAssignedPapers();
        } catch (Zend_Exception $e) {
            trigger_error('FAILED_TO_LOAD_ASSIGNED_PAPERS_TO_COPYEDITOR_' . $copyEditor->getUid() . ' : ' . $e, E_USER_WARNING);
            $assignedPapersToCopyEditing = [];

        }

        $this->view->assignedPapersToCopyEditing = $assignedPapersToCopyEditing;

        // Bloc "Mes articles"
        $settings = [
            'is' => ['uid' => Episciences_Auth::getUid()],
            'isNot' => ['status' => Episciences_Paper::NOT_LISTED_STATUS]
        ];
        $this->view->submittedPapers = $review->getPapers($settings);

        // Bloc "Mes relectures"

        $reviewer = new Episciences_Reviewer();
        $reviewer->find(Episciences_Auth::getUid());

        $papers = $reviewer->getAssignedPapers(['is' => ['rvid' => RVID]], true);
        /** @var Episciences_Paper $paper */
        foreach ($papers as $paper) {
            $reviewer->getReviewing($paper->getDocid());
        }
        $this->view->reviewings = $reviewer->getReviewings();

    }

    /**
     * décrit les différents niveaux de permission des utilisateurs ['exclut le rôle "root(epiadmin)" et ses resources
     * @throws Zend_Controller_Exception
     */
    public function permissionsAction(): void
    {
        // Personalisation des menus : ignorer l'affichage des pages personnalisées et les répertoires
        $ignoredControllerName = ['page', 'folder'];

        /** @var Episciences_Auth_Plugin $epiAuthPlugin */
        $epiAuthPlugin = $this->getFrontController()->getPlugin('Episciences_Auth_Plugin');
        /** @var Episciences_Acl $aclFromPlugin */
        $aclFromPlugin = $epiAuthPlugin->getAcl();
        /** @var array $resources */
        $roles = $aclFromPlugin->getRolesCodes();
        // unset root
        unset($roles[Episciences_Acl::ROLE_ROOT], $roles[Episciences_Acl::ROLE_GUEST]); // github #166
        $resources = $aclFromPlugin->getResources();
        $permissions = [];

        // les resources à ne pas afficher
        foreach (Episciences_Acl::TYPE_OF_RESOURCES_NOT_TO_BE_DISPLAYED as $excludedResource) {
            $key = array_search($excludedResource, $resources, true);
            if($key !== false){
                unset($resources[$key]);
            }
        }

        foreach ($resources as $resource) {
            // Aussi, les resources à ne pas afficher
            $explodedResource = explode('-', $resource);

            if (in_array($explodedResource[0], $ignoredControllerName, true)) {
                $key = array_search($resource, $resources, true);

                if(false !== $key){
                    unset($resources[$key]);
                    continue;
                }
            }

            foreach ($roles as $role) {
                $isAllowed = $aclFromPlugin->isAllowed($role, $resource);
                if ($isAllowed && (array_key_exists($role, Episciences_Acl::CONFIGURABLE_RESOURCES) && array_key_exists($resource, Episciences_Acl::CONFIGURABLE_RESOURCES[$role]))) {
                    $permissions[$resource][$role] = Episciences_Acl::CONFIGURABLE_RESOURCES[$role][$resource] ? 'configurable' : !$isAllowed;
                } else {
                    $permissions[$resource][$role] = $isAllowed;
                }
            }
        }

        asort($resources);

        $this->view->roles = $roles;
        $this->view->resources = $resources;
        $this->view->permissions = $permissions;
    }

}
