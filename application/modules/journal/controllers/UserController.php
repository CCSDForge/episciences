<?php

require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

/**
 * Class UserController
 */
class UserController extends UserDefaultController
{
    /**
     * Page d'accueil d'un utilisateur connecté
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     */
    public function dashboardAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $this->view->review = $review;

        // Bloc Mon compte
        $this->view->user = Episciences_Auth::getInstance()->getIdentity()->toArray();
        $this->view->user['editorSections'] = null;

        // Bloc "Gérer la revue"
        if (Episciences_Auth::isChiefEditor() || Episciences_Auth::isAdministrator() || Episciences_Auth::isSecretary() || (Episciences_Auth::isEditor(RVID, true) && !$review->getSetting('encapsulateEditors'))) {
            $settings = array('isNot' => array('status' => array(Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED)));
            $this->view->allPapers = $review->getPapers($settings);
        }

        // Bloc "Articles assignés"
        if (Episciences_Auth::isChiefEditor() || Episciences_Auth::isEditor() || Episciences_Auth::isGuestEditor()) {
            $editor = new Episciences_Editor(Episciences_Auth::getUser()->toArray());
            /** Episciences_Editor $editor */
            try {
                $editor->loadAssignedPapers(array('isNot' => array('status' => array(Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED))));
                $assignedPapers = $editor->getAssignedPapers();
            } catch (Zend_Exception $e) {
                error_log('FAILED_TO_LOAD_ASSIGNED_PAPERS_TO_EDITOR_' . $editor->getUid() . ' : ' . $e);
                $assignedPapers = [];
            }

            $this->view->assignedPapers = $assignedPapers;
        }

        // Les articles assignés pour le copy editing

        $copyEditor = new Episciences_CopyEditor(Episciences_Auth::getUser()->toArray());
        try {
            $copyEditor->loadAssignedPapers(['isNot' => ['status' => [Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED]]]);
            $assignedPapersToCopyEditing = $copyEditor->getAssignedPapers();
        } catch (Zend_Exception $e) {
            error_log('FAILED_TO_LOAD_ASSIGNED_PAPERS_TO_COPYEDITOR_' . $copyEditor->getUid() . ' : ' . $e);
            $assignedPapersToCopyEditing = [];

        }

        $this->view->assignedPapersToCopyEditing = $assignedPapersToCopyEditing;

        // Bloc "Mes articles"
        $settings = array(
            'is' => array('uid' => Episciences_Auth::getUid()),
            'isNot' => array('status' => array(Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED)));
        $this->view->submittedPapers = $review->getPapers($settings);

        // Bloc "Mes relectures"

        $reviewer = new Episciences_Reviewer();
        $reviewer->find(Episciences_Auth::getUid());

        $papers = $reviewer->getAssignedPapers(array('is' => array('rvid' => RVID)), true);
        /** @var Episciences_Paper $paper */
        foreach ($papers as $paper) {
            $reviewer->getReviewing($paper->getDocid());
        }
        $this->view->reviewings = $reviewer->getReviewings();


        /*
        * Récupérer liste des articles qui m'ont été assignés pour relecture
        * Enregistrer un statut pour chaque relecture (assigné, en cours, terminée)
        * >> Quand un article m'est assigné (accepté) pour relecture, enregistrer une grille
        * dans paper_rating_grid avec statut 0 (assigné). devient 1 quand commencé, 2 quand terminé.
        */

    }

    /**
     * décrit les différents niveaux de permission des utilisateurs ['exclut le rôle "root(epiadmin)" et ses resources
     * @throws Zend_Controller_Exception
     */
    public function permissionsAction()
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
        unset($roles[Episciences_Acl::ROLE_ROOT]);
        $resources = $aclFromPlugin->getResources();
        $permissions = [];

        // les resources à ne pas afficher
        foreach (Episciences_Acl::TYPE_OF_RESOURCES_NOT_TO_BE_DISPLAYED as $excludedResource) {
            unset($resources[array_search($excludedResource, $resources, true)]);
        }

        foreach ($resources as $resource) {
            // Aussi, les resources à ne pas afficher
            $explodedResource = explode('-', $resource);

            if (in_array($explodedResource[0], $ignoredControllerName)) {
                unset($resources[array_search($resource, $resources, true)]);
                continue;
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
