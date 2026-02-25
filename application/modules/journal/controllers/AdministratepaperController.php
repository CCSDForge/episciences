<?php

use Psr\Log\LogLevel;

require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

/**
 * Class AdministratepaperController
 */
class AdministratepaperController extends PaperDefaultController
{
    public const ACTION_ASSIGNED = 'assigned';
    public const DATATABLE_COLUMNS = [
        '0' => 'paperid',
        '1' => 'docid',
        '2' => 'status',
        '3' => '',//  ***
        '4' => 'vid',
        '5' => 'sid',
        '6' => '', // ***
        '7' => '',// ***
        '8' => '',// *** (désactiver dans js/paper/submitted.js) sinon prévoir une jointure si nécessaire
        '9' => '',
        '10' => 'when',
        '11' => 'publication_date'
    ];


    /**
     * @throws Zend_Exception
     */
    public function indexAction(): void
    {
        $this->listAction();
    }

    /**
     * Liste tous les articles
     * @throws Zend_Exception
     */
    public function listAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // if editors encapsulation is on, redirect editors to "assigned papers" page
        if (Episciences_Auth::isAllowedToListOnlyAssignedPapers()) {
            $this->_helper->redirector->gotoUrl($this->url(['action' => self::ACTION_ASSIGNED, 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER]));
        }

        // Ajax dataTable
        if ($request->isXmlHttpRequest()) {

            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $review = Episciences_ReviewsManager::find(RVID);
            $review->loadSettings();

            $this->view->review = $review;

            $volumes = $review->getVolumes();
            // load volumes settings
            Episciences_VolumesManager::loadSettingsForVolumes($volumes);

            $sections = $review->getSections();

            //load  sections settings
            Episciences_SectionsManager::loadSettingsForSections($sections);

            $post = $request->getParams();
            $draw = isset($post['draw']) ? (int)$post['draw'] : 1;

            $limit = Ccsd_Tools::ifsetor($post['length'], '10');
            $offset = Ccsd_Tools::ifsetor($post['start'], '0');
            $list_search = Ccsd_Tools::ifsetor($post['search']['value'], '');

            /** L'ordre est un tableau de tableaux, chaque tableau intérieur étant composé de deux éléments:
             * index de la colonne et la direction
             */
            $requestOrder = Ccsd_Tools::ifsetor($post['order'], []);

            $is = Episciences_PapersManager::getFiltersParams();
            unset($is['controller'], $is['action'], $is['module']);

            $settings = [
                'is' => $is,
                'isNot' => ['status' => Episciences_Paper::NOT_LISTED_STATUS],
                'limit' => $limit,
                'offset' => $offset,
            ];

            if (!empty($requestOrder)) {
                $settings['order'] = Episciences_Tools::dataTableOrder($requestOrder, self::DATATABLE_COLUMNS);
            }

            // Pour limiter le nombre de requêtes SQL

            if (!empty($volumes)) {
                $settings['volumes'] = $volumes;
            }

            if (!empty($sections)) {
                $settings['sections'] = $sections;
            }

            $list_search = trim($list_search);

            if ($list_search !== '') {
                $settings['list_search'] = $list_search;
            }

            // Le nombre total d'enregistrements, avant filtrage
            $papersCount = $review->getPapersCount($settings);
            // Le nombre total d'eregistrements, après filtrage
            $papersFiltredCount = $review->getPapersCount($settings, true);
            // La liste des articles, après filtrage
            $papers = $review->getPapers($settings, true);

            $isCoiEnabled = $review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED);

            foreach ($papers as &$paper) {
                $paper->loadSubmitter(false);
                $paper->getEditors();
                $paper->getCopyEditors();
                $paper->getRatings();
                $paper->getReviewers(
                    [
                        Episciences_User_Assignment::STATUS_ACTIVE,
                        Episciences_User_Assignment::STATUS_PENDING,
                        Episciences_User_Assignment::STATUS_DECLINED
                    ]
                ); // environ 1s

                $paper->setRevisionDeadline();

                if ($isCoiEnabled) {
                    $paper->getConflicts();
                }

            }

            unset($paper);

            $tbody = ($papersFiltredCount > 0) ?
                $this->view->partial('administratepaper/datatable_list.phtml', [
                    'list' => $papers,
                    'volumes' => $volumes,
                    'sections' => $sections,
                    'isCoiEnabled' => $review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED)
                ]) :
                '';

            echo Episciences_Tools::getDataTableData($tbody, $draw, $papersCount, $papersFiltredCount);
        }
    }

    /**
     * AJAX endpoint to request a new DOI assignment for a paper.
     *
     * Returns a JSON object:
     *   - doi:           DOI link HTML, or the string 'Error'
     *   - doi_status:    Status badge HTML, or the string 'Error'
     *   - feedback:      Success message (trimmed, may be empty)
     *   - error_message: Error detail  (trimmed, may be empty)
     *
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function ajaxrequestnewdoiAction(): void
    {
        // Disable layout and view rendering unconditionally so that every
        // early-return path (auth failure, bad input…) sends clean JSON only.
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // Require both an XHR and a POST to prevent cross-site form submissions
        // and direct browser navigation to this endpoint.
        if (!$request->isPost() || !$request->isXmlHttpRequest()) {
            return;
        }

        if (!Episciences_Auth::isLogged() || !Episciences_Auth::isAllowedToManageDoi()) {
            trigger_error(
                sprintf('Unauthorized access to requestNewDoi by uid=%s', Episciences_Auth::getUid()),
                E_USER_WARNING
            );
            echo json_encode([
                'doi'           => 'Error',
                'doi_status'    => 'Error',
                'feedback'      => '',
                'error_message' => 'Unauthorized access',
            ]);
            return;
        }

        // Validate and sanitize the document identifier.
        $docId = (int) $request->getPost('docid');
        if ($docId <= 0) {
            echo json_encode([
                'doi'           => 'Error',
                'doi_status'    => 'Error',
                'feedback'      => '',
                'error_message' => $this->view->translate('Invalid document identifier.'),
            ]);
            return;
        }

        // PapersManager::get() returns false when no matching paper is found;
        // calling methods on false would cause a fatal error.
        $paper = Episciences_PapersManager::get($docId);
        if (!$paper instanceof Episciences_Paper) {
            echo json_encode([
                'doi'           => 'Error',
                'doi_status'    => 'Error',
                'feedback'      => '',
                'error_message' => $this->view->translate('Document not found.'),
            ]);
            return;
        }

        if (!$paper->canBeAssignedDOI()) {
            echo json_encode([
                'doi'           => 'Error',
                'doi_status'    => 'Error',
                'feedback'      => '',
                'error_message' => $this->view->translate('Le statut du document ne permet pas de lui assigner un DOI'),
            ]);
            return;
        }

        $resCreateDoi = Episciences_Paper::createPaperDoi(RVID, $paper);

        $doi          = 'Error';
        $doiStatus    = 'Error';
        $feedback     = '';
        $errorMessage = '';
        $doiStr       = '';

        if ($resCreateDoi['resUpdateDoi'] > 0) {
            $doiStr    = trim($resCreateDoi['doi'] ?? '');
            $doi       = Episciences_View_Helper_DoiAsLink::DoiAsLink($resCreateDoi['doi']);
            $feedback .= ' ' . $this->view->translate('DOI créé.');
        } else {
            $errorMessage .= ' ' . $this->view->translate('Erreur lors de la creation du DOI.');
            trigger_error(
                sprintf('Error updating DOI "%s" for paperId=%d', $resCreateDoi['doi'], $paper->getPaperid()),
                E_USER_WARNING
            );
        }

        if ($resCreateDoi['resUpdateDoiQueue'] > 0) {
            $doiStatus = sprintf(
                Episciences_Paper_DoiQueue::getStatusHtmlTemplate(Episciences_Paper_DoiQueue::STATUS_ASSIGNED),
                $this->view->translate(Episciences_Paper_DoiQueue::STATUS_ASSIGNED)
            );
            $feedback .= ' ' . $this->view->translate('Statut du DOI modifié.');
        } else {
            $errorMessage .= ' ' . $this->view->translate('Erreur lors de la sauvegarde du statut du DOI.');
            trigger_error(
                sprintf('Error updating Queue "%s" for paperId=%d', $resCreateDoi['doi'], $paper->getPaperid()),
                E_USER_WARNING
            );
        }

        echo json_encode([
            'doi'           => trim($doi),
            'doiStr'        => $doiStr,
            'doi_status'    => trim($doiStatus),
            'feedback'      => trim($feedback),
            'error_message' => trim($errorMessage),
        ]);
    }


    /**
     * list papers assigned to user
     * @throws Zend_Exception
     */
    public function assignedAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {

            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $review = Episciences_ReviewsManager::find(RVID);
            $review->loadSettings();
            $volumes = $review->getVolumes();
            $sections = $review->getSections();

            $post = $request->getParams();
            $limit = Ccsd_Tools::ifsetor($post['length'], '10');
            $offset = Ccsd_Tools::ifsetor($post['start'], '0');
            $list_search = Ccsd_Tools::ifsetor($post['search']['value'], '');
            // L'ordre est un tableau de tableaux, chaque tableau intérieur étant composé de deux éléments:
            // index de la colonne et la direction
            $requestOrder = Ccsd_Tools::ifsetor($post['order'], []);

            $is = Episciences_PapersManager::getFiltersParams();

            if (array_key_exists('ce', $is) && filter_var($is['ce'], FILTER_VALIDATE_BOOLEAN)) {
                $user = new Episciences_CopyEditor(Episciences_Auth::getUser()->toArray());
            } else {
                $user = new Episciences_Editor(Episciences_Auth::getUser()->toArray());
            }

            if (RVID !== 0) {
                // only get papers submitted to this review
                $is['rvid'] = RVID;
            }

            $settings = [
                'is' => $is,
                'isNot' => ['status' => Episciences_Paper::NOT_LISTED_STATUS],
                'limit' => $limit,
                'offset' => $offset
            ];

            // Pour limiter le nombre de requêtes SQL
            if (!empty($volumes)) {
                $settings['volumes'] = $volumes;
            }

            if (!empty($sections)) {
                $settings['sections'] = $sections;
            }

            if (!empty($requestOrder)) {
                $settings['order'] = Episciences_Tools::dataTableOrder($requestOrder, self::DATATABLE_COLUMNS);
            }

            $list_search = trim($list_search);

            if ($list_search !== '') {
                $settings['list_search'] = $list_search;
            }

            // Total des articles assignés
            $allPapersCount = count($user->loadAssignedPapers());

            // Total des articles assignés, après filtrage
            $allPapersFiltredCount = count($user->loadAssignedPapers($settings, true, false));

            // liste des articles à afficher
            $papers = $user->loadAssignedPapers($settings, true);

            $isCoiEnabled = $review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED);


            /** @var Episciences_Paper $paper */
            foreach ($papers as &$paper) {
                $paper->loadSubmitter(false);
                $paper->getEditors(true, true);
                $paper->getRatings();
                $paper->getReviewers([Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_PENDING], true);
                $paper->setRevisionDeadline();

                if ($isCoiEnabled) {
                    $paper->getConflicts();
                }
            }
            unset($paper);
            $tbody = (count($papers) > 0) ?
                $this->view->partial('administratepaper/datatable_list.phtml', [
                    'list' => $papers,
                    'volumes' => $volumes,
                    'sections' => $sections,
                    'isCoiEnabled' => $review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED)
                ]) :
                '';

            echo Episciences_Tools::getDataTableData($tbody, $post['draw'], $allPapersCount, $allPapersFiltredCount);
            return;

        }

        $this->view->pageDescription = "Gestion des articles qui m'ont été assignés.";

        $this->render('list');
    }

    /**
     * Do not seem to be called
     * Seems deprecated
     * @throws Zend_Exception
     * @deprecated
     */
    public function ajaxcontrolboardAction(): void
    {
        trigger_error('ajaxcontrolboardAction is deprecated.', E_USER_DEPRECATED);
        return;

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $params = $request->getParams();
        $this->_helper->layout()->disableLayout();


        if ($request->isXmlHttpRequest()) {

            $review = Episciences_ReviewsManager::find(RVID);
            $volumes = $review->getVolumes();
            $sections = $review->getSections();

            $limit = Ccsd_Tools::ifsetor($params['iDisplayLength'], '10');
            $offset = Ccsd_Tools::ifsetor($params['iDisplayStart'], '0');

            $settings = [
                'is' => Episciences_PapersManager::getFiltersParams(),
                'isNot' => ['status' => Episciences_Paper::NOT_LISTED_STATUS],
                'limit' => $limit,
                'offset' => $offset];

            $papers = $review->getPapers($settings);

            foreach ($papers as &$paper) {
                $paper->loadSubmitter(false);
                $paper->getEditors();
                $paper->getRatings();
                $paper->getReviewers([Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_PENDING]); // environ 1s
            }
            unset($paper);

            $output = ["sEcho" => (int)$_GET['sEcho'], "aaData" => []];
            $output['iTotalRecords'] = $review->getPapersCount($settings);
            $output['iTotalDisplayRecords'] = $review->getPapersCount($settings);

            $this->view->output = $output;
            $this->view->papers = $papers;
            $this->view->volumes = $volumes;
            $this->view->sections = $sections;
        }

    }

    /**
     * fetch master volume selection form (popover)
     */
    public function volumeformAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $params = $request->getPost();
        $docId = $params['docid'];

        $paper = Episciences_PapersManager::get($docId, false);

        $review = Episciences_ReviewsManager::find(RVID);
        $volumes = $review->getVolumes();

        $this->_helper->layout()->disableLayout();
        $this->view->docid = $docId;
        $this->view->volumes = $volumes;
        $this->view->vid = $paper->getVid();
        $this->view->paperPosition = $paper->getPosition();
        $this->renderScript(self::ADMINISTRATE_PAPER_CONTROLLER . '/master_volume_form.phtml');
    }

    /**
     * fetch secondary volumes selection form (popover)
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function othervolumesformAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $params = $request->getPost();
        $docId = $params['docid'];

        $paper = Episciences_PapersManager::get($docId);
        $paper->loadOtherVolumes();

        // fetch paper secondary volumes
        $vids = [];
        /** @var Episciences_Volume $paper_volume */
        foreach ($paper->getOtherVolumes() as $paper_volume) {
            $vids[] = $paper_volume->getVid();
        }

        // fetch volumes list
        $review = Episciences_ReviewsManager::find(RVID);
        $volumes = [];
        /** @var Episciences_Volume $volume */
        foreach ($review->getVolumes() as $volume) {
            // remove article master volume from the list, if there is one
            if ($volume->getVid() === $paper->getVid()) {
                continue;
            }
            $volumes[] = [
                'vid' => $volume->getVid(),
                'name' => $volume->getName(),
                'checked' => in_array($volume->getVid(), $vids, true)
            ];
        }
        //alphabetic sort
        usort($volumes, static function ($a, $b) {
            return strcmp($a["name"], $b["name"]);
        });

        $this->_helper->layout()->disableLayout();
        $this->view->docid = $docId;
        $this->view->volumes = $volumes;
        $this->renderScript(self::ADMINISTRATE_PAPER_CONTROLLER . '/other_volumes_form.phtml');
    }

    /**
     * fetch section selection form (popover)
     */
    public function sectionformAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $params = $request->getPost();
        $docId = $params['docid'];

        $paper = Episciences_PapersManager::get($docId);

        $review = Episciences_ReviewsManager::find(RVID);
        $sections = $review->getSections();

        $this->_helper->layout()->disableLayout();
        $this->view->docid = $docId;
        $this->view->sections = $sections;
        $this->view->sid = $paper->getSid();
        $this->renderScript(self::ADMINISTRATE_PAPER_CONTROLLER . '/section_form.phtml');
    }

    /**
     * paper administration page
     * TODO: split this into smaller functions
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Json_Exception
     */
    public function viewAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = (int)$request->getParam('id');
        // get journal details
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        $this->view->review = $review;

        // get paper details
        $paper = Episciences_PapersManager::get($docId);

        // check if paper exists
        if (!$paper || $paper->getRvid() !== RVID) {
            $actionName = Episciences_Auth::isAllowedToManagePaper() ? 'list' : self::ACTION_ASSIGNED;
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($this->view->translate(self::MSG_PAPER_DOES_NOT_EXIST));
            $this->_helper->redirector->gotoUrl($this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'action' => $actionName ]));
        }
        $docId = $paper->getDocid();

        $this->redirectWithFlashMessageIfPaperIsRemovedOrDeleted($paper, false);

        $loggedUid = Episciences_Auth::getUid();

        $isCoiEnabled = (boolean)$review->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED);

        $this->redirectWithFlashMessageIfConflictDetected($paper, $review);

        // get contributor details
        $contributor = new Episciences_User();
        $contributor->findWithCAS($paper->getUid());
        $contributorSocialMedia = $contributor->getSocialMedias();
        $this->view->contributorSocialMedia = $contributorSocialMedia;
        $this->view->coAuthorsList = $paper->getCoAuthors();
        // check if paper is obsolete; if so, display a warning
        if ($paper->isObsolete()) {
            $latestDocId = $paper->getLatestVersionId();
            $this->view->latestDocId = $latestDocId;
            $this->view->linkToLatestDocId = $this->adminPaperUrl($latestDocId);
        } else { // redirect if not allowed
            $this->checkPermissions($review, $paper);
        }

        $paper->setXslt($paper->getXml(), 'admin_paper');

        // load all volumes
        $volumes = $review->getVolumes();
        Episciences_VolumesManager::loadSettingsForVolumes($volumes);
        $this->view->volumes = $volumes;

        // get paper/volumes relations (secondary volumes)f
        $paper->loadOtherVolumes();

        $sections = $review->getSections();

        // load sections settings
        Episciences_SectionsManager::loadSettingsForSections($sections);
        $this->view->sections = $sections;


        $doiQueue = Episciences_Paper_DoiQueueManager::findByPaperId($paper->getPaperid());

        $doi_status = $doiQueue->getDoi_status();

        if ($doi_status === Episciences_Paper_DoiQueue::STATUS_NOT_ASSIGNED && $paper->getDoi()) {
            // already has one DOI but not auto assigned
            $doi_status = Episciences_Paper_DoiQueue::STATUS_MANUAL;
        }

        try {
            $journalSetting = Zend_Registry::get('reviewSettingsDoi');
            $this->view->doiAssignMode = $journalSetting->getDoiAssignMode();
        } catch (Exception $exception) {
            $this->view->doiAssignMode = '';
            trigger_error($exception->getMessage(), E_USER_WARNING);
        }

        $this->view->canBeAssignedDOI = $paper->canBeAssignedDOI();
        $this->view->doiQueueStatus = $doi_status;
        $this->view->doiQueueStatusHtml = Episciences_Paper_DoiQueue::getStatusHtmlTemplate($doi_status) . '&nbsp;';


        // get rating invitations
        $invitations = Episciences_Auth::isAllowedToManagePaper() ? $paper->getRatingInvitations() : [];

        #git 323: Lister les relecteurs non invités
        $this->listUninvitedReviewers($paper, $invitations);

        $this->view->invitations = $invitations;

        // load editors
        $paperEditors = $paper->getEditors(true, true);
        $this->view->editors = $paperEditors;


        if (Episciences_Auth::isGuestEditor() || Episciences_Auth::isEditor()) {

            // get editor comments ******************************************************
            $editor_comment_form = Episciences_CommentsManager::getForm('editor_comment_form');
            $this->view->editor_comment_form = $editor_comment_form;

            if (($request->getPost('postComment') !== null) && $editor_comment_form->isValid($request->getPost())) {
                if ($this->save_editor_comment($paper)) {
                    $message = $this->view->translate("Votre commentaire a bien été envoyé.");
                    $this->_helper->FlashMessenger->setNamespace(self::SUCCESS)->addMessage($message);
                } else {
                    $message = $this->view->translate("Une erreur s'est produite lors de l'envoi du formulaire. Veuillez vérifier le formulaire et le soumettre à nouveau.");
                    $this->_helper->FlashMessenger->setNamespace(self::ERROR)->addMessage($message);
                }

                $this->_helper->redirector->gotoUrl($this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER , 'action' => 'view', 'id' => $paper->getDocid()]));
            }
        }

        // Préparation de copie
        $this->view->copyEditors = $paper->getCopyEditors();
        $copyEditingCommentTypes = [
            'types' => [
                Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,
                Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
                Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST,
                Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER,
                Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,
                Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED,
                Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST,
                Episciences_CommentsManager::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION
            ]];

        $allCopyEditingDemands = Episciences_CommentsManager::getList($paper->getDocid(), $copyEditingCommentTypes);

        $this->view->copyEditingDemands = $allCopyEditingDemands;

        // Editor comment
        $settings = ['types' => [Episciences_CommentsManager::TYPE_EDITOR_COMMENT]];
        $editor_comments = Episciences_CommentsManager::getList($paper->getDocid(), $settings);
        $this->view->editor_comments = $editor_comments;

        // get reviewer comments ******************************************************
        $settings = ['types' => [
            Episciences_CommentsManager::TYPE_INFO_REQUEST,
            Episciences_CommentsManager::TYPE_INFO_ANSWER]];
        $reviewer_comments = Episciences_CommentsManager::getList($paper->getDocid(), $settings);
        $this->view->reviewer_comments = $reviewer_comments;

        // Get author comments

        $author_comments = Episciences_CommentsManager::getList(
            $paper->getDocid(),
            [
                'type' => Episciences_CommentsManager::TYPE_AUTHOR_COMMENT
            ]);

        $this->view->author_comments = $author_comments;


        // get revision requests ******************************************************
        // fetch revision requests
        $settings = [
            'types' => [
                Episciences_CommentsManager::TYPE_REVISION_REQUEST,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION,
                Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT
            ]
        ];

        $demands = Episciences_CommentsManager::getRevisionRequests($paper->getDocid(), $settings);

        // fetch previous version revision requests
        // TODO: optimize this (don't need to get the full paper object)
        $previousVersions = $paper->getPreviousVersions();
        if ($previousVersions) {
            $previousComments = [];
            /** @var Episciences_Paper $version */
            foreach ($previousVersions as $version) {
                $vComments = $version->getComments($settings);
                // ne pas afficher le bloc demande de modification si aucune demande de modification n'a été demandés suite au copy editing
                if ($vComments) {
                    $previousComments[$version->getDocid()] = $version->getComments($settings);
                }
            }
            $this->view->previousVersions = $previousVersions;
            $this->view->previousVersionsDemands = $previousComments;
        }

        // check if last revision request has been answered
        $currentDemand = null;
        $revisionDeadline = null;

        if (!empty($demands)) {

            $currentDemand = array_shift($demands);

            if (
                !array_key_exists('replies', $currentDemand) ||
                (
                    isset($currentDemand['replies']) &&
                    (int)$currentDemand['replies'][array_key_first($currentDemand['replies'])]['TYPE'] === Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT)
            ) {
                $revisionDeadline = $currentDemand['DEADLINE'];
            }

        }

        $paper->_revisionDeadline = $revisionDeadline;

        $this->view->demands = $demands;
        $this->view->currentDemand = $currentDemand;

        // load all paper rating reports
        $this->view->grid = $paper->getGrid();
        $paper->loadRatings(null, Episciences_Rating_Report::STATUS_COMPLETED);

        $isRequiredReviewersOk = (int)$review->getSetting('requiredReviewers') <= count($paper->getRatings(null, Episciences_Rating_Report::STATUS_COMPLETED));
        $this->view->isRequiredReviewersOk = $isRequiredReviewersOk;
        $this->view->isAllowedToSeeReportDetails = !$paper->isOwner() && (
                Episciences_Auth::isSecretary() ||
                Episciences_Auth::isEditor() ||
                Episciences_Auth::isGuestEditor() ||
                Episciences_Auth::isCopyEditor()
            );

        // #37430 Demande d'avis des autres rédacteurs, pas uniquement les redacteurs qui sont assignés a l'article.
        // Include both editors and chief editors
        $roles = [
            Episciences_Acl::ROLE_EDITOR,
            Episciences_Acl::ROLE_CHIEF_EDITOR
        ];
        $all_editors = Episciences_UsersManager::getUsersWithRoles($roles);

        // Echapper l'éditeur en cours
        if (array_key_exists($loggedUid, $all_editors)) {
            unset($all_editors[$loggedUid]);
        }

        // Echapper les éditeurs assignés à l'article

        foreach ($paperEditors as $uid => $editor) {
            if (array_key_exists($uid, $all_editors)) {
                unset($all_editors[$uid]);
            }
        }

        // Allow post - acceptance revisions of articles
        $isPostAcceptanceEnabled = (int)$review->getSetting(Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION) === 1;

        $templates = Episciences_PapersManager::getStatusFormsTemplates($paper, $contributor, $all_editors, $review->getSettings());


        if ($isPostAcceptanceEnabled && (Episciences_Auth::isSecretary() || Episciences_Auth::isCopyEditor())) {
            $this->view->acceptedAskAuthorFinalVersionForm = Episciences_PapersManager::getAcceptedAskAuthorFinalVersionForm($templates['acceptedAskAuthorFinalVersion']);
            $this->view->acceptedAskAuthorValidationForm = Episciences_PapersManager::getAcceptedAskAuthorValidationForm($templates['acceptedAskAuthorValidation']);
        }

        // paper status change form
        if (Episciences_Auth::isAllowedToManagePaper()) {
            $this->view->other_editors = $all_editors;
            $this->view->acceptanceForm = Episciences_PapersManager::getAcceptanceForm($templates['accept']);
            $this->view->publicationForm = Episciences_PapersManager::getPublicationForm($templates['publish']);
            $this->view->refusalForm = Episciences_PapersManager::getRefusalForm($templates['refuse'], $docId);
            $this->view->minorRevisionForm = Episciences_PapersManager::getRevisionForm($templates['minorRevision'], 'minor', $review, true, $docId);
            $this->view->majorRevisionForm = Episciences_PapersManager::getRevisionForm($templates['majorRevision'], 'major', $review, true, $docId);
            // waiting for author resources form request
            $this->view->authorSourcesRequestForm = Episciences_PapersManager::getWaitingForAuthorSourcesForm($templates['waitingAuthorSources']);
            // waiting for author formatting
            $this->view->authorFormattingRequestForm = Episciences_PapersManager::getWaitingForAuthorFormatting($templates['waitingAuthorFormatting']);
            $this->view->reviewFormattingDeposedForm = Episciences_PapersManager::getReviewFormattingDeposedForm($templates['reviewFormattingDeposed']);
            $this->view->ceAcceptFinalVersionForm = Episciences_PapersManager::getCeAcceptFinalVersionForm($templates['ceAcceptFinalVersion']);

            if (!empty($all_editors)) {
                $this->view->askOtherEditorsForm = Episciences_PapersManager::getAskOtherEditorsForm($templates['askOtherEditors'], $all_editors, $paper);
            }

        } elseif (Episciences_Auth::isCopyEditor()) { // copy editor role only
            // waiting for author resources form request (review formatting)
            $this->view->authorSourcesRequestForm = Episciences_PapersManager::getWaitingForAuthorSourcesForm($templates['waitingAuthorSources']);
            // Author formatting
            $this->view->authorFormattingRequestForm = Episciences_PapersManager::getWaitingForAuthorFormatting($templates['waitingAuthorFormatting']);
            $this->view->reviewFormattingDeposedForm = Episciences_PapersManager::getReviewFormattingDeposedForm($templates['reviewFormattingDeposed']);
            $this->view->ceAcceptFinalVersionForm = Episciences_PapersManager::getCeAcceptFinalVersionForm($templates['ceAcceptFinalVersion']);


            if ($paper->isApprovedByAuthor()) {
                $this->view->publicationForm = Episciences_PapersManager::getPublicationForm($templates['publish']);

            }
        }

        $suggestionsStatusForm = $this->getSuggestStatusForm($docId);
        $this->view->suggestionsStatusForm = $suggestionsStatusForm;


        $this->view->suggestions = Episciences_EditorsManager::getEditorsSuggestionsByPaper($paper->getDocid());
        // ne plus gérer l'article
        $rejections = Episciences_EditorsManager::getRejectionComments($paper->getDocid());
        $this->view->rejections = $rejections;
        $this->view->isMonitoringRefused = Episciences_EditorsManager::isMonitoringRefused($loggedUid, $paper->getDocid());

        // other versions block
        $versions = [];

        foreach ($paper->getVersionsIds() as $version => $docId) {
            $versions[$version] = Episciences_PapersManager::get($docId, false);
        }

        $this->view->versions = array_reverse($versions, true);

        // history block
        $this->view->logs = $paper->getHistory();

        // js tags
        $this->view->js_review = Zend_Json::encode(['rvid' => RVID, 'code' => RVCODE, 'name' => $review->getName()]);
        $this->view->js_paper = Zend_Json::encode(['id' => $paper->getDocid(),
            'title' => $paper->getAllTitles(),
            'repository' => (int)$paper->getRepoid()]);
        $this->view->js_contributor = Zend_Json::encode($contributor->toArray());
        $this->view->js_sender = Zend_Json::encode(['fullname' => Episciences_Auth::getFullName(), 'screen_name' => Episciences_Auth::getScreenName(), 'email' => Episciences_Auth::getEmail()]);
        $this->view->available_languages = Zend_Json::encode(Episciences_Tools::getLanguages());

        $this->view->paper = $paper;
        $this->view->paperUrl = $this->publicPaperUrl($paper->getDocid());
        $this->view->metadata = $paper->getDatasetsFromEnrichment();
        $this->view->siteLocale = Episciences_Tools::getLocale();
        $this->view->defaultLocale = Episciences_Review::getDefaultLanguage();

        $affiForm = Episciences_PapersManager::getAffiliationsForm(['paperid' => $paper->getPaperid()]);


        $this->view->affiliationsForm = $affiForm;

        $getterCiting = Episciences_Paper_CitationsManager::formatCitationsForViewPaper($paper->getDocid());
        $this->view->citations = $getterCiting['template'];
        $this->view->counterCitations = $getterCiting['counterCitations'];

        if ($isCoiEnabled) {
            //conflict management section
            $this->view->paperConflicts = $paper->getConflicts(false, true);
        }

        // paper password bloc

        $displayPaperPasswordBloc = (
            in_array(Episciences_Repositories::ARXIV_REPO_ID, $review->getSetting($review::SETTING_REPOSITORIES)) &&
            $review->getSetting($review::SETTING_ARXIV_PAPER_PASSWORD) &&
            $paper->getRepoid() === (int)Episciences_Repositories::ARXIV_REPO_ID &&
            !in_array($paper->getStatus(), $paper::$_noEditableStatus, true) &&
            (
                Episciences_Auth::isSecretary() ||
                $paper->getEditor($loggedUid) ||
                $paper->getCopyEditor($loggedUid)
            )
        );

        if ($displayPaperPasswordBloc) {
            $plainPaperPassword = $this->getPlainPaperPassword($paper);
            $this->view->paperPassword = $plainPaperPassword;
        }

        $this->view->displayPaperPasswordBloc = $displayPaperPasswordBloc;

        $isEditableVersion = $paper->isEditableVersion() &&
            (Episciences_Auth::isSecretary() || $paper->getEditor($loggedUid) || $paper->getCopyEditor($loggedUid));
        $this->view->isEditableVersion = $isEditableVersion;

        /**
         * Bibliographical References
         */
        $enabledBib = false;
        if (EPISCIENCES_BIBLIOREF['ENABLE'] &&
            ($paper->getStatus() === Episciences_Paper::STATUS_CE_READY_TO_PUBLISH ||
                $paper->getStatus() === Episciences_Paper::STATUS_PUBLISHED)) {
            $this->view->urlcallapibib = APPLICATION_URL . '/' . $docId . '/pdf';
            $this->view->apiEpiBibCitation = EPISCIENCES_BIBLIOREF['URL'];
            $enabledBib = true;
        }
        $this->view->enabledBib = $enabledBib;
    }

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Review $review
     * @return void
     */
    private function redirectWithFlashMessageIfConflictDetected(Episciences_Paper $paper, Episciences_Review $review): void
    {
        $docId = $paper->getDocid();
        $loggedUid = Episciences_Auth::getUid();

        $checkConflictResponse = $paper->checkConflictResponse($loggedUid);

        $isOwnSubmission = $paper->isOwner();
        $isConflictDetected = self::isConflictDetected($paper, $review);

        // check if user has required permissions
        if ($isOwnSubmission || $isConflictDetected) {

            $suUser = Episciences_Auth::getOriginalIdentity();

            $message = '';

            if ($isOwnSubmission) {

                if ($suUser && ($suUser->getUid() !== $loggedUid)) {

                    $message .= $suUser->getScreenName();
                    $message .= ', ';
                    $message .= '<br>';
                    $message .= $this->view->translate("Vous êtes connecté en tant que : ");
                    $message .= Episciences_Auth::getScreenName();
                    $message .= '<br>';
                }


                $message .= $this->view->translate('Vous avez été redirigé, car vous ne pouvez pas gérer un article que vous avez vous-même déposé');
                $url = $this->url(['controller' => 'paper', 'action' => 'view', 'id' => $docId]);

            } else {

                $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

                if (
                    isset($session->checkConflictResponseForSu) &&
                    in_array($session->checkConflictResponseForSu, [Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'], Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']], true)
                ) {

                    $message .= $suUser->getScreenName();
                    $message .= ', ';
                    $message .= '<br>';

                    if ($session->checkConflictResponseForSu === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {

                        Episciences_Auth::updateIdentity($suUser);

                        $message .= $this->view->translate("Vous êtes maintenant connecté à votre compte :");
                        $message .= '<br>';
                        $message .= $this->view->translate("Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission");

                    } else {
                        $message .= $this->view->translate("Vous avez vous-même signalé un conflit d'intérêts avec cette soumission.");
                        $message .= '<br>';
                        $message .= $this->view->translate("Vous êtes connecté en tant que : ");
                        $message .= Episciences_Auth::getScreenName();
                        $message .= '<br>';


                        if ($checkConflictResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {
                            $message .= $this->view->translate("Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission");
                        }

                    }

                } elseif ($checkConflictResponse === Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']) {
                    $message = $this->view->translate("Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission");

                } else {
                    $message = $this->view->translate("Vous avez été redirigé, car vous avez déclaré un conflit d'intérêts avec cette soumission.");
                }

                $url = $this->url(['controller' => 'coi', 'action' => 'report', 'id' => $docId]);

            }


            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector->gotoUrl($url);

        }

    }

    /**
     * check user permissions according to controller action
     * if access is denied, redirect to another page with an error message
     * @param Episciences_Review $review
     * @param Episciences_Paper $paper
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    protected function checkPermissions(Episciences_Review $review, Episciences_Paper $paper): bool
    {
        // chief editors, administrator and secretary (git #235) can do whatever they want
        if (Episciences_Auth::isSecretary()) {
            return true;
        } // check if editors have sufficient permission for accessing paper or changing its status

        $redirection = $this->buildRedirectionMessage($review, $paper);
        $params = array_key_exists('params', $redirection) ? $redirection['params'] : [];

        if (!empty($redirection) && array_key_exists('message', $redirection)) {
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($redirection['message']);
            $this->_helper->redirector->gotoUrl($this->url(array_merge(['action' => self::ACTION_ASSIGNED, 'controller' =>  self::ADMINISTRATE_PAPER_CONTROLLER], $params)));
        }

        return empty($redirection);
    }

    /**
     * @param Episciences_Review $review
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    private function buildRedirectionMessage(Episciences_Review $review, Episciences_Paper $paper): array
    {
        $redirection = [];

        $isNextTest = true;

        $message = "Vous n'avez pas les droits suffisants pour accéder à cet article";


        if ($paper->getEditor(Episciences_Auth::getUid()) || $paper->getCopyEditor(Episciences_Auth::getUid())) { // assigned
            $isNextTest = false;
        }

        // if editors encapsulation is on, editors who are not assigned to this paper do not have any permission for it: redirect them
        if ($isNextTest && Episciences_Auth::isEditor() && $review->getSetting('encapsulateEditors')) {
            $redirection['message'] = $message;
            $isNextTest = false;
        }

        // if copy editors encapsulation is on, copy editors who are not assigned to this paper do not have any permission for it: redirect them
        if ($isNextTest && Episciences_Auth::isCopyEditor() && $review->getSetting('encapsulateCopyEditors')) {
            $redirection['message'] = $message;
            $redirection['params'] = ['ce' => 1];
            $isNextTest = false;
        }


        if ($isNextTest && Episciences_Auth::isGuestEditor() && !(Episciences_Auth::isEditor() || Episciences_Auth::isCopyEditor())) {
            $redirection['message'] = $message;
            return $redirection;
        }

        // check if journal settings allow editors to take decisions about this paper
        switch ($this->getRequest()->getActionName()) {

            case 'accept':
                if (!$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS)) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour accepter cet article";
                }
                break;

            case 'publish':

                if (
                    !$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS) &&
                    !($paper->isApprovedByAuthor() && $paper->getCopyEditor(Episciences_Auth::getUid()))
                ) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour publier cet article";
                }
                break;

            case 'refuse':
                if (!$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_REJECT_PAPERS)) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour refuser cet article";
                }
                break;

            case 'revision':
                if (!$review->getSetting(Episciences_Review::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS)) {
                    $redirection['message'] = "Vous n'avez pas les droits suffisants pour demander des modifications sur cet article";
                }
                break;
            default: // not action
                break;
        }


        return $redirection;

    }

    /**
     * @param Episciences_Paper $paper
     * @param array $invitations
     * @throws Zend_Db_Statement_Exception
     */
    private function listUninvitedReviewers(Episciences_Paper $paper, array &$invitations): void
    {
        $docId = $paper->getDocid();
        // Invitations déjà acceptées
        $activatedInvitations = array_key_exists(Episciences_User_Assignment::STATUS_ACTIVE, $invitations) ? $invitations[Episciences_User_Assignment::STATUS_ACTIVE] : [];

        $paper->loadRatings();
        $ratings = $paper->getRatings();

        /**
         * @var  $uid
         * @var  $rating Episciences_Rating_Report
         */
        foreach ($ratings as $uid => $rating) {
            $isFound = false;

            foreach ($activatedInvitations as $aInvitation) {
                if ((int)$aInvitation['UID'] === $uid) {
                    $isFound = true;
                    break;
                }
            }

            if ($isFound) {
                continue;
            }

            if (!array_key_exists($uid, $paper->getReviewers()) || !$reviewer = $paper->getReviewers()[$uid]) { // fix Notice: Undefined offset: UID
                continue;
            }

            $reviewer->loadAssignments();

            if (!array_key_exists($docId, $reviewer->getAssignments())) {
                continue;
            }

            /** @var  $assignment Episciences_User_Assignment */
            $assignment = $reviewer->getAssignment($docId);

            $invitations [Episciences_Reviewer::STATUS_UNINVITED][] = [
                'ASSIGNMENT_ID' => $assignment->getId(),
                'ASSIGNMENT_STATUS' => $assignment->getStatus(),
                'UID' => $uid,
                'TMP_USER' => 0,
                'DOCID' => $docId,
                Episciences_Reviewer::STATUS_UNINVITED => true,
                'reviewer' =>
                    [
                        'alias' => $reviewer->getAlias($paper->getDocid()),
                        'fullname' => $reviewer->getFullName(),
                        'screenname' => $reviewer->getScreenName(),
                        'email' => $reviewer->getEmail(),
                        'rating' => [
                            'status' => $rating->getStatus(), 'last_update' => $rating->getUpdate_date()
                        ]
                    ]
            ];
        }

    }

    /**
     * @param Episciences_Paper $paper
     * @return bool
     */
    private function save_editor_comment(Episciences_Paper $paper): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();
        $docId = $paper->getDocid();

        // save comment to database ******************************************
        $oComment = new Episciences_Comment();
        $oComment->setFilePath(Episciences_PapersManager::buildDocumentPath($docId) . '/comments/');
        $oComment->setType(Episciences_CommentsManager::TYPE_EDITOR_COMMENT);
        $oComment->setDocid($docId);
        $oComment->setMessage($post['comment']);
        if (!$oComment->save()) {
            return false;
        }

        //Notifications
        return $this->newCommentNotifyManager($paper, $oComment);
    }

    /**
     * @param int $docId
     * @return Ccsd_Form|null
     * @throws Zend_Form_Exception
     */
    private function getSuggestStatusForm(int $docId): ?Ccsd_Form
    {

        if (Episciences_Auth::isRoot() || Episciences_Auth::isSecretary()) {
            return null;
        }

        return Episciences_PapersManager::getSuggestStatusForm($docId);
    }

    /**
     * reviewer invitation form
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function invitereviewerAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost()) {

            // get paper parameter
            $docId = ($request->getPost('docid')) ?: $request->getParam('docid'); /* Erreur: le changement de la langue au cours de l'invitation d'un relecteur déclanche une erreur (docId = null)*/

            // sauvegarder d'où vient l'appel de l'invitation d'un relecteur
            $referer = ($request->getPost('referer')) ?: $this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'list']);
            $this->view->referer = $referer;
            $this->view->isExistingCriterionGrid = Episciences_Rating_Manager::isExistingCriterion();

            // load paper detail
            /** @var Episciences_Paper $oPaper */
            $oPaper = (!$docId) ? null : Episciences_PapersManager::get($docId, false);

            if (!$oPaper) {
                $this->getResponse()?->setHttpResponseCode(404);
                $this->renderScript('index/notfound.phtml');
                return;
            }

            $vid = $request->getPost('vid');
            $special_issue = $request->getPost('special_issue');

            $oPaper->loadSettings();
            $this->view->js_paper = Zend_Json::encode(array_merge($oPaper->getAllMetadata(), ['paperId' => $oPaper->getPaperid()])); // due to the addition of a new tag %%PERMANENT_ARTICLE_ID%%
            $this->view->paper = $oPaper;
            // Lors de la reinvitation d'un relecteur suite à l'expiration d'une invitation
            $this->view->js_uid = $request->getPost('reinvite_uid');

            // get contributor detail
            $contributor = new Episciences_User;
            $contributor->findWithCAS($oPaper->getUid());
            $this->view->js_contributor = ($contributor instanceof \Episciences_User) ? Zend_Json::encode($contributor->toArray()) : null;

            // load journal detail
            $oReview = Episciences_ReviewsManager::find(RVID);
            $oReview->loadSettings();
            // load reviewers array
            $reviewers = [];
            $allJsReviewers = [];

            $oReviewers = Episciences_Review::getReviewers();
            $allReviewers = Episciences_PapersManager::getReviewers($docId, ['pending'], true);


            if ($oReviewers) {
                // On ne peut pas inviter un relecteur à relire son article.
                if (array_key_exists($oPaper->getUid(), $oReviewers)) {
                    unset($oReviewers[$oPaper->getUid()]);
                }

                if (array_key_exists(Episciences_Auth::getUid(), $oReviewers)) {
                    unset($oReviewers[Episciences_Auth::getUid()]);
                }

                //[COI] When inviting an reviewer; do not propose user that have confirmed (answer = yes) a COI in the user list

                foreach ($this->usersWithReportedCoiProcessing($oPaper) as $uid => $user) {
                    unset($oReviewers[$uid]);
                }

                // **** filter reviewers list

                // TODO: get this code out of the controller: move this to a model
                // TODO: write a reviewers collection class ?

                // get active or pending invitations
                $invitations = $oPaper->getInvitations([
                    Episciences_User_Assignment::STATUS_ACTIVE,
                    Episciences_User_Assignment::STATUS_PENDING], true);
                $ignore_list = [];
                // remove reviewers who already have an accepted invitation for this paper
                foreach ($invitations[Episciences_User_Assignment::STATUS_ACTIVE] as $invitation) {
                    $ignore_list[$invitation['UID']] = $invitation['UID'];
                    if (array_key_exists($invitation['UID'], $oReviewers)) {
                        unset($oReviewers[$invitation['UID']]);
                    }
                }

                // remove reviewers who already have a pending invitation for this paper
                foreach ($invitations[Episciences_User_Assignment::STATUS_PENDING] as $invitation) {
                    $ignore_list[$invitation['UID']] = $invitation['UID'];
                    if (array_key_exists($invitation['UID'], $oReviewers)) {
                        unset($oReviewers[$invitation['UID']]);
                    }
                }

                // prepare js array
                /** @var Episciences_Reviewer $reviewer */
                foreach ($oReviewers as $uid => $reviewer) {
                    $reviewers[$uid]['locale'] = $reviewer->getLangueid();
                    $reviewers[$uid]['screen_name'] = $reviewer->getScreenName();
                    $reviewers[$uid]['full_name'] = $reviewer->getFullname();
                    $reviewers[$uid]['user_name'] = $reviewer->getUsername();
                    $reviewers[$uid]['email'] = $reviewer->getEMail();
                }

                $this->view->js_reviewers = Zend_Json::encode($reviewers);
                $this->view->js_invitations = Zend_Json::encode(array_keys($invitations));
                $this->view->js_ignore_list = Zend_Json::encode(array_keys($ignore_list));

                $this->view->reviewers = $oReviewers;
            }

            // Tous les relecteurs (utilisateurs temp. compris)
            foreach ($allReviewers as $uid => $reviewer) {
                // pour les comptes temporaires
                if (!is_numeric($uid)) {
                    $uid = substr($uid, 4); // UID === tmp_id
                    $allJsReviewers[$uid]['type'] = 3; // Utilisateur temporaire
                } elseif (!array_key_exists((int)$uid, $reviewers)) {
                    $allJsReviewers[$uid]['type'] = 2; // Utilisateur connu par le CAS
                } else {
                    $allJsReviewers[$uid]['type'] = 1; // Relecteur connu par Episciences.
                }

                $allJsReviewers[$uid]['locale'] = $reviewer->getLangueid();
                $allJsReviewers[$uid]['screen_name'] = $reviewer->getScreenName();
                $allJsReviewers[$uid]['full_name'] = $reviewer->getFullname();
                $allJsReviewers[$uid]['user_name'] = $reviewer->getUsername();
                $allJsReviewers[$uid]['email'] = $reviewer->getEMail();
            }

            $this->view->allJsReviewers = Zend_Json::encode($allJsReviewers);
            // load templates
            for ($i = 1; $i <= 3; $i++) {
                $oTemplate = new Episciences_Mail_Template();
                switch ($i) {
                    case 1:
                        $template_key = Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER;
                        break;
                    case 2:
                        $template_key = Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER;
                        break;
                    case 3:
                        $template_key = Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER;
                        break;
                    default:
                        $template_key = '';
                }

                $oTemplate->findByKey($template_key);
                $oTemplate->loadTranslations();

                $template = $oTemplate->toArray();
                $template['subject'] = $oTemplate->loadSubject(Episciences_Tools::getLanguages());
                unset($template['id'], $template['parentId'], $template['rvid'], $template['key'], $template['type']);

                $oTemplates[$i] = $oTemplate;
                $templates[$i] = $template;
            }

            // review js array init
            $review['id'] = $oReview->getRvid();
            $review['code'] = $oReview->getCode();
            $review['name'] = $oReview->getName();
            $review['invitation_deadline'] = $oReview->getSetting('invitation_deadline');
            $review['rating_deadline'] = Episciences_Tools::addDateInterval(date('Y-m-d'), $oReview->getSetting('rating_deadline'));

            $this->view->js_review = Zend_Json::encode($review);
            $this->view->js_templates = Zend_Json::encode($templates);
            $this->view->templates = $oTemplates;
            $this->view->available_languages = Zend_Json::encode(Episciences_Tools::getLanguages());
            $this->view->siteLocale = Episciences_Tools::getLocale();
            $this->view->defaultLocale = Episciences_Review::DEFAULT_LANG;
            $this->view->suggestedReviewers = Episciences_ReviewersManager::getSuggestedReviewers($docId);
            $this->view->unwantedReviewers = Episciences_ReviewersManager::getUnwantedReviewers($docId);

            // reviewer invitation form
            $page = ($request->getPost('page')) ?: null;
            $params = ['vid' => $vid, 'special_issue' => $special_issue];
            if ($oReview->getSetting('rating_deadline_min')) {
                $params['rating_deadline_min'] = Episciences_Tools::addDateInterval(date('Y-m-d'), $oReview->getSetting('rating_deadline_min'));
            }
            if ($oReview->getSetting('rating_deadline_max')) {
                $params['rating_deadline_max'] = Episciences_Tools::addDateInterval(date('Y-m-d'), $oReview->getSetting('rating_deadline_max'));
            }
            $invitationForm = Episciences_PapersManager::getReviewerInvitationForm($docId, $page, $referer, $params);
            $invitationForm->setDefault('deadline', $review['rating_deadline']);
            $this->view->invitation_form = $invitationForm;
            $this->view->user_form = Episciences_PapersManager::getTmpReviewerForm();
            $this->view->js_allAuthors = json_encode($oPaper->formatAuthorsMetadata());

        } else {
            Episciences_Tools::header('HTTP/1.1 405 Method Not Allowed');
            $this->renderScript('index/notfound.phtml');
        }
    }

    /**
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function updatedeadlineAction(): bool
    {
        $this->_helper->layout->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        //get assignment id
        $aid = $request->getParam('aid');
        if (!$aid || !is_numeric($aid)) {
            return false;
        }

        //get assignment object
        $oAssignment = Episciences_User_AssignmentsManager::findById($aid);
        if (!$oAssignment) {
            return false;
        }

        //get paper object
        $oPaper = Episciences_PapersManager::get($oAssignment->getItemid());
        if (!$oPaper) {
            return false;
        }

        //get user object
        if ($oAssignment->isTmp_user()) {
            $oReviewer = Episciences_TmpUsersManager::findById($oAssignment->getUid());
            $oReviewer->generateScreen_name();
            if (!$oReviewer->getScreenName()) {
                return false;
            }
        } else {
            $oReviewer = new Episciences_Reviewer;
            if (!$oReviewer->findWithCAS($oAssignment->getUid())) {
                return false;
            }
        }
        $locale = $oReviewer->getLangueid(true);
        $reviewer = [
            'locale' => $locale,
            'full_name' => $oReviewer->getFullName(),
            'user_name' => ($oAssignment->isTmp_user()) ? $oReviewer->getUsername() : null,
            'email' => $oReviewer->getEmail()
        ];

        //get review object
        $oReview = Episciences_ReviewsManager::find(RVID);
        $review = ['rvid' => RVID, 'code' => RVCODE, 'name' => $oReview->getName()];

        //init template
        $template = new Episciences_Mail_Template;
        $template->findByKey(Episciences_Mail_TemplatesManager::TYPE_PAPER_UPDATED_RATING_DEADLINE);
        $template->loadTranslations();
        $template->setLocale($locale);

        //prepare tags
        $tags = [
            Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $oReviewer->getScreenName(),
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => (!$oAssignment->isTmp_user()) ? $oReviewer->getUsername() : '',
            Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $oReviewer->getFullName(),
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $oPaper->getDocid(),
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $oPaper->getPaperid(),
            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $oPaper->getTitle($locale, true),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $oPaper->formatAuthorsMetadata(),
            Episciences_Mail_Tags::TAG_SENDER_FULL_NAME => Episciences_Auth::getFullName(),
            Episciences_Mail_Tags::TAG_UPDATED_DEADLINE => $this->view->Date($oAssignment->getDeadline()),
            Episciences_Mail_Tags::TAG_REVIEW_CODE => RVCODE,
        ];
        $subject = str_replace(array_keys($tags), array_values($tags), $template->getSubject());
        $body = str_replace(array_keys($tags), array_values($tags), $template->getBody());
        if ($oAssignment->isTmp_user()) {
            $body = Episciences_Tools::cleanBody($body);
        }

        //init invitation form
        $params = [
            'rating_deadline_min' => Episciences_Tools::addDateInterval(date('Y-m-d'), date('Y-m-d')),
            'rating_deadline_max' => Episciences_Tools::addDateInterval(date('Y-m-d'), $oReview->getSetting('rating_deadline_max'))];
        $form = Episciences_PapersManager::getDeadlineForm($aid, $params);
        $defaults = [
            'recipient' => $oReviewer->getFullName() . ' <' . $oReviewer->getEmail() . '>',
            'deadline' => date('Y-m-d', strtotime($oAssignment->getDeadline())),
            'subject' => $subject,
            'body' => nl2br($body)
        ];
        $form->setDefaults($defaults);

        $this->view->form = $form;
        $this->view->js_reviewer = Zend_Json::encode($reviewer);
        $this->view->js_paper = Zend_Json::encode(['id' => $oPaper->getDocid(), 'title' => $oPaper->getTitle()]);
        $this->view->js_review = Zend_Json::encode($review);
        $this->view->js_editor = Zend_Json::encode(['full_name' => Episciences_Auth::getFullName(), 'email' => Episciences_Auth::getEmail()]);
        $this->view->available_languages = Zend_Json::encode(Episciences_Tools::getLanguages());
        return true;
    }

    /**
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function savenewdeadlineAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $translator = Zend_Registry::get('Zend_Translate');
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost(); //subject, body, deadline

        $result = ['status' => 0];

        //assignment id
        $id = $request->getParam('aid');
        if (!$id || !is_numeric($id)) {

            $result['message'] = $translator->translate("Id invalide");
            echo Zend_Json::encode($result);
            return;
        }

        //assignment
        $assignment = Episciences_User_AssignmentsManager::findById($id);
        if (!$assignment) {
            $result['message'] = $translator->translate("Impossible de retrouver l'assignation");
            echo Zend_Json::encode($result);
            return;
        }

        $paper = Episciences_PapersManager::get($assignment->getItemid());

        //user
        if ($assignment->isTmp_user()) {
            $reviewer = Episciences_TmpUsersManager::findById($assignment->getUid());
            if ($reviewer) {
                $reviewer->generateScreen_name();
            }
        } else {
            $reviewer = new Episciences_Reviewer;
            $reviewer->findWithCAS($assignment->getUid());
        }
        if (!$reviewer) {
            $result['message'] = $translator->translate("Impossible de trouver le relecteur");
            echo Zend_Json::encode($result);
            return;
        }

        //deadline validations
        if (!array_key_exists('deadline', $post)) {
            $result['message'] = $translator->translate("La date limite de rendu de relecture est obligatoire");
            echo Zend_Json::encode($result);
            return;
        }
        if (!Episciences_Tools::isValidSQLDate($post['deadline'])) {
            $result['message'] = $translator->translate("La date limite de rendu de relecture est invalide");
            echo Zend_Json::encode($result);
            return;
        }
        if (strtotime($post['deadline']) <= strtotime($assignment->getDeadline())) {
            $result['message'] = $translator->translate("La nouvelle date limite de rendu de relecture doit être supérieure à : ");
            $result['message'] .= $this->view->Date($assignment->getDeadline(), Episciences_Tools::getLocale());
            echo Zend_Json::encode($result);
            return;
        }

        $oldDeadline = $assignment->getDeadline();

        //assignment update
        $assignment->setDeadline($post['deadline']);
        $assignment->save();

        $paper->log(
            Episciences_Paper_Logger::CODE_NEW_REVIEWING_DEADLINE,
            Episciences_Auth::getUid(),
            ['oldDeadline' => $oldDeadline,
                'newDeadline' => $assignment->getDeadline(),
                'screenName' => Episciences_Auth::getScreenName()
            ]
        );

        //mail to the reviewer
        $this->sendMailFromModal($reviewer, $paper, $post['subject'], $post['body'], $post);

        //ajax response
        $result = [
            'status' => 1,
            'id' => $id,
            'deadline' => $this->view->Date($post['deadline'], Episciences_Tools::getLocale()),
            'docId' => $paper->getDocid()
        ];
        echo Zend_Json::encode($result);
    }

    /**
     * save reviewer invitation sent to a user
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws Exception
     */
    public function savereviewerinvitationAction(): bool
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();
        $docId = $request->getParam('docid');
        $special_issue = $request->getParam('special_issue');
        $vid = $request->getParam('vid');
        $referer = $request->getPost('referer');
        $session = new Zend_Session_Namespace('Zend_Form_Element_Hash_unique_no_csrf_foo');


        if ((!isset($post['no_csrf_foo'], $session->hash)) || ($post['no_csrf_foo'] !== $session->hash)) {
            $session->hash = null;
            $message = $translator->translate('Pour des raisons de sécurité le formulaire a expiré. Merci de soumettre à nouveau  le formulaire.');
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($referer);
            return false;
        }
        $session->hash = null;

        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper) {
            return false;
        }

        $errors = [];

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        $reviewer = $request->getPost('reviewer');
        try {
            $reviewer = json_decode($reviewer, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            return $false;
        }

        if (!is_array($reviewer)) {
            $message = $translator->translate('Pour des raisons de sécurité le formulaire a expiré. Merci de soumettre à nouveau  le formulaire.');
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($referer);
            return false;
        }

        if (!isset($reviewer['firstname'])) {
            $reviewer['firstname'] = '';
        }
        // save temporary user in USER_TMP table
        if (!$reviewer['id'] && $reviewer['invitation_type'] === 3) {
            $tmp_user = new Episciences_User_Tmp([
                'email' => $reviewer['email'],
                'firstname' => $reviewer['firstname'],
                'lastname' => $reviewer['lastname'],
                'lang' => $reviewer['locale']]);
            $tmp_user->save();
            $uid = $tmp_user->getId();
        } else {
            $uid = $reviewer['id'];
        }

        // save assignment to db
        $params = [
            'rvid' => RVID,
            'itemid' => $docId,
            'tmp_user' => ($reviewer['invitation_type'] === 3) ? 1 : 0,
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'status' => Episciences_User_Assignment::STATUS_PENDING,
            'deadline' => $request->getPost('deadline')
        ];
        $assignments = Episciences_UsersManager::assign($uid, $params);
        /** @var Episciences_User_Assignment $oAssignment */
        $oAssignment = array_shift($assignments);


        // save invitation to db
        $invitation = new Episciences_User_Invitation;
        $invitation->setAid($oAssignment->getId());
        $invitation->setSender_uid(Episciences_Auth::getUid());
        $invitation->save();

        // save invitation id
        $oAssignment->setInvitation_id($invitation->getId());
        $oAssignment->save();

        // save reviewer in a pool
        // if this is not a special volume, or reviewers are not encapsulated, save reviewer to global pool (journal)
        if (!$special_issue || !$review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_REVIEWERS)) {
            Episciences_ReviewersManager::addReviewerToPool($uid);
        }
        // then, if paper is attached to a volume, add the volume
        if ($vid) {
            Episciences_ReviewersManager::addReviewerToPool($uid, $vid);
        }

        // invitation answer url
        $url_params = [
            'controller' => 'reviewer',
            'action' => 'invitation',
            'id' => $invitation->getId()];
        if (array_key_exists('locale', $reviewer)) {
            $url_params['lang'] = $reviewer['locale'];
        }
        if ($reviewer['invitation_type'] === 3) {
            $url_params['tmp'] = md5($reviewer['email']);
        }

        $invitationUrl = self::buildBaseUrl() . $this->view->url($url_params);

        // la page de l'article sur l'archive ouverte
        $paperRepoUrl = $paper->getDocUrl();

        // get contributor details
        $contributor = new Episciences_User();
        $contributor->findWithCAS($paper->getUid());

        // reviewer invitation e-mail
        $mail = new Episciences_Mail('UTF-8');
        $mail->setDocid($docId);
        $mail->setSubject($post['subject']);
        $mail->setRawBody(Ccsd_Tools::clear_nl($post['body']));
        $mail->addTag(Episciences_Mail_Tags::TAG_INVITATION_URL, $invitationUrl);
        $mail->addTag(Episciences_Mail_Tags::TAG_PAPER_URL, self::buildPublicPaperUrl($paper->getDocid()));
        $mail->addTag(Episciences_Mail_Tags::TAG_PAPER_REPO_URL, $paperRepoUrl); // La page de l'article sur Episciences
        $mail->addTag(Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME, $contributor->getFullName());
        $mail->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN, sprintf('%s://%s%s',SERVER_PROTOCOL,$this->getRequest()->getHttpHost(), $this->url(['controller' => 'user', 'action' => 'lostlogin'])));
        $mail->addTag(Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN, sprintf('%s://%s%s',SERVER_PROTOCOL,$this->getRequest()->getHttpHost(), $this->url(['controller' => 'user', 'action' => 'lostlogin'])));
        $mail->addTo($reviewer['email'], $reviewer['full_name']);

        // Other recipients
        $cc = (!empty($post['cc'])) ? explode(';', $post['cc']) : [];
        $bcc = (!empty($post['bcc'])) ? explode(';', $post['bcc']) : [];

        $this->addOtherRecipients($mail, $cc, $bcc);

        if (isset($post[Episciences_Mail_Send::ATTACHMENTS])) {
            $path = Episciences_Tools::getAttachmentsPath($paper->getDocid());
            foreach ($post[Episciences_Mail_Send::ATTACHMENTS] as $attachment) {
                $filepath = $path . $attachment;
                if (file_exists($filepath)) {
                    $mail->addAttachedFile($filepath);
                }
            }
        }
        $mail->writeMail();

        // invitation log
        $log = $paper->log(
            Episciences_Paper_Logger::CODE_REVIEWER_INVITATION,
            Episciences_Auth::getUid(),
            [
                'aid' => $oAssignment->getId(),
                'invitation_id' => $invitation->getId(),
                'tmp_user' => $oAssignment->isTmp_user(),
                'uid' => $uid,
                'pool' => ($vid) ?: null,
                'user' => ['fullname' => $reviewer['full_name']]]);
        if (!$log) {
            $errors[] = "Le log de l'invitation de relecteur n'a pas pu être enregistré";
        }

        // mail log
        $log = $paper->log(
            Episciences_Paper_Logger::CODE_MAIL_SENT,
            Episciences_Auth::getUid(),
            ['id' => $mail->getId(), 'mail' => $mail->toArray()]);
        if (!$log) {
            $errors[] = "Le log de l'e-mail n'a pas pu être enregistré";
        }

        if (empty($errors)) {
            $message = $translator->translate('Votre invitation a bien été envoyée.');
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
        } else {
            // premier message d'erreur
            $message = $translator->translate('Erreur') . ' : ' . $translator->translate($errors[0]);
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        }

        if (!empty($referer)) {
            $this->_helper->redirector->gotoUrl($referer);
        }
        return true;
    }

    /**
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function reviewerslistAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');

        if (!$docId) {
            return false;
        }

        $papersManager = new Episciences_PapersManager;
        $paper = $papersManager::get($docId);
        $paper->getRatings();
        if (Episciences_Auth::isSecretary() || Episciences_Auth::isRoot()) {
            $paper->getReviewers();
        }
        $paper = $paper->toArray();

        echo $this->view->partial('partials/paper_reviewers.phtml', ['article' => $paper]);
        return true;
    }

    /**
     * Accept paper
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function acceptAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('id');

        $journal = Episciences_ReviewsManager::find(RVID);
        $journal->loadSettings();
        $paper = Episciences_PapersManager::get($docId);

        $this->checkPermissions($journal, $paper);


        if ($request->isPost()) {

            $csrfName = 'csrf_accept_' . (int)$docId;
            $csrfSession = new Zend_Session_Namespace('Zend_Form_Element_Hash_unique_' . $csrfName);
            $post = $request->getPost();
            if (!isset($post[$csrfName], $csrfSession->hash) || $post[$csrfName] !== $csrfSession->hash) {
                $csrfSession->hash = null;
                $this->_helper->redirector->gotoUrl($this->_helper->url('view', self::ADMINISTRATE_PAPER_CONTROLLER, null, ['id' => $docId]));
                return;
            }
            $csrfSession->hash = null;

            $doneByUid = Episciences_Auth::getUid();

            $data = $request->getPost();

            // get contributor detail
            $contributor = new Episciences_User;
            $contributor->findWithCAS($paper->getUid());

            // define new status
            if ($paper->getRepoid()) {
                // repository version
                $status = Episciences_Paper::STATUS_ACCEPTED;
            } else { // tmp version
                $status = Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED;
                // save comment
                $subject = $data['acceptancesubject'];
                $message = $data['acceptancemessage'];

                $deadline = $data['minor-revisiondeadline'] ?? null;

                // prepare comment options
                $options = [];

                if ($deadline) {
                    $options['deadline'] = $deadline;
                }

                $options['isAlreadyAccepted'] = true;

                $comment = new Episciences_Comment([
                    'docid' => $docId,
                    'uid' => $doneByUid,
                    'message' => $message,
                    'type' => Episciences_CommentsManager::TYPE_REVISION_REQUEST,
                    'deadline' => $deadline,
                    'options' => $options
                ]);

                $comment->save();

                // log minor revision request
                $paper->log(
                    Episciences_Paper_Logger::CODE_MINOR_REVISION_REQUEST,
                    $doneByUid,
                    [
                        'id' => $comment->getPcid(),
                        'deadline' => $deadline,
                        'subject' => $subject,
                        'message' => $message,
                        'isAlreadyAccepted' => true
                    ]);
            }

            // update paper status
            $paper->setStatus($status);
            if ($paper->save()) {

                // log new status
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, $doneByUid, ['status' => $paper->getStatus()]);

                // send mail to contributor
                $this->sendMailFromModal($contributor, $paper, $data['acceptancesubject'], $data['acceptancemessage'], $data);

                // Une fois la version acceptée, les relecteurs devraient être notifiés (leurs éviter de poursuivre un travail inutile).
                $this->paperStatusChangedNotifyReviewer($paper, Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_ACCEPTED_STOP_PENDING_REVIEWING);

                //delete unanswered rating invitations

                $this->deleteUnansweredInvitations($paper);

                //Notification de rédcateurs + autres, si le bon paramétrage a été choisi

                if (!$paper->isTmp()) {
                    $managersNotificationTemplate = Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_EDITORS_COPY;

                    $additionalTags = [
                        Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_NAME => RVCODE . '_' . CE_RESOURCES_NAME,
                        Episciences_Mail_Tags::TAG_ALL_REVIEW_RESOURCES_LINK => sprintf('%s://%s%s', SERVER_PROTOCOL, $this->getRequest()->getHttpHost(), $this->url(['controller' => 'website', 'action' => 'public']))
                    ];

                    if ($journal->getDoiSettings()->getDoiAssignMode() === Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_AUTO) {
                        Episciences_Paper::createPaperDoi(RVID, $paper);
                    }


                } else {
                    $managersNotificationTemplate = Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_TMP_VERSION_MANAGERS_COPY;

                    $additionalTags = [
                        Episciences_Mail_Tags::TAG_REQUESTER_SCREEN_NAME => Episciences_Auth::getScreenName(),
                        Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $contributor->getFullName()
                    ];

                }

                $this->paperStatusChangedNotifyManagers($paper, $managersNotificationTemplate, Episciences_Auth::getUser(), $additionalTags);

                $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Vos modifications ont bien été prises en compte');

            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti !");
            }

        }

        $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
    }

    /**
     * send a mail to other editors for asking their opinion
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function askothereditorsAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('id');

        $journal = Episciences_ReviewsManager::find(RVID);
        $journal->loadSettings();
        $paper = Episciences_PapersManager::get($docId);
        if (!$paper) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Vos modifications n'ont pas pu être enregistrées");
            $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
            return;
        }

        if ($request->isPost()) {

            $post = $request->getPost();

            foreach ($post as $name => $value) {
                if (0 !== strpos($name, "editor_")) {
                    continue;
                }
                $uid = filter_var($name, FILTER_SANITIZE_NUMBER_INT);
                $editor = new Episciences_Editor;
                if (!$editor->findWithCAS($uid)) {
                    continue;
                }

                // send mail to editor
                $this->sendMailFromModal($editor, $paper, $post['askothereditorssubject'], $post['askothereditorsmessage'], $post);
            }

            $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Vos modifications ont bien été prises en compte');
        }

        $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
    }

    /**
     * publish paper
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function publishAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('id');

        $journal = Episciences_ReviewsManager::find(RVID);
        $journal->loadSettings();
        $paper = Episciences_PapersManager::get($docId);

        $this->checkPermissions($journal, $paper);

        if ($request->isPost()) {

            $csrfName = 'csrf_publish_' . (int)$docId;
            $csrfSession = new Zend_Session_Namespace('Zend_Form_Element_Hash_unique_' . $csrfName);
            $post = $request->getPost();
            if (!isset($post[$csrfName], $csrfSession->hash) || $post[$csrfName] !== $csrfSession->hash) {
                $csrfSession->hash = null;
                $this->_helper->redirector->gotoUrl($this->_helper->url('view', self::ADMINISTRATE_PAPER_CONTROLLER, null, ['id' => $docId]));
                return;
            }
            $csrfSession->hash = null;

            $data = $request->getPost();

            // get contributor detail
            $contributor = new Episciences_User;
            $contributor->findWithCAS($paper->getUid());

            $paper->setPublication_date(date("Y-m-d H:i:s"));


            // update paper status
            $paper->setStatus(Episciences_Paper::STATUS_PUBLISHED);
            //delete paper password
            $paper->setPassword();

            if ($paper->isPreprint()) { // force article's type tor 'article'
                $currentType = $paper->getType();
                $currentType[Episciences_Paper::TITLE_TYPE] = Episciences_Paper::ARTICLE_TYPE_TITLE;
                $paper->setType($currentType);
            }

            if ($paper->save()) {
                // log new status
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), ['status' => $paper->getStatus()]);

                // send mail to contributor
                $this->sendMailFromModal($contributor, $paper, $data['publicationsubject'], $data['publicationmessage'], $data);

                //Notifier les relecteurs
                $this->paperStatusChangedNotifyReviewer($paper, Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_PUBLISHED_REQUEST_STOP_PENDING_REVIEWING);

                // Notifier les rédacteurs + préparateurs de copie de l'article + selon les pramètres de la revue: red. en chef, admins et secrétaires de red.
                $this->paperStatusChangedNotifyManagers($paper, Episciences_Mail_TemplatesManager::TYPE_PAPER_PUBLISHED_EDITOR_COPY, Episciences_Auth::getUser());
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Vos modifications ont bien été prises en compte');

                $this->_helper->FlashMessenger->setNamespace('success')->addMessage(
                    $this->view->translate("Vous pouvez partager cette publication")
                    . '&nbsp;<a class="btn btn-default" href="'
                    . APPLICATION_URL . $this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]) . '#share">'
                    . $this->view->translate('Partager')
                    . '</a>');

                $this->indexAndCOARNotify($paper, $journal);

            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti !");
            }

        }

        $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
    }

    /**
     * decline a paper
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function refuseAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('id');

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $paper = Episciences_PapersManager::get($docId);

        $this->checkPermissions($review, $paper);

        if ($request->isPost()) {

            $csrfName = 'csrf_refuse_' . (int)$docId;
            $csrfSession = new Zend_Session_Namespace('Zend_Form_Element_Hash_unique_' . $csrfName);
            $post = $request->getPost();
            if (!isset($post[$csrfName], $csrfSession->hash) || $post[$csrfName] !== $csrfSession->hash) {
                $csrfSession->hash = null;
                $this->_helper->redirector->gotoUrl($this->_helper->url('view', self::ADMINISTRATE_PAPER_CONTROLLER, null, ['id' => $docId]));
                return;
            }
            $csrfSession->hash = null;

            $data = $request->getPost();

            // get contributor detail
            $submitter = new Episciences_User;
            $submitter->findWithCAS($paper->getUid());

            // update status
            $paper->setStatus(Episciences_Paper::STATUS_REFUSED);

            // reset paper password
            $paper->setPassword();

            if ($paper->save()) {

                // log new status
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), ['status' => $paper->getStatus()]);

                // send mail to contributor
                $this->sendMailFromModal($submitter, $paper, $data['refusalsubject'], $data['refusalmessage'], $data);

                //Notifier les relecteurs
                $this->paperStatusChangedNotifyReviewer($paper, Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_REFUSED_REQUEST_STOP_PENDING_REVIEWING);

                // Selon les paramètres de la revue, notifier les administrateurs, rédacteurs en chefs et secrétaires de rédaction.
                $this->paperStatusChangedNotifyManagers($paper, Episciences_Mail_TemplatesManager::TYPE_PAPER_REFUSED_EDITORS_COPY, Episciences_Auth::getUser());

                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage('Vos modifications ont bien été prises en compte');
            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti !");
            }
        }

        $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
    }

    /**
     * suggest status change from an editor
     * (can suggest: accept, decline, ask for a revision)
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     */
    public function suggeststatusAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getQuery('id');

        $form = Episciences_PapersManager::getSuggestStatusForm($docId);

        $papersManager = new Episciences_PapersManager;
        $paper = $papersManager::get($docId);

        if ($this->getRequest()->isPost() && !$form->isValid($this->getRequest()->getPost())) {
            $message = $this->view->translate('Pour des raisons de sécurité le formulaire a expiré. Merci de soumettre à nouveau  le formulaire.');
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector->gotoUrl($this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER,'action' => 'view', 'id' => $paper->getDocid()]));
        }

        //Initialisation
        $type = null;
        $input = '';

        $post = $request->getPost();

        // load paper metadata
        $paper->getAllMetadata();

        // Le rédacteur recommande :
        if (array_key_exists('confirm_accept', $post)) {
            $type = Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION;
            $input = 'comment_accept';
        } elseif (array_key_exists('confirm_refuse', $post)) {
            $type = Episciences_CommentsManager::TYPE_SUGGESTION_REFUS;
            $input = 'comment_refuse';
        } elseif (array_key_exists('confirm_newversion', $post)) {
            $type = Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION;
            $input = 'comment_newversion';
        }

        // On enregistre le commentaire en base
        $oComment = new Episciences_Comment();
        $oComment->setType($type);
        $oComment->setDocid($docId);
        $oComment->setMessage($post[$input]);
        $oComment->save();

        // On l'envoie par mail aux rédacteurs en chef, secrétaires de rédaction, administrateurs si le bon paramétrage a été choisi
        $this->newCommentNotifyManager($paper, $oComment);

        $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->addMessage('Vos modifications ont bien été prises en compte');
        $this->_helper->redirector->gotoUrl($this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER,'action' => 'view', 'id' => $paper->getDocid()]));


    }

    /**
     * display paper master volume (ajax display refresh)
     * @throws Zend_Exception
     */
    public function refreshmastervolumeAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docId');
        $from = $request->getPost('from');
        $volume = Episciences_VolumesManager::find($request->getPost('vid'));
        $paper = Episciences_PapersManager::get($docId);

        $htmlPosition = '';


        if ($volume) {
            $htmlPosition = $this->view->partial('partials/paper_volume_position.phtml', [
                'docId' => $paper->getDocid(),
                'vid' => $volume->getVid(),
                'position' => $paper->getPosition(),
                'from' => $from
            ]);
        }

        echo ($volume) ? ($volume->getName() . $htmlPosition) : Zend_Registry::get('Zend_Translate')->translate('aucun');
    }

    /**
     * display paper secondary volumes (ajax display refresh)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function refreshothervolumesAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);
        $paper->loadOtherVolumes();
        $review = Episciences_ReviewsManager::find(RVID);
        $volumes = $review->getVolumes();

        echo $this->view->partial('partials/paper_other_volumes.phtml', ['article' => $paper, 'volumes' => $volumes]);
        return true;
    }

    /**
     * display paper section (ajax display refresh)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function displaysectionAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');
        $partial = filter_var($request->getPost('partial'), FILTER_VALIDATE_BOOLEAN);

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);

        $isAllowed = (
            (Episciences_Auth::isSecretary() || $paper->getEditor(Episciences_Auth::getUid())) &&
            (Episciences_Auth::getUid() !== $paper->getUid())
        );

        $review = Episciences_ReviewsManager::find(RVID);
        $sections = $review->getSections();

        echo $this->view->partial('partials/paper_section.phtml', [
            'article' => $paper,
            'sections' => $sections,
            'isPartial' => $partial,
            'isAllowed' => $isAllowed
        ]);
        return true;
    }

    /**
     * editor assignment form
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function editorsformAction(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');
        $vid = $request->getPost('vid');
        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper) {
            return false;
        }


        $editors = $this->getEditors($paper);

        if ($vid) {
            $volume = Episciences_VolumesManager::find($vid);

            if ($volume) {
                $editors = array_replace($editors, $volume->getEditors());
            }
        }

        // Exclure l'auteur de l'article
        unset($editors[$paper->getUid()]);

        //Exclure les rédacteurs qui ont déjà refusés la supervision de l'article
        foreach ($editors as $uid => $editor) {
            if (!$paper->getEditor($uid) && Episciences_EditorsManager::isMonitoringRefused($uid, $docId)) {
                unset($editors[$uid]);
            }
        }

        $this->view->suggestedEditors = Episciences_EditorsManager::getSuggestedEditors($docId);

        if ($editors) {
            try {
                $this->view->editors = $editors;
                $this->view->editorsForm = Episciences_PapersManager::getEditorsForm($docId, $editors);

                // Get unavailable editors
                $unavailableEditors = [];
                foreach ($editors as $editor) {
                    if (!Episciences_UsersManager::isEditorAvailable($editor->getUid(), RVID)) {
                        $unavailableEditors[] = $editor->getUid();
                    }
                }
                $this->view->unavailableEditors = $unavailableEditors;
            } catch (Exception $e) {
                trigger_error('EDITORS_FORM_ACTION : ' . $e, E_USER_WARNING);
            }
        }

        $this->_helper->layout->disableLayout();
        return true;

    }

    /**
     * formulaire d'assigantion des CE
     * @return bool
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function copyeditorsformAction(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');
        $vId = $request->getPost('vid');

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper) {
            return false;
        }

        $copyEditors = $this->getCopyEditors($paper);

        if ($vId) {
            $volume = Episciences_VolumesManager::find($vId);
            if ($volume) {
                $copyEditors = array_replace($copyEditors, $volume->getCopyEditors());
            }
        }

        // Exclure l'auteur de l'article
        unset($copyEditors[$paper->getUid()]);

        if (!empty($copyEditors)) {
            $this->view->copyEditors = $copyEditors;
            try {
                $this->view->copyEditorsForm = Episciences_PapersManager::getCopyEditorsForm($docId, $copyEditors);

            } catch (Exception $e) {
                trigger_error('COPY_EDITORS_FORM_ACTION : ' . $e, E_USER_WARNING);
            }

        }

        $this->_helper->layout->disableLayout();
        return true;
    }

    /**
     * save paper copy editors
     * @throws JsonException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function savecopyeditorsAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = ($request->getPost('docid')) ?: $request->getParam('docid');

        $paper = Episciences_PapersManager::get($docId);

        if (!$paper) {
            echo false;
            return;
        }

        if ($request->isPost() && $request->get('type') === 'copyeditors') {

            $docId = (int)$paper->getDocid();

            // Rédacteurs assignés à l'article
            /** @var Episciences_Editor $assignedEditors */
            $recipients = $this->getAllEditors($paper);
            // Selon les paramètres de la revue, notifier les rédacteurs en chef, secrétaires de rédaction + administrateurs
            Episciences_Review::checkReviewNotifications($recipients);

            Episciences_PapersManager::keepOnlyUsersWithoutConflict($paper->getPaperid(), $recipients);

            $CC = $paper->extractCCRecipients($recipients);

            if (empty($recipients)) {
                $recipients = $CC;
                $CC = [];
            }

            // new copy editor assignments
            $submittedCopyEditors = $request->getPost('copyeditors');
            $copyEditors = ($submittedCopyEditors) ? array_map('intval', $submittedCopyEditors) : [];

            // currently assigned editors
            $currentCopyEditors = $paper->getCopyEditors();

            // sort added editors and removed editors
            $added = array_diff($copyEditors, array_keys($currentCopyEditors));
            $removed = array_diff(array_keys($currentCopyEditors), $copyEditors);

            //admin paper URL
            $adminPaperUrl = $this->adminPaperUrl($docId);

            // public paperURL
            $paperUrl = $this->publicPaperUrl($docId);

            if (!empty($removed)) {
                $this->unssignUser($paper, $removed, $paperUrl, Episciences_User_Assignment::ROLE_COPY_EDITOR, Episciences_Auth::getUid());
            }

            if (!empty($added)) {

                $author = new Episciences_User();
                $author->findWithCAS($paper->getUid());
                $aLocale = $author->getLangueid();

                $added = $this->assignUser($paper, $added, $adminPaperUrl, Episciences_User_Assignment::ROLE_COPY_EDITOR);

                // Envoi de mails a l'auteur
                $commonTags = [
                    Episciences_Mail_Tags::TAG_ARTICLE_ID => $docId,
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid()
                ];

                $authorTags = array_merge(
                    [
                        Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl,
                        Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($aLocale, true),
                        Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($aLocale),
                        Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $aLocale)
                    ], $commonTags);

                Episciences_Mail_Send::sendMailFromReview($author, Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN_AUTHOR_COPY, $authorTags, $paper);

                // informer les rédacteurs, autres corrceteurs, administrateurs, secrétaires de rédaction et rédcateurs en chefs
                Episciences_Submit::addIfNotExists($this->getAllCopyEditors($paper), $recipients);
                /** @var Episciences_User $recipient */
                foreach ($recipients as $recipient) {
                    $rLocale = $recipient->getLangueid();

                    if (in_array($recipient->getUid(), $added)) { // empilement des rôles , ne pas renvoyer le mail
                        continue;
                    }

                    $recipientTags = array_merge(
                        [
                            Episciences_Mail_Tags::TAG_PAPER_URL => $adminPaperUrl,
                            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($rLocale, true),
                            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($rLocale),
                        ], $commonTags);

                    Episciences_Mail_Send::sendMailFromReview(
                        $recipient, Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN_EDITOR_COPY, $recipientTags,
                        $paper, null, [], false, $CC
                    );
                    //reset $CC
                    $CC = [];
                }
            }

            echo Zend_Json_Encoder::encode(['result' => true]);
        }
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $added
     * @param string $paper_url
     * @param string $userAssignment
     * @return array
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private function assignUser(Episciences_Paper $paper, array $added, string $paper_url = '', string $userAssignment = Episciences_User_Assignment::ROLE_EDITOR): array
    {
        $loggerType = Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT;
        $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_ASSIGN;

        if ($userAssignment === Episciences_Acl::ROLE_COPY_EDITOR) {
            $loggerType = Episciences_Paper_Logger::CODE_COPY_EDITOR_ASSIGNMENT;
            $templateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_COPY_EDITOR_ASSIGN;
        }

        foreach ($added as $uid) {

            // fetch user data
            $assignedUser = new Episciences_User;
            $assignedUser->findWithCAS($uid);
            $locale = $assignedUser->getLangueid();

            // save new user assignment
            try {
                $aid = $paper->assign($uid, $userAssignment);
            } catch (Exception $e) {
                trigger_error($e . ' : ASSIGN_USER_UID : ' . $assignedUser->getUid() . ' Paper ID : ' . $paper->getDocid());
                continue;
            }

            // log assignment
            if (
                !$paper->log($loggerType, Episciences_Auth::getUid(), ["aid" => $aid, "user" => $assignedUser->toArray()])
            ) {
                trigger_error('Error: failed to log ' . $loggerType . ' AID : ' . $aid . ' UID : ' . $assignedUser->getUid());
            }

            $tags = [

                Episciences_Mail_Tags::TAG_SENDER_EMAIL => Episciences_Auth::getEmail(),
                Episciences_Mail_Tags::TAG_SENDER_FULL_NAME => Episciences_Auth::getFullName(),
                Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $assignedUser->getUsername(),
                Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $assignedUser->getFullName(),
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata(),
                Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $this->view->Date($paper->getSubmission_date(), $locale),
                Episciences_Mail_Tags::TAG_PAPER_URL => $paper_url

            ];

            Episciences_Mail_Send::sendMailFromReview($assignedUser, $templateType, $tags, $paper);
        }
        return $added;
    }

    /**
     * save paper master volume
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function savemastervolumeAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = (int)($request->getPost('docid')) ?: $request->getParam('docid');

        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper) {
            echo false;
            return;
        }

        if ($request->isPost()) {

            $oldVid = $paper->getVid();
            $vid = (int)$request->getPost('vid');

            if ($vid !== $oldVid) {

                if (
                    $oldVid &&
                    !$paper->deletePosition() //deletion of the old position
                ) {
                    $logMsg = 'Moving a paper (docId = . ' . $docId . ') from (vid = ' . $oldVid . ') ' . 'to (vid = ' . $vid . '): failed to delete position in old volume';
                    $logMsg .= ' or the paper has not been positioned';
                    trigger_error($logMsg);
                }

                $paper->setVid($vid); // new volume
                $paper->save();
                $paper->log(
                    Episciences_Paper_Logger::CODE_VOLUME_SELECTION,
                    Episciences_Auth::getUid(),
                    ['vid' => $vid]
                );

                // deleting the volume from T_VOLUME_PAPER
                Episciences_Volume_PapersManager::deletePaperVolume($docId, $vid);

            }

            echo true;
        }
    }

    /**
     * save paper secondary volumes
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function saveothervolumesAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost()) {

            $errors = [];
            $docid = $request->getPost('docid');
            $paper = Episciences_PapersManager::get($docid);

            // process form and retrieve volume ids
            $paper_volumes = [];
            foreach ($request->getPost() as $name => $value) {
                if (!preg_match('#^volume_#', $name)) {
                    continue;
                }
                $vid = filter_var($name, FILTER_SANITIZE_NUMBER_INT);
                // master volume can't be a secondary volume
                if ($vid == $paper->getVid()) {
                    continue;
                }
                $paper_volumes[] = new Episciences_Volume_Paper(['vid' => $vid, 'docid' => $docid]);
            }

            $paper->setOtherVolumes($paper_volumes);
            $paper->saveOtherVolumes();
            $oOVolumes = $paper->getOtherVolumes(true);
            $oVolumes = [];

            /** @var Episciences_Volume_Paper $oOVolume */

            foreach ($oOVolumes as $oOVolume) {
                $oVolumes [] = $oOVolume->toArray();
            }

            $paper->log(Episciences_Paper_Logger::CODE_OTHER_VOLUMES_SELECTION, Episciences_Auth::getUid(), ['vids' => $oVolumes]);

            if ($paper->isPublished()) {
                $resOfIndexing = $paper->indexUpdatePaper();

                if (!$resOfIndexing) {
                    try {
                        Ccsd_Search_Solr_Indexer::addToIndexQueue([$paper->getDocid()], RVCODE, Ccsd_Search_Solr_Indexer::O_UPDATE, Ccsd_Search_Solr_Indexer_Episciences::$coreName);
                    } catch (Exception $e) {
                        trigger_error($e->getMessage());
                    }
                }

            }

            echo empty($errors);
        }
    }

    /**
     * save paper section
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function savesectionAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = ($request->getPost('docid')) ?: $request->getParam('docid');

        $paper = Episciences_PapersManager::get($docId);
        if (!$paper) {
            echo false;
            return;
        }

        if ($request->isPost()) {

            $sid = (int)$request->getPost('sid');
            $paper->setSid($sid);
            $paper->save();
            $paper->log(
                Episciences_Paper_Logger::CODE_SECTION_SELECTION,
                Episciences_Auth::getUid(),
                ['sid' => $sid]);

            // if checkbox is checked,
            if ($request->getPost('assignEditors')) {

                // assign section editors to this article
                $section = Episciences_SectionsManager::find($sid);
                $sectionEditors = $section->getEditors();
                $paperEditors = $paper->getEditors(true, true);

                // filter editors already assigned to this article (avoid reassignment)
                $editors = array_diff_key($sectionEditors, $paperEditors);

                if (!empty($editors)) {

                    $paper_url = self::buildAdminPaperUrl($paper->getDocid());

                    foreach ($editors as $uid => $editor) {

                        // save editor assignment to paper
                        try {
                            $aid = $paper->assign($uid, Episciences_User_Assignment::ROLE_EDITOR);
                        } catch (Exception $e) {
                            continue;
                        }

                        // log editor assignment to paper
                        $paper->log(
                            Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT,
                            Episciences_Auth::getUid(),
                            ["aid" => $aid, "user" => $editor->toArray()]);

                        // send mail
                        $locale = $editor->getLangueid();
                        $template = new Episciences_Mail_Template();
                        $template->findByKey(Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_ASSIGN);
                        $template->loadTranslations();
                        $template->setLocale($locale);

                        $mail = new Episciences_Mail('UTF-8');
                        $mail->setDocid($docId);
                        $mail->addTag(Episciences_Mail_Tags::TAG_SENDER_EMAIL, Episciences_Auth::getEmail());
                        $mail->addTag(Episciences_Mail_Tags::TAG_SENDER_FULL_NAME, Episciences_Auth::getFullName());
                        $mail->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME, $editor->getUsername());
                        $mail->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME, $editor->getFullName());
                        $mail->addTag(Episciences_Mail_Tags::TAG_ARTICLE_ID, $paper->getDocid());
                        $mail->addTag(Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID, $paper->getPaperid());
                        $mail->addTag(Episciences_Mail_Tags::TAG_ARTICLE_TITLE, $paper->getTitle($locale, true));
                        $mail->addTag(Episciences_Mail_Tags::TAG_AUTHORS_NAMES, $paper->formatAuthorsMetadata());
                        $mail->addTag(Episciences_Mail_Tags::TAG_SUBMISSION_DATE, $this->view->Date($paper->getSubmission_date(), $locale));
                        $mail->addTag(Episciences_Mail_Tags::TAG_PAPER_URL, $paper_url);
                        $mail->setFromReview();
                        $mail->setTo($editor);
                        $mail->setSubject($template->getSubject());
                        $mail->setTemplate($template->getPath(), $template->getKey() . '.phtml');
                        $mail->writeMail();

                        // log mail sending
                        $paper->log(
                            Episciences_Paper_Logger::CODE_MAIL_SENT,
                            Episciences_Auth::getUid(),
                            ['id' => $mail->getId(), 'mail' => $mail->toArray()]);
                    }
                }
            }
            try {
                echo json_encode(true, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            }
        }
    }

    /**
     * save paper editors
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function saveeditorsAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = ($request->getPost('docid')) ?: $request->getParam('docid');

        $paper = Episciences_PapersManager::get($docId);
        if (!$paper) {
            echo false;
            return;
        }

        if ($request->isPost() && $request->get('type') === 'editors') {

            // new editor assignments
            $submittedEditors = $request->getPost('editors');
            $editors = ($submittedEditors) ? array_map('intval', $submittedEditors) : [];

            // currently assigned editors
            $currentEditors = $paper->getEditors();

            // sort added editors and removed editors
            $added = array_diff($editors, array_keys($currentEditors));
            $removed = array_diff(array_keys($currentEditors), $editors);

            $paper_url = self::buildAdminPaperUrl($paper->getDocid());

            if (!empty($added)) {
                $this->assignUser($paper, $added, $paper_url);
            }

            if (!empty($removed)) {
                $this->unssignUser($paper, $removed, $paper_url, Episciences_User_Assignment::ROLE_EDITOR, Episciences_Auth::getUid());
            }

            echo Zend_Json_Encoder::encode(['result' => true]);
        }
    }

    /**
     * display paper logs (partial render for ajax)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function displaylogsAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('docid');

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);
        if (!$paper) {
            return false;
        }

        $this->view->logs = $paper->getHistory();
        $this->view->docid = $docId;
        $this->renderScript('/partials/paper_history.phtml');
        return true;
    }

    /**
     * display paper editors (partial)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function displayeditorsAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');
        $partial = filter_var($request->getPost('partial'), FILTER_VALIDATE_BOOLEAN);

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);
        $editors = $paper->getEditors();

        echo !$partial ?
            $this->view->partial('partials/paper_editors.phtml', ['article' => $paper, 'editors' => $editors, 'isPartial' => $partial]) :
            $this->view->partial('partials/partial_paper_editors.phtml', ['paper' => $paper, 'users' => $editors]);
        return true;
    }

    /**
     *
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function displaycopyeditorsAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');
        $partial = filter_var($request->getPost('partial'), FILTER_VALIDATE_BOOLEAN);

        if (!$docId) {
            return false;
        }

        /** @var Episciences_Paper $paper */
        $paper = Episciences_PapersManager::get($docId);
        $copyEditors = $paper->getCopyEditors();
        $editors = $paper->getEditors();

        echo $this->view->partial('partials/paper_copy_editors.phtml', ['article' => $paper, 'copyEditors' => $copyEditors, 'editors' => $editors, 'isPartial' => $partial]);
        return true;
    }

    /**
     * display paper reviewers (partial)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function displayreviewersAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('docid');
        $partial = filter_var($request->getPost('partial'), FILTER_VALIDATE_BOOLEAN);

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);
        $reviewers = $paper->getReviewers([Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_PENDING], true);

        echo $this->view->partial('partials/paper_reviewers.phtml', ['article' => $paper, 'reviewers' => $reviewers, 'isPartial' => $partial]);
        return true;
    }

    /**
     * display rating invitations (partial)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function displayinvitationsAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('docid');
        $partial = filter_var($request->getPost('partial'), FILTER_VALIDATE_BOOLEAN);

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);
        if ($paper->isObsolete()) {
            // get paper reviewers, including inactive ones (when a paper is obsolete, reviewers are disabled)
            $paper->getReviewers([Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE, Episciences_User_Assignment::STATUS_PENDING], true);
        } else {
            // get active reviewers, and those who have a pending invitation
            $paper->getReviewers([Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_PENDING], true);
        }

        $invitations = $paper->getInvitations(
            [
                Episciences_User_Assignment::STATUS_ACTIVE,
                Episciences_User_Assignment::STATUS_INACTIVE,
                Episciences_User_Assignment::STATUS_PENDING,
                Episciences_User_Assignment::STATUS_CANCELLED,
                Episciences_User_Assignment::STATUS_DECLINED
            ], true);

        if (array_key_exists(Episciences_User_Assignment::STATUS_ACTIVE, $invitations)) {
            foreach ($invitations[Episciences_User_Assignment::STATUS_ACTIVE] as &$invitation) {
                $reviewer = $paper->getReviewer($invitation['UID']);
                if (!$reviewer) {
                    continue;
                }
                $reviewing = $reviewer->getReviewing($docId);
                if (!$reviewing) {
                    continue;
                }
                $invitation['reviewer']['rating']['status'] = $reviewing->getStatus();
                $invitation['reviewer']['rating']['last_update'] = $reviewing->getUpdateDate();
            }
            unset($invitation);
        }

        echo $this->view->partial('partials/paper_reviewers.phtml', ['article' => $paper, 'invitations' => $invitations, 'isPartial' => $partial]);
        return true;
    }

    /**
     * DOI edit form (ajax)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function doiformAction(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');
        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);
        $this->view->doi = $paper->getDoi();
        $this->view->docid = $paper->getDocid();

        $this->_helper->layout->disableLayout();
        $this->renderScript(self::ADMINISTRATE_PAPER_CONTROLLER . '/doiform.phtml');
        return true;
    }

    /**
     * Save paper DOI
     * @throws Zend_Db_Adapter_Exception
     */
    public function savedoiAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost() || !$request->isXmlHttpRequest()) {
            return;
        }

        if (!Episciences_Auth::isLogged() || !Episciences_Auth::isAllowedToManageDoi()) {
            echo json_encode(['success' => false, 'doi' => '', 'error' => 'Unauthorized access']);
            trigger_error(sprintf('Unauthorized access to savedoi by uid=%s', Episciences_Auth::getUid()), E_USER_WARNING);
            return;
        }

        $docId   = (int) $request->getPost('docid');
        $paperId = (int) $request->getPost('paperid');
        $doi     = trim((string) $request->getPost('doi'));

        if ($docId <= 0 || $paperId <= 0) {
            echo json_encode(['success' => false, 'doi' => '', 'error' => 'Invalid document ID']);
            return;
        }

        if ($doi === '') {
            echo json_encode(['success' => false, 'doi' => '', 'error' => $this->view->translate('DOI cannot be empty')]);
            return;
        }

        $doiPattern = '/^10\.\d{4,9}\/[-._;()\/:A-Z0-9]+$/i';

        if (!preg_match($doiPattern, $doi)) {
            echo json_encode(['success' => false, 'doi' => '', 'error' => $this->view->translate('Motif de DOI incorrect')]);
            return;
        }

        if (!Episciences_PapersManager::paperExists($docId, RVID)) {
            echo json_encode(['success' => false, 'doi' => '', 'error' => $this->view->translate('Document non trouvé')]);
            trigger_error(sprintf('Docid %d not found in RVID %s', $docId, RVID), E_USER_WARNING);
            return;
        }

        // updateDoi() returns the number of rows affected by the UPDATE.
        // 0 rows affected is not an error: it means the DOI was already set to this
        // value (MySQL does not update unchanged rows). Treat as an idempotent success.
        Episciences_PapersManager::updateDoi($doi, $paperId);

        // Add or update the DOI queue entry with STATUS_ASSIGNED so the badge
        // and "Cancel the DOI" button are shown in the UI after a manual save.
        $doiQueueEntry = new Episciences_Paper_DoiQueue([
            'paperid'    => $paperId,
            'doi_status' => Episciences_Paper_DoiQueue::STATUS_ASSIGNED,
        ]);
        $existingQueue = Episciences_Paper_DoiQueueManager::findByPaperId($paperId);
        if ($existingQueue->getId_doi_queue() > 0) {
            $doiQueueEntry->setId_doi_queue($existingQueue->getId_doi_queue());
            Episciences_Paper_DoiQueueManager::update($doiQueueEntry);
        } else {
            Episciences_Paper_DoiQueueManager::add($doiQueueEntry);
        }

        Episciences_Paper_Logger::log($paperId, $docId, Episciences_Paper_Logger::CODE_DOI_UPDATED, Episciences_Auth::getUid(), json_encode(['DOI' => $doi]), null, RVID);

        echo json_encode(['success' => true, 'doi' => $doi, 'error' => '']);
    }

    /**
     * unassign a reviewer
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function removereviewerAction(): bool
    {
        $this->_helper->layout()->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $status = $request->getParam('status');

        $isUninvited = $status && ($status === Episciences_Reviewer::STATUS_UNINVITED);

        //assignment id
        $aid = $request->getParam('aid');
        if (!$aid || !is_numeric($aid)) {
            return false;
        }

        //assignment object
        $oAssignment = Episciences_User_AssignmentsManager::findById($aid);
        if (!$oAssignment) {
            return false;
        }

        //paper object
        $oPaper = Episciences_PapersManager::get($oAssignment->getItemid(), false);
        if (!$oPaper) {
            return false;
        }

        if (!$isUninvited) {
            //invitation object
            $oInvitation = Episciences_User_InvitationsManager::findById($oAssignment->getInvitation_id());
            if (!$oInvitation) {
                return false;
            }

            //review object
            $oReview = Episciences_ReviewsManager::find(RVID);
            $review = ['rvid' => RVID, 'code' => RVCODE, 'name' => $oReview->getName()];

            //user object
            if ($oAssignment->isTmp_user()) {
                $oReviewer = Episciences_TmpUsersManager::findById($oAssignment->getUid());
                $oReviewer->generateScreen_name();
                if ('' === $oReviewer->getScreenName()) {
                    return false;
                }
            } else {
                $oReviewer = new Episciences_Reviewer;
                if (!$oReviewer->findWithCAS($oAssignment->getUid())) {
                    return false;
                }
            }

            $locale = $oReviewer->getLangueid(true);
            $reviewer = [
                'locale' => $locale,
                'full_name' => $oReviewer->getFullName(),
                'user_name' => ($oAssignment->isTmp_user()) ? $oReviewer->getUsername() : null,
                'email' => $oReviewer->getEmail()
            ];

            //template
            $template = new Episciences_Mail_Template;
            $template->findByKey(Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_REMOVAL);
            $template->loadTranslations();

            //tags
            $tags = [
                Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => !$oReviewer->getScreenName() ? $reviewer['full_name'] : $oReviewer->getScreenName(),
                Episciences_Mail_Tags::TAG_SENDER_FULL_NAME => Episciences_Auth::getFullName(),
                Episciences_Mail_Tags::TAG_ARTICLE_ID => $oPaper->getDocid(),
                Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $oPaper->getPaperid(),
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $oPaper->getTitle($locale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $oPaper->formatAuthorsMetadata(),
                Episciences_Mail_Tags::TAG_REVIEW_CODE => RVCODE,
                Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => (!$oAssignment->isTmp_user()) ? $oReviewer->getUsername() : '',
            ];

            $subject = str_replace(array_keys($tags), array_values($tags), $template->getSubject());
            $body = str_replace(array_keys($tags), array_values($tags), $template->getBody());
            if ($oAssignment->isTmp_user()) {
                $body = Episciences_Tools::cleanBody($body);
            }

            //invitation form
            $form = Episciences_PapersManager::getReviewerRemovalForm($aid, $oPaper->getDocid());
            $defaults = [
                'recipient' => $oReviewer->getFullName() . ' <' . $oReviewer->getEmail() . '>',
                'subject' => $subject,
                'body' => nl2br($body)
            ];

            $form->setDefaults($defaults);
            //tags ?
            $this->view->js_reviewer = Zend_Json::encode($reviewer);
            $this->view->js_paper = Zend_Json::encode(['id' => $oPaper->getDocid(), 'title' => $oPaper->getTitle()]);
            $this->view->js_review = Zend_Json::encode($review);
            $this->view->js_editor = Zend_Json::encode(['full_name' => Episciences_Auth::getFullName(), 'email' => Episciences_Auth::getEmail()]);
            $this->view->available_languages = Zend_Json::encode(Episciences_Tools::getLanguages());

        } else {
            $form = Episciences_PapersManager::getReviewerRemovalForm($aid, $oPaper->getDocid(), true);
            $alertMsg = Zend_Registry::get('Zend_Translate')->translate('Vous êtes sur le point de supprimer un rapport de relecture.');
            $form->setDefaults([
                'note' => $this->buildAlertMessage('', $alertMsg)
            ]);
        }

        $this->view->isUninvited = $isUninvited;
        $this->view->form = $form;

        return true;
    }

    /**
     * Passer le status d'un rapport de relecture de l'état "2 : STATUS_COMPLETED"
     * à l'état "1: STATUS_WIP", permettant ainsi au reviewer de modifier sa relecture.
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function refreshratingAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $request = $this->getRequest();
        $docId = (int)$request->getParam('id');
        $reviewerUid = (int)$request->getParam('reviewer_uid');

        if (empty($docId) || empty($reviewerUid)) {
            $this->_helper->redirector->goToUrl($this->url(['controller' => 'index']));
        }

        $report = Episciences_Rating_Report::find($docId, $reviewerUid);
        $paper = Episciences_PapersManager::get($docId);
        if ($paper && $report) {
            if ($report->isCompleted() && ($paper->getEditor(Episciences_Auth::getUid()) || Episciences_Auth::isAllowedToUploadPaperReport())) {
                $report->setStatus(Episciences_Rating_Report::STATUS_WIP);
                if (!$report->save()) {
                    $message = "Une erreur s'est produite pendant l'enregistrement de vos modifications.";
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                } else {
                    $paper->ratingRefreshPaperStatus();
                    // log
                    $paper->log('alter_report_status', Episciences_Auth::getUid(), [
                        'user' => Episciences_Auth::getUser()->toArray()
                    ]);
                    $message = $this->view->translate("Le status de la relecture a été changé avec succès");
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                }
            } else { // Pas autoriser
                $message = $this->view->translate("Vous n'avez pas les droits suffisants pour changer le statut de cette relecture");
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            }
        } else {
            return;

        }

        $this->_helper->redirector->gotoUrl($this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'view', 'id' => $docId]));
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    public function savereviewerremovalAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $translator = Zend_Registry::get('Zend_Translate');
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $status = $request->getParam('status');
        $isUninvited = $status && ($status === Episciences_Reviewer::STATUS_UNINVITED);

        $result = ['status' => 0];

        //assignment id
        $id = $request->getParam('aid');

        if (!$id || !is_numeric($id)) {

            $result['message'] = $translator->translate("Id invalide");
            echo Zend_Json::encode($result);
            return;
        }

        //assignment
        $oAssignment = Episciences_User_AssignmentsManager::findById($id);
        if (!$oAssignment) {
            $result['message'] = $translator->translate("Impossible de retrouver l'assignation");
            echo Zend_Json::encode($result);
            return;
        }

        if (!$isUninvited) {
            $post = $request->getPost(); //subject, body
            //invitation object
            $oInvitation = Episciences_User_InvitationsManager::findById($oAssignment->getInvitation_id());
            if (!$oInvitation) {
                $result['message'] = $translator->translate("Impossible de retrouver l'invitation");
                echo Zend_Json::encode($result);
                return;
            }
        }

        $paper = Episciences_PapersManager::get($oAssignment->getItemid(), false);

        if (!$paper) {
            $result['message'] = $translator->translate("Impossible de retrouver l'article");
            echo Zend_Json::encode($result);
            return;
        }

        $docId = $paper->getDocid();
        $errors = [];

        //user
        if ($oAssignment->isTmp_user()) {
            $reviewer = Episciences_TmpUsersManager::findById($oAssignment->getUid());
            if ($reviewer) {
                $reviewer->generateScreen_name();
            }
        } else {
            $reviewer = new Episciences_Reviewer;
            $reviewer->findWithCAS($oAssignment->getUid());
        }

        if (!$reviewer) {
            $result['message'] = $translator->translate("Impossible de trouver le relecteur");
            echo Zend_Json::encode($result);
            return;
        }

        $uid = $reviewer->getUid();

        if (!$isUninvited) {
            //invitation update (cancel)
            $oInvitation->setStatus($oInvitation::STATUS_CANCELLED);
            $oInvitation->save();
        }

        //update assignment  (cancel)
        $params = [
            'itemid' => $oAssignment->getItemid(),
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'status' => Episciences_User_Assignment::STATUS_CANCELLED,
            'tmp_user' => $oAssignment->isTmp_user()
        ];
        /** @var Episciences_User_Assignment $newAssignment */
        $newAssignment = Episciences_UsersManager::unassign($oAssignment->getUid(), $params)[0];

        $logOptions = [
            'aid' => $oAssignment->getId(),
            'invitation_id' => !$isUninvited ? $oInvitation->getId() : null,
            'tmp_user' => $oAssignment->isTmp_user(),
            'uid' => $oAssignment->getUid(),
            'user' => $reviewer->toArray()
        ];

        if (!$isUninvited) {
            $newAssignment->setInvitation_id($oInvitation->getId());
        } else {
            $logOptions = array_merge($logOptions, [Episciences_Reviewer::STATUS_UNINVITED => Episciences_Reviewer::STATUS_UNINVITED]);
        }

        $newAssignment->save();

        // log de la suppression
        $log = $paper->log(Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT, Episciences_Auth::getUid(), $logOptions);

        if (!$log) {
            $errors[] = "Le log de suppression du relecteur n'a pas pu être enregistré";
        }

        if ($uid) {
            //remove rating report
            Episciences_Rating_ReportManager::deleteByUidAndDocId($uid, $docId);
            // delete reviewer alias
            Episciences_Reviewer_AliasManager::delete($docId, $uid);

            //delete reviewer grid
            Episciences_Tools::deleteDir(Episciences_PapersManager::buildDocumentPath($docId) . '/reports/' . $uid);
        }

        if (!$isUninvited) { // Seulement si une invitation est à l'origine de la relecture
            //mail to the reviewer
            $mail = new Episciences_Mail('UTF-8');
            $mail->setDocid($oAssignment->getItemid());
            $mail->setTo($reviewer);
            $mail->setSubject($post['subject']);
            $mail->setRawBody(Ccsd_Tools::clear_nl($post['body']));

            // Other reciptients
            $cc = (!empty($post['cc'])) ? explode(';', $post['cc']) : [];
            $bcc = (!empty($post['bcc'])) ? explode(';', $post['bcc']) : [];
            $this->addOtherRecipients($mail, $cc, $bcc);
            $mail->writeMail();

            //log mail
            $log = $paper->log(
                Episciences_Paper_Logger::CODE_MAIL_SENT,
                Episciences_Auth::getUid(),
                ['id' => $mail->getId(), 'mail' => $mail->toArray()]);
            if (!$log) {
                $errors[] = "Le log de l'e-mail n'a pas pu être enregistré";
            }
        }

        //ajax response
        echo Zend_Json::encode([
            'status' => 1,
            'id' => $id,
            'errors' => $errors
        ]);
    }

    /**
     * *Formulaire de réassignation du rédacteur d'un article (pour Ajax)
     * //public function declinepaperassignmentAction()
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function reassignAction(): bool
    {
        $errors = [];
        $this->_helper->layout->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        // Récupération de l'id de l'article
        $docId = $request->getParam('docid');
        if (!$docId) {
            $errors[] = "Impossible de trouver l'article";
            $this->view->errors = $errors;
            return false;
        }

        // Récupération de l'article
        $oPaper = Episciences_PapersManager::get($docId);
        if (!$oPaper) {
            $errors[] = "Impossible de trouver l'article";
            $this->view->errors = $errors;
            return false;
        }

        // Récupération de la revue
        $oReview = Episciences_ReviewsManager::find(RVID);
        // Préparation de js_review
        $review = [
            'id' => $oReview->getRvid(),
            'code' => $oReview->getCode(),
            'name' => $oReview->getName()
        ];

        // Récupération du volume
        $oVolume = Episciences_VolumesManager::find($oPaper->getVid());
        $oVolume->loadSettings();

        // On vérifie si la revue autorise le rédacteur à réassigner l'article
        if (!$oVolume->getSetting(Episciences_Volume::SETTING_SPECIAL_ISSUE) ||
            !$oReview->getSetting(Episciences_Review::SETTING_EDITORS_CAN_REASSIGN_ARTICLES)
        ) {
            $errors[] = "Vous n'avez pas les droits nécessaires pour réassigner cet article";
            $this->view->errors = $errors;
            return false;
        }

        // Récupération des rédacteurs du volume
        $volume_editors = $oVolume->getEditors();
        $paper_editors = $oPaper->getEditors();
        $editors = [];
        foreach ($volume_editors as $editor) {
            // on ne peut pas se réassigner l'article
            if ($editor->getUid() == Episciences_Auth::getUid()) {
                continue;
            }
            // on ne peut pas réassigner l'article à un rédacteur déjà en charge de l'article
            if (array_key_exists($editor->getUid(), $paper_editors)) {
                continue;
            }
            $editors[$editor->getUid()] = $editor->getFullname();
            $js_editors[$editor->getUid()] = [
                'locale' => $editor->getLangueid(),
                'full_name' => $editor->getFullname(),
                'user_name' => $editor->getUsername(),
                'email' => $editor->getEmail()
            ];
        }


        // Chargement du formulaire
        $form = Episciences_PapersManager::getReassignmentForm($docId, $editors);
        $form->setDefault('editor', $editors);

        // Chargement du template
        $oTemplate = new Episciences_Mail_Template();
        $oTemplate->findByKey(Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_ASSIGN);
        $oTemplate->loadTranslations();

        $template = $oTemplate->toArray();
        $template['subject'] = $oTemplate->loadSubject(Episciences_Tools::getLanguages());
        unset($template['id'], $template['parentId'], $template['rvid'], $template['key'], $template['type']);


        $this->view->form = $form;
        $this->view->available_languages = Zend_Json::encode(Episciences_Tools::getLanguages());
        $this->view->template = $oTemplate;
        $paper_url = $this->adminPaperUrl($oPaper->getDocid());
        $this->view->js_paper = Zend_Json::encode(array_merge($oPaper->getAllMetadata(), ['url' => $paper_url]));
        $this->view->js_review = Zend_Json::encode($review);
        $this->view->js_template = Zend_Json::encode($template);
        $this->view->js_editors = Zend_Json::encode($js_editors);
        return true;
    }

    /**
     * reassign an article to another editor
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function savereassignmentAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();

        $docId = $request->getParam('docid');
        if (!$docId) {
            return;
        }

        // load paper
        $oPaper = Episciences_PapersManager::get($docId);
        if (!$oPaper) {
            return;
        }

        // unassign current editor
        $oPaper->unassign(Episciences_Auth::getUid(), Episciences_User_Assignment::ROLE_EDITOR);

        // load template
        $template = new Episciences_Mail_Template();
        $template->findByKey(Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_UNASSIGN); // TODO: create new template for this
        $template->loadTranslations();

        $locale = Episciences_Auth::getLangueid();
        $template->setLocale($locale);

        // set tags and send the mail
        $mail = new Episciences_Mail('UTF-8');
        $mail->setDocid($docId);
        $mail->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME, Episciences_Auth::getUsername());
        $mail->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME, Episciences_Auth::getScreenName());
        $mail->addTag(Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME, Episciences_Auth::getFullName());
        $mail->addTag(Episciences_Mail_Tags::TAG_ARTICLE_ID, $oPaper->getDocid());
        $mail->addTag(Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID, $oPaper->getPaperid());
        $mail->addTag(Episciences_Mail_Tags::TAG_ARTICLE_TITLE, $oPaper->getTitle($locale, true));
        $mail->addTag(Episciences_Mail_Tags::TAG_AUTHORS_NAMES, $oPaper->formatAuthorsMetadata());
        $mail->addTag(Episciences_Mail_Tags::TAG_SUBMISSION_DATE, $this->view->Date($oPaper->getSubmission_date(), $locale));
        $mail->addTag(Episciences_Mail_Tags::TAG_PAPER_URL, self::buildAdminPaperUrl($oPaper->getDocid()));
        $mail->addTag(Episciences_Mail_Tags::TAG_SENDER_EMAIL, Episciences_Auth::getEmail());
        $mail->addTag(Episciences_Mail_Tags::TAG_SENDER_FULL_NAME, Episciences_Auth::getFullName());
        $mail->setFromReview();
        $mail->setTo(Episciences_Auth::getUser());
        $mail->setSubject($template->getSubject());
        $mail->setTemplate($template->getPath(), $template->getKey() . '.phtml');
        $mail->writeMail();


        // load new editor
        $editor = new Episciences_Editor;
        $editor->findWithCAS($post['editor']);

        // assign new editor
        $oPaper->assign($post['editor'], Episciences_User_Assignment::ROLE_EDITOR);

        // send the mail
        $mail = new Episciences_Mail('UTF-8');
        $mail->setDocid($docId);
        $mail->setFromReview();
        $mail->setTo($editor);
        $mail->setSubject($post['subject']);
        $mail->setRawBody(Ccsd_Tools::clear_nl($post['body']));
        $mail->writeMail();
    }

    /**
     * display details of a log (modal)
     */
    public function logAction(): void
    {
        $this->_helper->layout->disableLayout();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = $request->getParam('id');

        $oLog = new Episciences_Paper_Log();
        $oLog->load($id);
        $log = $oLog->toArray();

        $oUser = new Episciences_User();
        $oUser->findWithCAS($oLog->getUid());
        $user = $oUser->toArray();

        $this->view->log = $log;
        $this->view->user = $user;
    }

    /**
     * Affiche tous les comptes qui ont le même prénom et nom
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     */
    public function displayccsdusersAction(): void
    {
        $span = '<span class="glyphicon glyphicon-minus"></span>';
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $users = [];
        $users_stats = [];
        $trace = [];
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {
            $isOwner = false;
            $isSearchWithMail = (boolean)$request->getPost('is_search_with_mail');
            $docId = $request->getPost('paper_id');
            $paper = Episciences_PapersManager::get($docId);

            if (!$paper) {
                $trace['error'] = $this->view->translate('Une erreur est survenue.');
            } else {

                try {

                    $post = json_decode($request->getPost('post'), true, 512, JSON_THROW_ON_ERROR);

                } catch (Exception $e) {
                    trigger_error($e->getMessage(), E_USER_WARNING);
                }

                $user_lang = $request->getPost('user_lang');
                $local_users = Episciences_UsersManager::getLocalUsers();
                // liste des utilisateurs à ignorer
                $ignoreList = $request->getPost('ignore_list');

                try {
                    $ignoreReviewers = ($ignoreList) ? json_decode($ignoreList, false, 512, JSON_THROW_ON_ERROR) : [];

                } catch (Exception $e) {
                    trigger_error($e->getMessage(), E_USER_WARNING);
                }

                /** @var stdClass $value */
                foreach ($post as $value) {
                    $user = new Episciences_User((array)$value);
                    $uid = $user->getUid();

                    $isOwner = ($uid === $paper->getUid());

                    if ($isOwner || in_array($user->getEmail(), EPISCIENCES_IGNORED_EMAILS_WHEN_INVITING_REVIEWER, true)) {
                        $ignoreReviewers[] = $uid;
                    }

                    // Utilisateurs Episciences
                    if (array_key_exists($uid, $local_users)) {
                        $users_stats[$uid]['is_epi_user'] = true;
                        $user->find($uid);
                        $users_stats[$uid]['invitations_nbr'] = Episciences_UserManager::countInvitations($uid)['stats_invitations_nbr'];
                        $users_stats[$uid]['reviewing_complete_nbr'] = Episciences_UserManager::countRatings($uid)['stats_ratings_nbr'];

                    } else {
                        $users_stats[$uid]['is_epi_user'] = false;
                        $user->setLangueid($user_lang);
                        $users_stats[$uid]['invitations_nbr'] = $span;
                        $users_stats[$uid]['reviewing_complete_nbr'] = $span;
                    }

                    $users[$uid] = $user;
                }

                if (!empty($users) && isset($ignoreReviewers)) {
                    $trace['ignore_reviewer'] = true;
                    foreach ($ignoreReviewers as $value) {
                        unset($users[$value]);
                    }
                }

                if (empty($users)) {

                    if (!$isOwner) {
                        $message = $this->view->translate('Une invitation de relecture a été envoyée à cet utilisateur');

                        if (!$isSearchWithMail) {
                            $message .= '(';
                            $message .= $this->view->translate('même nom et même prénom');
                            $message .= ').';
                            $message .= ('<br>');
                            $message .= ' ';
                            $message .= $this->view->translate("Si votre relecteur n'est pas celui détecté par le système");
                            $message .= ', ';
                            $message .= $this->view->translate("continuez avec le nouvel utilisateur que vous venez de saisir.");
                        } else {
                            $message .= ', ';
                            $message .= $this->view->translate("ou bien vous n'avez pas les autorisations nécessaires.");
                        }

                    } else {
                        $message = $this->view->translate('Cet article ne peut pas être relu par son auteur');
                    }

                    $trace['message'] = $message;
                }

            }

            echo $this->view->partial('partials/inviteccsdusers.phtml', [
                'users' => $users, 'users_stat' => $users_stats, 'is_search_with_mail' => $isSearchWithMail, 'trace' => $trace
            ]);

        }
    }

    /**
     * Demande de la mise en forme par la revue
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function waitingforauthorsourcesAction(): void
    {

        $this->checkAction();
    }

    /**
     * @param bool $isCopyEditingAction
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function checkAction(bool $isCopyEditingAction = true): void
    {
        /** @var Zend_Controller_Request_Http $request */

        $request = $this->getRequest();
        $docId = $request->getParam('id');

        $paper = Episciences_PapersManager::get($docId);

        if (!$paper) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti : article introuvable !");
            $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
        }

        if ($request->isPost()) {

            if ($this->applyAction($request, $paper, $isCopyEditingAction)) {
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Vos modifications ont bien été prises en compte');
            } else {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti !");
            }
        }

        $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));

    }

    /**
     * Applique les changements (actions)
     * @param Zend_Controller_Request_Http $request
     * @param Episciences_Paper $paper
     * @param bool $isCopyEditingComment
     * @return bool
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws Exception
     */
    private function applyAction(Zend_Controller_Request_Http $request, Episciences_Paper $paper, bool $isCopyEditingComment = true): bool
    {
        $actionName = $request->getActionName();
        $post = $request->getPost();
        $docId = $paper->getDocid();
        $attachments = [];
        $tags = [];
        $comment = null;

        // Récup. des infos sur l'auteur
        $submitter = new Episciences_User;
        $result = $submitter->findWithCAS($paper->getUid());

        if (null === $result) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti : auteur introuvable !");
            $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $paper->getDocid()]));
            return false;
        }

        if ($actionName === 'waitingforauthorsources') {
            $subject = $post['authorSourcesRequestSubject'];
            $message = $post['authorSourcesRequestMessage'];
            $commentType = Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST;
            $managerTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_EDITOR_COPY;
            $newStatus = Episciences_Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES;

        } elseif ($actionName === 'waitingforauthorformatting') {
            $subject = $post['authorFormattingRequestSubject'];
            $message = $post['authorFormattingRequestMessage'];
            $commentType = Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST;
            $managerTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_EDITOR_AND_COPYEDITOR_COPY;
            $newStatus = Episciences_Paper::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION;

        } elseif ($actionName === 'reviewformattingdeposed') {
            $tags[Episciences_Mail_Tags::TAG_PAPER_REPO_URL] = $paper->getDocUrl();
            $subject = $post['reviewFormattingDeposedSubject'];
            $message = $post['reviewFormattingDeposedMessage'];
            $commentType = Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST;
            $managerTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY;
            $newStatus = Episciences_Paper::STATUS_CE_REVIEW_FORMATTING_DEPOSED;

        } elseif ($actionName === 'copyeditingacceptfinalversion') {
            $tags[Episciences_Mail_Tags::TAG_PAPER_REPO_URL] = $paper->getDocUrl();
            $subject = $post['ceAcceptFinalVersionRequestSubject'];
            $message = $post['ceAcceptFinalVersionRequestMessage'];
            $commentType = Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST;
            $managerTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_COPYEDITOR_AND_EDITOR_COPY;
            $newStatus = Episciences_Paper::STATUS_CE_AUTHOR_FORMATTING_DEPOSED;

        } elseif ($actionName === 'acceptedaskauthorvalidation') {
            $tags[Episciences_Mail_Tags::TAG_PAPER_REPO_URL] = $paper->getDocUrl();
            $subject = $post['acceptedAskAuthorValidationSubject'];
            $message = $post['acceptedAskAuthorValidationMessage'];
            $commentType = Episciences_CommentsManager::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION;
            $managerTemplateType = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_EDITOR_AND_COPYEDITOR_COPY;
            $newStatus = Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION;

        } elseif ($actionName === 'approvedwaitingforfinalpublication') {
            $subject = null;
            $message = null;
            $commentType = null;
            $managerTemplateType = null;
            $newStatus = Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION;

        } else {
            return false;
        }


        if ($commentType) {

            $comment = new Episciences_Comment([
                'docid' => $docId,
                'uid' => Episciences_Auth::getUid(),
                'message' => $message,
                'type' => $commentType
            ]);

            $comment->setCopyEditingComment($isCopyEditingComment);

        }

        if (!empty($post[Episciences_Mail_Send::ATTACHMENTS])) {
            // Errors : si une erreur s'est produite lors de la validation d'un fichier attaché par exemple(voir es.fileupload.js)
            $attachments = Episciences_Tools::arrayFilterEmptyValues($post[Episciences_Mail_Send::ATTACHMENTS]);

            if ($comment) {
                try {

                    $comment->setFile(json_encode($attachments, JSON_THROW_ON_ERROR));

                } catch (JsonException $e) {

                    trigger_error($e->getMessage());

                }
            }
        }

        // Les mêmes fichiers attachés dans l'email envoyé aux rédacteurs et préparateurs de copie

        $authorAttachments = [];

        $source = Episciences_Tools::getAttachmentsPath();


        foreach ($attachments as $file) {
            $authorAttachments[$file] = $source;
        }


        if ($comment && $comment->save()) {

            // Envoi de mail à l'auteur
            $this->sendMailFromModal($submitter, $paper, $subject, $message, $post, $tags);

            if ($comment->isCopyEditingComment()) {

                $comment->logComment();

                $path = Episciences_PapersManager::buildDocumentPath($docId);
                $path .= DIRECTORY_SEPARATOR;
                $path .= Episciences_CommentsManager::COPY_EDITING_SOURCES;
                $path .= DIRECTORY_SEPARATOR;
                $path .= $comment->getPcid();
                $path .= DIRECTORY_SEPARATOR;
                //$source = REVIEW_FILES_PATH . Episciences_Mail_Send::ATTACHMENTS . DIRECTORY_SEPARATOR;
                $comment->setFilePath($path);
                $source = Episciences_Tools::getAttachmentsPath();

                Episciences_Tools::cpFiles($attachments, $source, $path);

            }

        }

        // if needed, set new status
        if ($paper->getStatus() !== $newStatus) {
            $paper->setStatus($newStatus);
            $paper->save();
            // log status change
            $paper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), ['status' => $paper->getStatus()]);

            // Envoi de mails aux rédacteurs + préparateurs de copie de l'article + notifier les rédacteurs en chef, secrétaires de rédaction et administrateurs, si le bon paramétrage a été choisi.
            $this->paperStatusChangedNotifyManagers($paper, $managerTemplateType, Episciences_Auth::getUser(), $tags, $authorAttachments);
        }

        return true;
    }

    /**
     * Demande de la mise en forme par l'auteur
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws JsonException
     */
    public function waitingforauthorformattingAction(): void
    {
        $this->checkAction();
    }

    /**
     * Depôt de la mise en forme par la revue
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws JsonException
     */
    public function reviewformattingdeposedAction(): void
    {
        $this->checkAction();
    }

    /**
     * Copy editing: Valider la version finale
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     * @throws JsonException
     */
    public function copyeditingacceptfinalversionAction(): void
    {
        $this->checkAction();
    }

    /**
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Form_Exception
     */
    public function refusedmonitoringformAction(): void
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docId');
        $paper = Episciences_PapersManager::get($docId, false);
        $html = '';

        if ($paper && array_key_exists($request->get('uid'), $paper->getEditors())) {
            $html = Episciences_EditorsManager::getRefusedMonitoringForm($docId);
        }

        echo $html;
    }

    /**
     *
     */
    public function saverefusedmonitoringAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getQuery('id'); // params en GET

        $papersManager = new Episciences_PapersManager;
        $paper = $papersManager::get($docId);

        if (!$paper) {
            trigger_error('SAVE_REFUSE_MANAGING_FAILED_BECAUSE_DOCID_' . $docId . 'NOT_EXIST');
            return;
        }

        if ($request->isPost()) {

            $post = $request->getPost();
            if (array_key_exists('confirm_refused_monitoring', $post)) {

                try {
                    if ($this->applyEditorRefusedMonitoring($paper, $post['refused_monitoring_comment'])) {
                        $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Vos modifications ont bien été prises en compte');
                        $this->_helper->redirector->gotoUrl($this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'view' => self::ACTION_ASSIGNED]));
                    }

                } catch (Exception $e) {
                    Ccsd_Log::message($e->getMessage(), false, Zend_Log::WARN, EPISCIENCES_EXCEPTIONS_LOG_PATH . RVCODE . 'exceptions.log');
                }

            }
        }
    }

    /**
     * @param Episciences_Paper $paper
     * @param string $message
     * @return bool
     * @throws JsonException
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     */
    private function applyEditorRefusedMonitoring(Episciences_Paper $paper, string $message): bool
    {
        $docId = $paper->getDocid();
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        // On enregistre le commentaire en base
        $oComment = new Episciences_Comment();
        $oComment->setType(Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED);
        $oComment->setDocid($docId);
        $oComment->setMessage($message);
        $oComment->save();

        $tags = [
            Episciences_Mail_Tags::TAG_COMMENT => $oComment->getMessage(),
            Episciences_Mail_Tags::TAG_EDITOR_SCREEN_NAME => Episciences_Auth::getScreenName(),
            Episciences_Mail_Tags::TAG_EDITOR_FULL_NAME => Episciences_Auth::getFullName()
        ];

        $uidS = $this->unssignUser($paper, [Episciences_Auth::getUid()], $this->publicPaperUrl($docId));
        $this->notifyManagers($paper, Episciences_Mail_TemplatesManager::TYPE_PAPER_EDITOR_REFUSED_MONITORING, null, $tags, [], false, $uidS);

        return true;
    }

    /**
     * display paper volumes (ajax display refresh)
     * @throws Zend_Exception
     */
    public function refreshvolumesAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $paper = Episciences_PapersManager::get($request->getPost('docId'));
        $isPartial = filter_var($request->getPost('isPartial'), FILTER_VALIDATE_BOOLEAN);


        $isAllowed = (
            (
                Episciences_Auth::isSecretary() ||
                $paper->getEditor(Episciences_Auth::getUid())
            ) &&
            Episciences_Auth::getUid() !== $paper->getUid()
        );

        if (!$isAllowed) {
            $disabled = 'disabled';
            $ariaDisabled = 'aria-disabled="true"';
        } else {
            $disabled = '';
            $ariaDisabled = 'aria-disabled="false"';
        }

        echo $this->view->partial('partials/paper_volumes.phtml', [
                'article' => $paper,
                'volumes' => Episciences_ReviewsManager::find(RVID)->getVolumes(),
                'isPartial' => $isPartial,
                'disabled' => $disabled,
                'ariaDisabled' => $ariaDisabled
            ]
        );

    }

    /**
     * refresh all master volumes and positions
     * @throws Zend_Exception
     */
    public function refreshallmastervolumesAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest() && $request->isPost()) {
            $none = Zend_Registry::get('Zend_Translate')->translate('aucun');
            $referer = $request->getPost('referer');
            $currentVid = (int)$request->getPost('vid');
            $currentVolume = Episciences_VolumesManager::find($currentVid);
            $oldVid = (int)$request->getPost('old_vid');
            $oldVolume = Episciences_VolumesManager::find($oldVid);
            $currentDocId = $request->getPost('docid');
            $result = []; // [docId => volume name and position (html)]

            if ($oldVolume) {
                foreach ($oldVolume->getPaperPositions() as $position => $docId) {
                    $htmlPosition = $this->view->partial('partials/paper_volume_position.phtml', [
                        'docId' => $docId,
                        'vid' => $oldVid,
                        'position' => $position,
                        'referer' => $referer
                    ]);

                    $result[$docId] = $oldVolume->getName() . $htmlPosition;
                }
            }

            if ($currentVolume) {
                /**
                 * @var  $docId
                 * @var  Episciences_Paper $paper
                 */
                foreach ($currentVolume->getPaperPositions() as $position => $docId) {
                    $htmlPosition = $this->view->partial('partials/paper_volume_position.phtml', [
                        'docId' => $docId,
                        'vid' => $currentVid,
                        'position' => $position,
                        'referer' => $referer
                    ]);
                    $result[$docId] = $currentVolume->getName() . $htmlPosition;
                }
            } else {
                $result[$currentDocId] = $none;
            }

            echo json_encode($result);
        }
    }

    /**
     *  edit publication date form (ajax)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function publicationdateformAction(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');
        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);

        if ($paper->isImported()) {
            return false;
        }

        $this->view->docId = $paper->getDocid();
        $this->view->publicationDate = date('Y-m-d', strtotime($paper->getPublication_date()));
        $this->view->acceptanceDate = date('Y-m-d', strtotime($paper->getAcceptanceDate()));

        $this->_helper->layout->disableLayout();
        $this->renderScript(self::ADMINISTRATE_PAPER_CONTROLLER . '/edit-publication-date-form.phtml');
        return true;

    }

    /**
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function savepublicationdateAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $docId = (int)(($request->getPost('docid')) ?: $request->getParam('docid'));

        $paper = Episciences_PapersManager::get($docId);

        if (!$paper || $paper->isImported()) {
            echo false;
            return;
        }

        if ($request->isPost() && $request->isXmlHttpRequest()) {

            $oldDate = $paper->getPublication_date();

            $newPublicationDate = $request->getPost('publication-date-value-' . $docId);

            if (
                Episciences_Auth::isSecretary() &&
                (DateTime::createFromFormat('Y-m-d', $newPublicationDate) !== false) && // it's a date ?
                ($newPublicationDate <= date('Y-m-d') && $newPublicationDate >= date('Y-m-d', strtotime($paper->getAcceptanceDate())))
            ) {
                {
                    $local = Episciences_Tools::getLocale();
                    $localDate = Episciences_View_Helper_Date::Date($newPublicationDate, $local);

                    if ($newPublicationDate !== date('Y-m-d', strtotime($oldDate))) {
                        $paper->setPublication_date($newPublicationDate);
                        $paper->save();
                        $status = $paper->getStatus();

                        // Update DATE in PAPER_LOGS

                        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                        $sql = 'UPDATE PAPER_LOG pl SET pl.DATE = ? WHERE pl.DOCID = ? AND pl.status = ?';
                        $stm = $db?->prepare($sql);

                        try {
                            $stm->execute([$newPublicationDate, $docId, $status]);
                        } catch (Exception $e) {
                            Episciences_View_Helper_Log::log($e->getMessage(), Psr\Log\LogLevel::CRITICAL);
                        }

                        $details = ['user' => ['uid' => Episciences_Auth::getUid(), 'fullname' => Episciences_Auth::getFullName()], 'oldDate' => Episciences_View_Helper_Date::Date($oldDate, $local), 'newDate' => $localDate];
                        $paper->log(Episciences_Paper_Logger::CODE_ALTER_PUBLICATION_DATE, Episciences_Auth::getUid(), $details);

                        $this->indexAndCOARNotify($paper);
                    }

                    echo $localDate;
                }

            }
        }
    }

    /**
     * Ask author's final version
     * @return void
     */
    public function acceptedaskauhorfinalversionAction(): void
    {
        try {
            $this->revisionAction();

        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);

        }

    }

    /**
     * revision request (can be minor or major)
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function revisionAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getParam('id');
        $type = $request->getParam('type');

        $allowedTypes = ['minor', 'major', 'acceptedAskAuthorsFinalVersion'];

        if (!in_array($type, $allowedTypes, true)) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti : type incorrect !");
            $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
            return;
        }

        $isMinorRevision = ($type === 'minor');
        $isMajorRevision = ($type === 'major');
        $isAcceptedAskAuthorsFinalVersion = ($type === 'acceptedAskAuthorsFinalVersion');

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $paper = Episciences_PapersManager::get($docId);

        // check that paper exists
        if (!$paper) {
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti : article introuvable !");
            $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
        }

        // check permissions
        $this->checkPermissions($review, $paper);

        if ($request->isPost()) {

            $csrfName = 'csrf_revision_' . $type . '_' . (int)$docId;
            $csrfSession = new Zend_Session_Namespace('Zend_Form_Element_Hash_unique_' . $csrfName);
            $post = $request->getPost();
            if (!isset($post[$csrfName], $csrfSession->hash) || $post[$csrfName] !== $csrfSession->hash) {
                $csrfSession->hash = null;
                $this->_helper->redirector->gotoUrl($this->_helper->url('view', self::ADMINISTRATE_PAPER_CONTROLLER, null, ['id' => $docId]));
                return;
            }
            $csrfSession->hash = null;

            $data = $request->getPost();

            // Récupération des infos sur l'auteur
            $submitter = new Episciences_User;
            $result = $submitter->findWithCAS($paper->getUid());

            // check that submitter exists
            if (null === $result) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti : auteur introuvable !");
                $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
            }

            $locale = $submitter->getLangueid();

            $subject = $data[$type . 'revisionsubject'];
            $message = $data[$type . 'revisionmessage'];
            $deadline = $data[$type . 'revisiondeadline'] ?: null;

            if (!$deadline) {
                if (
                    $review->getSetting(Episciences_Review::SETTING_TO_REQUIRE_REVISION_DEADLINE)) {
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("Les modifications n'ont pas abouti : la demande de révision n'est pas assortie d'un délai !");
                    $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
                    return;

                }

            } elseif (!Ccsd_Tools_String::validateDate($deadline)) {
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage("La date limite de révision n'est pas valide : Veuillez saisir une date limite de révision au format : AAAA-mm-jj.");
                $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
                return;

            }


            $isAlreadyAccepted = $review->getSetting(Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION) &&
                in_array($paper->getStatus(), Episciences_Paper::ACCEPTED_SUBMISSIONS, true);

            // prepare comment options
            $options = [];
            if ($deadline) {
                $options['deadline'] = $deadline;
            }

            if (array_key_exists('auto_reassign', $data)) {
                $options['reassign_reviewers'] = (bool)$data['auto_reassign'];
            }

            $options['isAlreadyAccepted'] = $isAlreadyAccepted;

            // save comment (revision request)
            $comment = new Episciences_Comment([
                'docid' => $docId,
                'uid' => Episciences_Auth::getUid(),
                'message' => $message,
                'type' => Episciences_CommentsManager::TYPE_REVISION_REQUEST,
                'deadline' => $deadline,
                'options' => $options

            ]);

            $comment->save();

            if ($isMinorRevision) {
                $actionLog = Episciences_Paper_Logger::CODE_MINOR_REVISION_REQUEST;
            } elseif ($isMajorRevision) {
                $actionLog = Episciences_Paper_Logger::CODE_MAJOR_REVISION_REQUEST;
            } elseif ($isAcceptedAskAuthorsFinalVersion) {
                $actionLog = Episciences_Paper_Logger::CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION;
            } else {
                $actionLog = 'undefined';
            }

            // log revision request
            $paper->log($actionLog, Episciences_Auth::getUid(), [
                'id' => $comment->getPcid(),
                'deadline' => $deadline,
                'subject' => $subject,
                'message' => $message,
                'isAlreadyAccepted' => $isAlreadyAccepted,
                'user' => Episciences_Auth::getUser()->toArray()
            ]);

            // sends an e-mail to the author
            $tags = [
                Episciences_Mail_Tags::TAG_REVISION_DEADLINE =>
                    !empty($deadline) ? Episciences_View_Helper_Date::Date($deadline, $locale) : Zend_Registry::get('Zend_Translate')->translate('dès que possible', $locale)
            ];
            $this->sendMailFromModal($submitter, $paper, $subject, $message, $data, $tags);

            //Demande de modifications, les relecteurs devraient être notifiés (leurs éviter de poursuivre un travail inutile).
            $this->paperStatusChangedNotifyReviewer($paper, Episciences_Mail_TemplatesManager::TYPE_REVIEWER_PAPER_REVISION_REQUEST_STOP_PENDING_REVIEWING);

            // if needed, set new status

            if (!$isAlreadyAccepted) {
                $status = ($isMajorRevision) ? Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION : Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION;
            } else {

                $status = ($isMajorRevision) ? Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION : Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION;

                if ($paper->isTmp()) {
                    $status = ($isMajorRevision) ? Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION : Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION;
                }
            }

            if ($paper->getStatus() !== $status) {
                $paper->setStatus($status);
                $paper->save();
                // log status change
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, null, ['status' => $paper->getStatus()]);
            }

            $this->_helper->FlashMessenger->setNamespace('success')->addMessage('Vos modifications ont bien été prises en compte');
        }

        $this->_helper->redirector->gotoUrl($this->url(['action' => 'view', 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER, 'id' => $docId]));
    }

    /**
     * Ask author's validation
     * @return void
     */
    public function acceptedaskauthorvalidationAction(): void
    {
        try {
            $this->checkAction();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
    }

    /**
     * @return void
     * @deprecated
     * Final version approved by author, waiting for final publication
     */
    public function approvedwaitingforfinalpublicationAction(): void
    {
        try {
            $this->checkAction(false);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

    }

    /**
     * edit latest version form (ajax)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function latestversioneditingformAction(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = $request->getPost('docid');

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper) {
            return false;
        }

        $availableVersions = $this->availableRepositoryVersions($paper);

        foreach ($availableVersions as $index => $value) {

            if ((float)$value > $paper->getVersion()) {
                continue;
            }

            unset($availableVersions[$index]);
        }

        $vString = "version la plus récente dans l’archive ouverte";
        $hasHook = $paper->hasHook; // zenodo repository
        $this->view->hasHook = $hasHook;
        $this->view->label = $paper->getRepoid() === (int)Episciences_Repositories::ZENODO_REPO_ID ? ("L'identifiant de la " . $vString) : ('La ' . $vString);
        $this->view->type = 'select';
        $this->view->options = $availableVersions;
        $this->view->docId = $paper->getDocid();
        $this->view->latestversion = $paper->getVersion();
        $this->view->action = $this->url(['controller' => PaperDefaultController::ADMINISTRATE_PAPER_CONTROLLER, 'action' => 'savenewpostedversion']);
        $this->view->prefix = 'latest-repository-version';
        $this->view->isPublished = $paper->isPublished();

        $this->_helper->layout->disableLayout();
        $this->renderScript(self::ADMINISTRATE_PAPER_CONTROLLER . '/edit-version-numbers-form.phtml');
        return true;
    }

    private function availableRepositoryVersions(Episciences_Paper $paper): array
    {
        $versions = [];
        $repoId = $paper->getRepoid();

        $api = Episciences_Repositories::getApiUrl($paper->getRepoid());

        if ('' !== $api) {

            if (Episciences_Repositories::isFromHalRepository($repoId)) {

                $url = $api . '/search/?indent=true&q=' . $paper->getIdentifier() . '&fl=label_xml';

                $result = Episciences_Tools::callApi($url);

                if ($result && is_array($result)) {
                    $xml = $result['response']['docs'][array_key_first($result['response']['docs'])]['label_xml'] ?? '';

                    if ('' !== $xml) {

                        $xmlObject = simplexml_load_string($xml);

                        if ($xmlObject) {

                            $editions = $xmlObject->text->body->listBibl->biblFull->editionStmt->edition;

                            foreach ($editions as $edition) {

                                $versions[] = substr($edition['n'][0], 1);

                            }
                        }

                    }
                }

            } elseif ((int)Episciences_Repositories::ZENODO_REPO_ID === $repoId) {

                $dataCiteUrl = 'https://api.datacite.org/dois/';
                $dataCiteUrl .= Episciences_Repositories::getRepoDoiPrefix($repoId);
                $dataCiteUrl .= '/';
                $dataCiteUrl .= mb_strtolower(Episciences_Repositories::getLabel($repoId));
                $dataCiteUrl .= '.';

                $conceptIdentifierUrl = $dataCiteUrl . $paper->getConcept_identifier();
                $responseWithConceptId = Episciences_Tools::callApi($conceptIdentifierUrl);

                $doisVersions = $responseWithConceptId['data']['relationships']['versions']['data'];

                foreach ($doisVersions as $index => $value) {

                    $cleanedIdentifier = Episciences_Repositories_Zenodo_Hooks::hookCleanIdentifiers(['id' => $value['id'], 'repoId' => $repoId])['identifier'];

                    if ($cleanedIdentifier > $paper->getIdentifier()) {
                        $versions[$index + 1] = $cleanedIdentifier;
                    }
                }

            } elseif (
                $repoId === (int)Episciences_Repositories::BIO_RXIV_ID ||
                $repoId === (int)Episciences_Repositories::MED_RXIV_ID
            ) {
                $url = $api . $paper->getIdentifier() . DIRECTORY_SEPARATOR . 'na' . DIRECTORY_SEPARATOR . 'json';

                $response = Episciences_Tools::callApi($url);
                $messages = $response['messages'][array_key_first($response['messages'])];
                $collection = $response['collection'];

                if (
                    isset($messages['status']) &&
                    $messages['status'] === Episciences_Repositories_BioMedRxiv::SUCCESS_CODE
                ) {
                    foreach ($collection as $index => $values) {
                        $versions[$index + 1] = $values['version'];

                    }
                }
            } elseif (Episciences_Repositories::isDataverse($repoId)) {
                $url = $api;
                $url .= 'datasets/:persistentId/?persistentId=';
                $url .= $paper->getIdentifier();
                $response = Episciences_Tools::callApi($url);

                if (
                    isset($response['status']) &&
                    mb_strtolower($response['status']) === Episciences_Repositories_Dataverse_Hooks::SUCCESS_CODE
                ) {

                    $latestVersion = $response['data']['latestVersion']['versionNumber'] ?? 1;
                    $versionMinorNumber = $response['data']['latestVersion']['versionMinorNumber'] ?? 0;

                    $version = (float)($latestVersion . '.' . $versionMinorNumber);

                    while ($version > 0) {
                        $versions[] = $version . '.' . $versionMinorNumber;
                        $version -= 1.0;
                    }
                }
            }

        } else {

            $identifier = Episciences_Repositories::getIdentifier($paper->getRepoid(), $paper->getIdentifier());
            $baseUrl = Episciences_Repositories::getBaseUrl($paper->getRepoid());
            $oai = new Episciences_Oai_Client($baseUrl, 'xml');
            if ((int)Episciences_Repositories::ARXIV_REPO_ID === $repoId) {
                try {
                    $versions = Episciences_Submit::extractVersionsFromArXivRaw($oai->getArXivRawRecord($identifier));
                } catch (Exception $e) {
                    trigger_error($e->getMessage());
                }
            }

        }

        arsort($versions);
        return $versions;
    }

    /**
     * Update paper version
     * @return false|void
     * @throws Exception
     */

    public function savenewpostedversionAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();

        $latestPostedVersion = isset($post['latest-repository-version']) ? (float)$post['latest-repository-version'] : 0; // version or identifier

        if (!$latestPostedVersion) {
            return false;
        }

        $isReadyToPublish = isset($post['ready-to-publish']) && $post['ready-to-publish'] === 'on';


        $docId = (int)$request->getPost('docid');

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper) {
            return false;
        }

        if (
            !$paper->isEditableVersion() ||
            (
                !Episciences_Auth::isSecretary() &&
                !$paper->getEditor(Episciences_Auth::getUid()) &&
                !$paper->getCopyEditor(Episciences_Auth::getUid())
            )
        ) {
            return false;
        }

        $hookedVersion = Episciences_Repositories::callHook('hookVersion', ['identifier' => $latestPostedVersion, 'repoId' => $paper->getRepoid()]);

        if (isset($hookedVersion['version'])) {
            $paper->setIdentifier($latestPostedVersion); // posted identifier
            $latestPostedVersion = (float)$hookedVersion['version'];
        }

        $currentVersion = $paper->getVersion();

        $result = ['version' => 0, 'isDataRecordUpdated' => false];

        if ($latestPostedVersion > $currentVersion) {

            $paper->setVersion($latestPostedVersion);
            $currentStatus = $paper->getStatus();

            if ($isReadyToPublish && $paper->isEditable()) {
                $paper->setStatus(Episciences_Paper::STATUS_CE_READY_TO_PUBLISH);
            }

            if ($paper->save()) {
                $paper->log(Episciences_Paper_Logger::CODE_VERSION_REPOSITORY_UPDATED, Episciences_Auth::getUid(), ['user' => Episciences_Auth::getUser()->toArray(), 'version' => ['old' => $currentVersion, 'new' => $latestPostedVersion]]);
                if ($isReadyToPublish && $paper->getStatus() !== $currentStatus) {
                    $paper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), ['status' => $paper->getStatus()]);
                }
                $result['version'] = $latestPostedVersion;
                $result['isDataRecordUpdated'] = Episciences_PapersManager::updateRecordData($paper) > 0;
            }

        }

        try {
            echo json_encode($result, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
        }

    }

    /**
     * Cancel a pending DOI assignment request.
     *
     * Deletes the DOI queue entry for the paper, clears its DOI field,
     * and logs the cancellation.
     *
     * @return void
     */
    public function ajaxrequestremovedoiAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost() || !$request->isXmlHttpRequest()) {
            return;
        }

        if (!Episciences_Auth::isLogged() || !Episciences_Auth::isAllowedToManageDoi()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
            trigger_error(sprintf('Unauthorized access to ajaxrequestremovedoi by uid=%s', Episciences_Auth::getUid()), E_USER_WARNING);
            return;
        }

        $paperId = (int) $request->getPost('paperId');
        $docId   = (int) $request->getPost('docId');

        if ($paperId <= 0 || $docId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid document ID']);
            return;
        }

        if (!Episciences_PapersManager::paperExists($docId, RVID)) {
            echo json_encode(['success' => false, 'error' => $this->view->translate('Document non trouvé')]);
            trigger_error(sprintf('Docid %d not found in RVID %s', $docId, RVID), E_USER_WARNING);
            return;
        }

        $doiQueue = Episciences_Paper_DoiQueueManager::findByPaperId($paperId);
        if ($doiQueue->getId_doi_queue() <= 0) {
            echo json_encode(['success' => false, 'error' => 'No pending DOI request found']);
            return;
        }

        if (!Episciences_Paper_DoiQueueManager::delete($paperId)) {
            echo json_encode(['success' => false, 'error' => 'Failed to cancel DOI request']);
            return;
        }

        // Clear the DOI on the paper. updateDoi returning 0 is not an error here:
        // it means the DOI field was already empty (idempotent operation).
        Episciences_PapersManager::updateDoi('', $paperId);

        $doi = trim((string) $request->getPost('doi'));
        Episciences_Paper_Logger::log(
            $paperId,
            $docId,
            Episciences_Paper_Logger::CODE_DOI_CANCELED,
            Episciences_Auth::getUid(),
            json_encode(['DOI' => $doi . ' canceled']),
            null,
            RVID
        );

        echo json_encode(['success' => true, 'error' => '']);
    }

    public function addcoauthorAction()
    {
        if (!Episciences_Auth::isAdministrator() && !Episciences_Auth::isSecretary() && !Episciences_Auth::isEditor()) {
            return;
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $selectedUserId = (int)$request->getPost('selectedUserId');


        // create an episciences account from a CAS account
        if ($selectedUserId) {

            $user = new Episciences_User();

            $docId = (int)$request->getPost('docId');
            if ($user->hasLocalData($selectedUserId) && $user->hasRoles($selectedUserId)) {
                $addcoAuthor = $this->addRoleCoAuthor($docId, $selectedUserId);
                if ($addcoAuthor) {
                    $message = Zend_Registry::get('Zend_Translate')->translate("Utilisateur ajouté en tant que co-auteur");
                } else {
                    $message = Zend_Registry::get('Zend_Translate')->translate("L'utilisateur est déjà co-auteur de ce document");
                }
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
            } else {
                // Récupération des données CAS
                $casUserMapper = new Ccsd_User_Models_UserMapper();
                $casUserMapper->find($selectedUserId, $user);
                $user->setScreenName();
                $user->setIs_valid();
                $user->setRegistrationDate();
                $user->setModificationDate();
                $screenName = $user->getScreenName();

                if ($user->save()) {
                    $success = Zend_Registry::get('Zend_Translate')->translate("L'utilisateur <strong>%%RECIPIENT_SCREEN_NAME%%</strong> a bien été ajouté en tant que co-auteur du document");
                    $success = str_replace('%%RECIPIENT_SCREEN_NAME%%', $screenName, $success);
                    $addcoAuthor = $this->addRoleCoAuthor($docId, $selectedUserId);
                    if (!$addcoAuthor) {
                        $success .= ' ' . Zend_Registry::get('Zend_Translate')->translate("L'utilisateur est déjà co-auteur de ce document");
                    }
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($success);
                } else {
                    $error = "L'utilisateur <strong>$screenName</strong> n'a pu être ajouté aux coauteur";
                    $error = str_replace('%%RECIPIENT_SCREEN_NAME%%', $screenName, $error);
                    $this->_helper->FlashMessenger->setNamespace('error')->addMessage($error);
                }
            }
            $this->_helper->redirector->gotoUrl($this->url(['controller' => self::ADMINISTRATE_PAPER_CONTROLLER , 'action' => 'view', 'id' => $docId]));
        }
    }

    private function addRoleCoAuthor(int $docId, int $uid)
    {
        $exist = Episciences_User_AssignmentsManager::find(['RVID' => RVID, "ITEMID" => $docId, "UID" => $uid]);
        if (!$exist) {
            $assignment = new Episciences_User_Assignment();
            $assignment->setRvid(RVID);
            $assignment->setItemid($docId);
            $assignment->setItem('paper');
            $assignment->setUid($uid);
            $assignment->setRoleid(Episciences_Acl::ROLE_CO_AUTHOR);
            $assignment->setStatus(Episciences_User_Assignment::STATUS_ACTIVE);
            return $assignment->save();
        }
        return false;
    }

    /**
     * @return void
     */
    public function ajaxrequestremovecoauthorAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $post = $request->getPost();
        if ($request->isXmlHttpRequest()
            && isset($post['docId'])
            && isset($post['rvid'])
            && isset($post['uid'])
            && (int)$post['rvid'] === RVID
            && Episciences_Auth::isAdministrator()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $getUserAssignment = Episciences_User_AssignmentsManager::find(['RVID' => RVID, "ITEMID" => (int)$post['docId'], "UID" => (int)$post['uid']]);
            if ($getUserAssignment->getId() !== 0 && ($getUserAssignment->getRoleid() === Episciences_Acl::ROLE_CO_AUTHOR)) {
                $row = Episciences_User_AssignmentsManager::removeAssignment($getUserAssignment->getId());
                if ($row > 0) {
                    $message = Zend_Registry::get('Zend_Translate')->translate("Co-auteur retiré");
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                    echo json_encode($row, JSON_THROW_ON_ERROR);
                    exit;
                }
            }
        } else {
            $message = "Une erreur est survenue.";
            $this->_helper->FlashMessenger->setNamespace('error')->addMessage($message);
        }
        echo json_encode(0, JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * @param int $docId
     * @return Ccsd_Form|null
     * @throws Zend_Form_Exception
     * @deprecated
     */
    private function getApprovedForm(int $docId): ?Ccsd_Form
    {

        try {
            $journalSettings = Zend_Registry::get('reviewSettings');

            if (
                (
                    !isset($journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION]) ||
                    (int)$journalSettings[Episciences_Review::SETTING_SYSTEM_PAPER_FINAL_DECISION_ALLOW_REVISION] === 0
                ) &&
                !Episciences_Auth::isSecretary() && !Episciences_Auth::isCopyEditor()
            ) {
                return null;
            }
        } catch (Exception $e) {
            trigger_error($e->getMessage());
            return null;
        }

        return Episciences_PapersManager::getApprovedForm($docId);
    }

    /**
     *  get revision deadline edit form (ajax)
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function revisiondeadlineformAction(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $docId = $request->getPost('docid');

        if (!$docId) {
            return false;
        }

        $paper = Episciences_PapersManager::get($docId);

        if (!$paper->isRevisionRequested()) {
            return false;
        }

        $paper->setRevisionDeadline();

        $this->view->paper = $paper;

        $this->_helper->layout->disableLayout();
        $this->renderScript(self::ADMINISTRATE_PAPER_CONTROLLER . '/edit-revision-deadline-form.phtml');
        return true;

    }


    /**
     * /**
     * @return void
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     */
    public function updaterevisiondeadlineAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $docId = ($request->getPost('docid')) ?: $request->getParam('docid');

        $paper = Episciences_PapersManager::get($docId);


        if (
            !$paper ||
            !$paper->isRevisionRequested() ||
            !(
                Episciences_Auth::isSecretary() ||
                $paper->isEditor(Episciences_Auth::getUid())
            )
        ) {
            echo false;
            return;
        }

        if ($request->isPost() && $request->isXmlHttpRequest()) {


            $current = date($paper->_revisionDeadline ?: 'Y-m-d');

            $maxDate = Episciences_Tools::addDateInterval($current, Episciences_Review::DEFAULT_REVISION_DEADLINE_MAX);

            $next = $request->getPost('revision-deadline-value-' . $docId);

            if (
                $next >= $current &&
                $next <= $maxDate &&
                (DateTime::createFromFormat('Y-m-d', $next) !== false) // it's a date ?

            ) {
                {
                    $local = Episciences_Tools::getLocale();

                    $localDate = Episciences_View_Helper_Date::Date($next, $local);


                    if ($next !== $current) {

                        /** @var Episciences_Comment $demand */
                        $oDemand = new Episciences_Comment(Episciences_CommentsManager::getComment((int)$request->getPost('pcid')));
                        $oDemand->setDeadline($next);
                        $oDemand->setOption('deadline', $oDemand->getDeadline());

                        $oDemand->save();

                        $paper->_revisionDeadline = $next;


                        $details = [
                            'user' => [
                                'uid' => Episciences_Auth::getUid(), 'fullname' => Episciences_Auth::getFullName()
                            ],
                            'oldDate' => $current,
                            'newDate' => $next
                        ];

                        $paper->log(Episciences_Paper_Logger::CODE_REVISION_DEADLINE_UPDATED, Episciences_Auth::getUid(), $details);

                    }

                    echo $localDate;
                }

            }
        }
    }
}
