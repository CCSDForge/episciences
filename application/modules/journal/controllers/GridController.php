<?php

use Episciences\Reviewer\RequestTypeRepository;

class GridController extends Zend_Controller_Action
{
    // beyond this number of volume grids, they are listed collapsed: only their titles are shown
    public const VOLUME_GRIDS_SHOWN = 5;

    /**
     * File of a grid from its key: a volume id (0: default grid) or "t<request type id>" for the grid of a review
     * request type (#319); an invalid key gives a file name that never exists
     */
    private static function gridFilename(mixed $rgid): string
    {
        $key = Episciences_GridsManager::gridKey($rgid);

        return $key === null ? 'grid_invalid.xml' : Episciences_GridsManager::filenameOf($key);
    }


    public function indexAction()
    {
        $this->_helper->redirector('list', 'grid');
    }

    /**
     * Review settings page: the default grid, one block per review request type (#319) with the grid it uses, then
     * the volume grids
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws JsonException
     */
    public function listAction(): void
    {
        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');

        $grids = Episciences_GridsManager::getList();
        $this->view->grids = $grids;
        $this->view->form = Episciences_GridsManager::getGridForm($grids);

        // the request types are managed by the same roles as on the former request types page
        $canManageTypes = Episciences_Auth::isSecretary();
        $repository = new RequestTypeRepository();
        $requestTypes = [];
        foreach ($this->journalRequestTypes($repository, $canManageTypes) as $requestType) {
            $requestTypes[(int)$requestType->getId()] = $requestType;
        }

        $gridTypeIds = [];
        foreach (array_keys($requestTypes) as $requestTypeId) {
            if (array_key_exists('grid_' . Episciences_GridsManager::requestTypeGridKey($requestTypeId), $grids)) {
                $gridTypeIds[$requestTypeId] = true;
            }
        }

        $this->view->requestTypes = $requestTypes;
        // types having their own grid
        $this->view->requestTypeGridIds = $gridTypeIds;
        $this->view->canManageTypes = $canManageTypes;
        $this->view->usedRequestTypes = $canManageTypes ? $this->usedRequestTypes($repository, array_keys($requestTypes)) : [];
        $this->view->csrfToken = Episciences_Csrf_Helper::getSessionToken();
        $this->view->locale = Episciences_Tools::getLocale();
        $this->view->translator = Zend_Registry::get('Zend_Translate');
    }

    /**
     * Request types of the journal; the default ones are created on the first visit of a user managing them
     * @return list<\Episciences\Reviewer\RequestType>
     * @throws JsonException
     */
    private function journalRequestTypes(RequestTypeRepository $repository, bool $canManageTypes): array
    {
        $types = $repository->findAllForJournal(RVID);

        if ($types === [] && $canManageTypes) {
            $repository->createDefaults(RVID);
            $types = $repository->findAllForJournal(RVID);
        }

        return $types;
    }

    /**
     * @param list<int> $requestTypeIds
     * @return array<int, bool> whether each type is used by an invitation (a used type cannot be deleted)
     */
    private function usedRequestTypes(RequestTypeRepository $repository, array $requestTypeIds): array
    {
        $used = [];
        foreach ($requestTypeIds as $requestTypeId) {
            $used[$requestTypeId] = $repository->isUsed($requestTypeId);
        }

        return $used;
    }

    /**
     * Visualiser une grille de relecture
     * @throws Zend_Form_Exception
     */
    public function viewAction()
    {
        $request = $this->getRequest();
        $rgid = $request->getParam('rgid');
        $ajax = $request->getParam('ajax');

        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');
        if ($ajax) {
            // $this->_helper->viewRenderer->setNoRender();
            $this->_helper->getHelper('layout')->disableLayout();
        }

        $oGrid = new Episciences_Rating_Report();
        $oGrid->loadXML(REVIEW_GRIDS_PATH . self::gridFilename($rgid));

        $form = Episciences_Rating_Manager::getRatingForm($oGrid);

        if ($form) {
            $form->removeElement('submitRatingForm');
            $form->removeElement('validateRating');
            $this->view->form = $form;
        }

    }

    /**
     * create a new rating grid
     */
    public function addAction()
    {
        $request = $this->getRequest();

        if ($request->getPost('submit') && !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $message = '<strong>' . $this->view->translate('Votre session a expiré, veuillez réessayer.') . '</strong>';
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        } elseif ($request->getPost('submit')) {

            // grid of a review request type of the journal (#319), else grid of a volume (0: default grid)
            $requestTypeId = $request->getPost('request_type');
            $requestType = is_scalar($requestTypeId) && ctype_digit((string)$requestTypeId)
                ? (new RequestTypeRepository())->findById((int)$requestTypeId)
                : null;

            if ($requestTypeId !== null && $requestTypeId !== '' && ($requestType === null || $requestType->getRvid() !== RVID)) {
                // unknown type, or a type of another journal
                $message = '<strong>' . $this->view->translate("Ce type de demande n'existe pas.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->_helper->redirector('list', 'grid');
                return;
            }

            if ($requestType !== null) {
                $filename = Episciences_GridsManager::filenameOf(Episciences_GridsManager::requestTypeGridKey((int)$requestType->getId()));
            } else {
                $id = (is_numeric($request->getPost('volume'))) ? (int)$request->getPost('volume') : 0;
                $filename = 'grid_' . $id . '.xml';
            }

            $grid = new Episciences_Rating_Grid;
            $grid->setFilename($filename);

            if (file_exists(REVIEW_GRIDS_PATH . $filename)) {
                $message = '<strong>' . $this->view->translate("Cette grille de notation existe déjà.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            } elseif ($grid->save()) {
                $message = '<strong>' . $this->view->translate("La nouvelle grille a bien été enregistrée.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
            } else {
                $message = '<strong>' . $this->view->translate("La grille n'a pas pu être créée.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            }
        }

        $this->_helper->redirector('list', 'grid');
    }

    /**
     *  Copie les critères d'une grille de notation vers une autre
     */
    public function copyAction()
    {
        $request = $this->getRequest();

        if (!$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $message = '<strong>' . $this->view->translate('Votre session a expiré, veuillez réessayer.') . '</strong>';
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            $this->_helper->redirector('list', 'grid');
            return;
        }

        $source_grid = new Episciences_Rating_Grid;
        if (!$source_grid->loadXML(REVIEW_GRIDS_PATH . self::gridFilename($request->getPost('from')))) {
            $message = '<strong>' . $this->view->translate("La grille source n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid');
        }

        $dest_grid = new Episciences_Rating_Grid;
        if (!$dest_grid->loadXML(REVIEW_GRIDS_PATH . self::gridFilename($request->getPost('to')))) {
            $message = '<strong>' . $this->view->translate("La grille de destination n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid');
        }

        $dest_grid->setCriteria(array_merge($dest_grid->getCriteria(), $source_grid->getCriteria()));

        if ($dest_grid->save()) {
            $message = '<strong>' . $this->view->translate("La grille par défaut a bien été copiée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
        } else {
            $message = '<strong>' . $this->view->translate("La grille par défaut n'a pas pu être copiée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        }

        $this->_helper->redirector('list', 'grid');

    }

    /**
     * delete a rating grid
     */
    public function deleteAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            echo '<strong>' . $this->view->translate('Votre session a expiré, veuillez réessayer.') . '</strong>';
            return;
        }

        /** @var array $params */
        $params = $request->getPost('params');
        $rgid = Episciences_GridsManager::gridKey($params['rgid'] ?? null);
        $file = self::gridFilename($rgid);

        if ($rgid === '0') {
            $message = '<strong>' . $this->view->translate("La grille par défault ne pas pu être supprimée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            return;
        }

        if (!Episciences_GridsManager::gridExists($file)) {
            echo '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            return;
        }

        if (Episciences_GridsManager::delete($file)) {
            $message = '<strong>' . $this->view->translate("La grille a bien été supprimée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
        } else {
            $message = '<strong>' . $this->view->translate("La grille n'a pas pu être supprimée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        }

        // la redirection vers "/gid/list" est faite dans "js/grid/es.dataTables.delete-buttons.js"
        echo true;
    }


    /**
     * add a new criterion to a rating grid
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public function addcriterionAction()
    {
        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');
        $this->view->jQuery()->addJavascriptFile('/js/grid/form.js');

        $request = $this->getRequest();
        $params = $request->getParams();
        $filename = self::gridFilename($params['rgid'] ?? null);

        $oGrid = new Episciences_Rating_Grid;
        if (!$oGrid->loadXML(REVIEW_GRIDS_PATH . $filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid');
        }

        $oCriterion = new Episciences_Rating_Criterion();
        $form = Episciences_GridsManager::getCriterionForm();

        if ($request->getPost('submit')) {

            $isTokenValid = Episciences_Csrf_Helper::validateRequestToken($request);

            // A refused request does not fill the form: its values may come from another site
            if ($isTokenValid && $form->isValid($_POST)) {

                if ($this->savecriterion($oGrid, $oCriterion)) {
                    $message = '<strong>' . $this->view->translate("Le nouveau critère a bien été ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le nouveau critère n'a pas pu être ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid');

            } else {
                $message = '<strong>' . ($isTokenValid ? $this->view->translate("Ce formulaire comporte des erreurs.") : $this->view->translate('Votre session a expiré, veuillez réessayer.')) . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            }
        }

        $this->view->form = $form;
    }

    /**
     * @param Episciences_Rating_Grid $oGrid
     * @param Episciences_Rating_Criterion $oCriterion
     * @param string $type
     * @return bool
     */
    private function savecriterion(Episciences_Rating_Grid $oGrid, Episciences_Rating_Criterion $oCriterion, $type = 'criterion')
    {
        $subType = '';
        $options = null;

        if ($type == 'criterion') {
            $rating = Episciences_GridsManager::getCriterionRatingFromPost($_POST);
            $subType = $rating['subType'];
            $options = $rating['options'];
        }


        // trim and clean description input
        $cleanedDescriptions = [];
        foreach ($_POST['description'] as $lang => $label) {
            $cleanLabel = trim(strip_tags($label));
            if ($cleanLabel != '') {
                $cleanedDescriptions[$lang] = htmlspecialchars($cleanLabel, ENT_QUOTES, 'UTF-8');
            }
        }

        // clean and sanitize criterion labels
        $cleanedLabels = [];
        foreach ($_POST['critere'] as $lang => $label) {
            $cleanLabel = trim(strip_tags($label));
            if ($cleanLabel != '') {
                $cleanedLabels[$lang] = htmlspecialchars($cleanLabel, ENT_QUOTES, 'UTF-8');
            }
        }

        // populate criterion
        $values = [
            'type' => $type,
            'subType' => $subType,
            'labels' => $cleanedLabels,
            'descriptions' => $cleanedDescriptions,
            'visibility' => htmlspecialchars(trim($_POST['visibility']), ENT_QUOTES, 'UTF-8'),
            'coefficient' => (array_key_exists('evaluation_type', $_POST) && $_POST['evaluation_type'] == Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE) ? (float)filter_var($_POST['coef'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null,
            'comment_setting' => Ccsd_Tools::ifsetor($_POST ['comment'], null),
            'attachment_setting' => Ccsd_Tools::ifsetor($_POST ['upload'], null),
            'options' => $options
        ];
        $oCriterion->populate($values);

        if ($oCriterion->getId()) {
            // editing existing criterion: numeric id is extracted from criterion id ("item_x")
            $criterion_id = (int)filter_var($oCriterion->getId(), FILTER_SANITIZE_NUMBER_INT);
        } else {
            // creating new criterion: numeric id is created
            $criterion_id = count($oGrid->getCriteria());
        }

        $oGrid->setCriterion(filter_var($criterion_id, FILTER_SANITIZE_NUMBER_INT), $oCriterion);

        return $oGrid->save();
    }

    /**
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public function addseparatorAction()
    {
        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');

        $request = $this->getRequest();
        $params = $request->getParams();
        $filename = self::gridFilename($params['rgid'] ?? null);

        $oGrid = new Episciences_Rating_Grid;
        if (!$oGrid->loadXML(REVIEW_GRIDS_PATH . $filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid');
        }

        $oCriterion = new Episciences_Rating_Criterion;
        $form = Episciences_GridsManager::getSeparatorForm();

        if ($request->getPost('submit')) {

            $isTokenValid = Episciences_Csrf_Helper::validateRequestToken($request);

            // A refused request does not fill the form: its values may come from another site
            if ($isTokenValid && $form->isValid($request->getPost())) {

                if ($this->savecriterion($oGrid, $oCriterion, 'separator')) {
                    $message = '<strong>' . $this->view->translate("Le nouveau séparateur a bien été ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le nouveau séparateur n'a pas pu être ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid');

            } else {
                $message = '<strong>' . ($isTokenValid ? $this->view->translate("Ce formulaire comporte des erreurs.") : $this->view->translate('Votre session a expiré, veuillez réessayer.')) . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            }
        }

        $this->view->form = $form;
        $this->renderScript('/grid/addcriterion.phtml');
    }

    /**
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public function editseparatorAction()
    {
        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');

        $request = $this->getRequest();
        $params = $request->getParams();
        $filename = self::gridFilename($params['rgid'] ?? null);
        $criterion_id = $params['id'];

        if (!Episciences_GridsManager::gridExists($filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid');
        }

        $oGrid = new Episciences_Rating_Grid;
        $oGrid->loadXML(REVIEW_GRIDS_PATH . $filename);
        $oCriterion = $oGrid->getCriterion(filter_var($criterion_id, FILTER_SANITIZE_NUMBER_INT));

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($oCriterion);
        $form = Episciences_GridsManager::getSeparatorForm($defaults);

        $post = $request->getPost();

        if ($request->getPost('submit')) {
            $isTokenValid = Episciences_Csrf_Helper::validateRequestToken($request);

            // A refused request does not fill the form: its values may come from another site
            if ($isTokenValid && $form->isValid($_POST)) {

                if ($this->savecriterion($oGrid, $oCriterion, 'separator')) {
                    $message = '<strong>' . $this->view->translate("Le séparateur a bien été modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le séparateur n'a pas pu être modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid');

            } else {
                $message = '<strong>' . ($isTokenValid ? $this->view->translate("Ce formulaire comporte des erreurs.") : $this->view->translate('Votre session a expiré, veuillez réessayer.')) . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->view->form = $form;
            }
        }

        $this->view->form = $form;
        $this->renderScript('/grid/editcriterion.phtml');
    }

    /**
     * process form and save a grid item (criterion or separator)
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public function editcriterionAction()
    {
        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');
        $this->view->jQuery()->addJavascriptFile('/js/grid/form.js');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $params = $request->getParams();

        $criterionId = filter_var($params['id'], FILTER_SANITIZE_NUMBER_INT);

        $filename = self::gridFilename($params['rgid'] ?? null);

        if (!Episciences_GridsManager::gridExists($filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid');
        }

        $oGrid = new Episciences_Rating_Grid();
        $oGrid->loadXML(REVIEW_GRIDS_PATH . $filename);
        $oCriterion = $oGrid->getCriterion($criterionId);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($oCriterion);
        $form = Episciences_GridsManager::getCriterionForm($defaults);

        if ($request->getPost('submit')) {

            $isTokenValid = Episciences_Csrf_Helper::validateRequestToken($request);

            // A refused request does not fill the form: its values may come from another site
            if ($isTokenValid && $form->isValid($_POST)) {

                if ($this->savecriterion($oGrid, $oCriterion)) {
                    $message = '<strong>' . $this->view->translate("Le critère a bien été modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le critère n'a pas pu être modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid');

            } else {
                $message = '<strong>' . ($isTokenValid ? $this->view->translate("Ce formulaire comporte des erreurs.") : $this->view->translate('Votre session a expiré, veuillez réessayer.')) . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                $this->view->form = $form;
            }
        }

        $this->view->form = $form;
    }

    public function deletecriterionAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();

        $request = $this->getRequest();

        if (!$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            echo $this->view->translate('Votre session a expiré, veuillez réessayer.');
            return;
        }

        $params = $request->getPost('params');


        $filename = self::gridFilename($params['rgid'] ?? null);
        $item_id = $params['id'];


        if (!Episciences_GridsManager::gridExists($filename)) {
            echo $this->view->translate("Cette grille n'existe pas." . $filename);
            return;
        }

        $oGrid = new Episciences_Rating_Grid;
        $oGrid->loadXML(REVIEW_GRIDS_PATH . $filename);
        $oGrid->removeCriterion($item_id);
        $oGrid->save();
        // la redirection vers "grid/list" est faite dans "js/grid/es.dataTables.delete-buttons.js"
        $message = '<strong>' . $this->view->translate("La modification a été effectuée avec succès.") . '</strong>';
        $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);

        echo true;

    }

    public function sortcriterionAction()
    {
        $request = $this->getRequest();
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();

        if (!$request->isPost() || !Episciences_Csrf_Helper::validateRequestToken($request)) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        $params = $request->getPost();
        $params['rvid'] = RVID;

        $respond = Episciences_GridsManager::sortCriterion($params);
        echo $respond;
    }

}
