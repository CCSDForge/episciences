<?php

class GridController extends Episciences_Controller_Action
{

    public function indexAction()
    {
        $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
    }

    /**
     *  Liste des grilles de notation
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function listAction()
    {
        $this->view->headMeta()->appendHttpEquiv('robots', 'noindex, nofollow');

        // Récupération de la liste
        $grids = Episciences_GridsManager::getList();

        // Formulaire d'ajout de grille
        $this->view->grids = $grids;

        $this->view->form = Episciences_GridsManager::getGridForm($grids);

        // Traductions
        $this->view->translator = Zend_Registry::get('Zend_Translate');
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
        $oGrid->loadXML(REVIEW_GRIDS_PATH . 'grid_' . $rgid . '.xml');

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

        if ($request->getPost('submit')) {

            $id = (is_numeric($request->getPost('volume'))) ? $request->getPost('volume') : 0;
            $filename = 'grid_' . $id . '.xml';

            $grid = new Episciences_Rating_Grid;
            $grid->setFilename($filename);

            if (file_exists(REVIEW_GRIDS_PATH . $filename)) {
                $message = '<strong>' . $this->view->translate("Une grille de notation existe déjà pour ce volume.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            } elseif ($grid->save()) {
                $message = '<strong>' . $this->view->translate("La nouvelle grille a bien été enregistrée.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
            } else {
                $message = '<strong>' . $this->view->translate("La grille n'a pas pu être créée.") . '</strong>';
                $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
            }
        }

        $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
    }

    /**
     *  Copie les critères d'une grille de notation vers une autre
     */
    public function copyAction(): void
    {
        $request = $this->getRequest();
        $from = $request?->getQuery('from');
        $to = $request?->getQuery('to');

        $source_grid = new Episciences_Rating_Grid;
        if (!$source_grid->loadXML(REVIEW_GRIDS_PATH . 'grid_' . $from . '.xml')) {
            $message = '<strong>' . $this->view->translate("La grille source n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
            return;
        }

        if (empty($source_grid->getCriteria())) {
            $message = '<strong>' . $this->view->translate("La grille source est vide.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
            return;
        }


        $dest_grid = new Episciences_Rating_Grid;
        if (!$dest_grid->loadXML(REVIEW_GRIDS_PATH . 'grid_' . $to . '.xml')) {
            $message = '<strong>' . $this->view->translate("La grille de destination n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
            return;
        }


        if (!empty($dest_grid->getCriteria())) {
            $message = '<strong>' . $this->view->translate("Il n'est pas possible de fusionner la grille par défaut avec une grille déjà finalisée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
            return;
        }


        $dest_grid->setCriteria(array_merge($dest_grid->getCriteria(), $source_grid->getCriteria()));

        if ($dest_grid->save()) {
            $message = '<strong>' . $this->view->translate("La grille par défaut a bien été copiée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
        } else {
            $message = '<strong>' . $this->view->translate("La grille par défaut n'a pas pu être copiée.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
        }

        $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);

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
        /** @var array $params */
        $params = $request->getPost('params');
        $rgid =(int) $params['rgid'];
        $file = 'grid_' . $rgid . '.xml';

        if ($rgid === 0) {
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

        // la redirection vers "/gid/list" est faite dans "js/library/es.dataTables.delete-buttons.js"
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
        $filename = 'grid_' . $params['rgid'] . '.xml';

        $oGrid = new Episciences_Rating_Grid;
        if (!$oGrid->loadXML(REVIEW_GRIDS_PATH . $filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
        }

        $oCriterion = new Episciences_Rating_Criterion();
        $form = Episciences_GridsManager::getCriterionForm();

        if ($request->getPost('submit')) {

            if ($form->isValid($_POST)) {

                if ($this->savecriterion($oGrid, $oCriterion)) {
                    $message = '<strong>' . $this->view->translate("Le nouveau critère a bien été ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le nouveau critère n'a pas pu être ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);

            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
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
        $subType = null; // Initialize subType variable
        
        if ($type == 'criterion') {

            // options
            $options = [];
            $i = 0;


            foreach ($_POST as $key => $value) {
                if (str_starts_with($key, 'option_')) {
                    $labels = [];
                    foreach ($value as $lang => $label) {
                        $cleanLabel = trim(strip_tags($label));
                        if ($cleanLabel != '') {
                            $labels [$lang] = htmlspecialchars($cleanLabel, ENT_QUOTES, 'UTF-8');
                        }
                    }
                    $options [$i] = ['value' => $i, 'label' => $labels];
                    $i++;
                }
            }


            if ($_POST ['evaluation_type'] == Episciences_Rating_Criterion::EVALUATION_TYPE_FREE) {
                unset ($options);
                $subType = Episciences_Rating_Criterion::EVALUATION_TYPE_FREE;
            }

            // if standard values, force back standard options labels
            if ($_POST ['evaluation_type'] == Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE && $_POST ['quantitative_rating_type'] == 0) {
                $subType = Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE;
                $options = [];
                for ($i = 0; $i <= 10; $i++) {
                    $options [] = ['value' => $i, 'label' => []];
                }
            } elseif ($_POST ['evaluation_type'] == Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE && $_POST ['quantitative_rating_type'] == 2) {
                $options = [];
                for ($i = 0; $i <= 5; $i++) {
                    $options [] = ['value' => $i, 'label' => []];
                }
            } elseif ($_POST ['evaluation_type'] == Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE && $_POST ['qualitative_rating_type'] == 0) {
                $subType = Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE;
                $options = [
                    ['value' => 0, 'label' => ['fr' => 'Oui', 'en' => 'Yes']],
                    ['value' => 1, 'label' => ['fr' => 'Non', 'en' => 'No']],
                    ['value' => 2, 'label' => ['fr' => 'Peut-être', 'en' => 'Maybe']]
                ];
            }
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
            '$subType' => $subType,
            'labels' => $cleanedLabels,
            'descriptions' => $cleanedDescriptions,
            'visibility' => htmlspecialchars(trim($_POST['visibility']), ENT_QUOTES, 'UTF-8'),
            'coefficient' => (array_key_exists('evaluation_type', $_POST) && $_POST['evaluation_type'] == Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE) ? (float)filter_var($_POST['coef'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null,
            'comment_setting' => Ccsd_Tools::ifsetor($_POST ['comment'], null),
            'attachment_setting' => Ccsd_Tools::ifsetor($_POST ['upload'], null),
            'options' => Ccsd_Tools::ifsetor($options, null)
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
        $filename = 'grid_' . $params['rgid'] . '.xml';

        $oGrid = new Episciences_Rating_Grid;
        if (!$oGrid->loadXML(REVIEW_GRIDS_PATH . $filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
        }

        $oCriterion = new Episciences_Rating_Criterion;
        $form = Episciences_GridsManager::getSeparatorForm();

        if ($request->getPost('submit')) {

            if ($form->isValid($request->getPost())) {

                if ($this->savecriterion($oGrid, $oCriterion, 'separator')) {
                    $message = '<strong>' . $this->view->translate("Le nouveau séparateur a bien été ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le nouveau séparateur n'a pas pu être ajouté.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);

            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
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
        $filename = 'grid_' . $params['rgid'] . '.xml';
        $criterion_id = $params['id'];

        if (!Episciences_GridsManager::gridExists($filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
        }

        $oGrid = new Episciences_Rating_Grid;
        $oGrid->loadXML(REVIEW_GRIDS_PATH . $filename);
        $oCriterion = $oGrid->getCriterion(filter_var($criterion_id, FILTER_SANITIZE_NUMBER_INT));

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($oCriterion);
        $form = Episciences_GridsManager::getSeparatorForm($defaults);

        $post = $request->getPost();

        if ($request->getPost('submit')) {
            if ($form->isValid($_POST)) {

                if ($this->savecriterion($oGrid, $oCriterion, 'separator')) {
                    $message = '<strong>' . $this->view->translate("Le séparateur a bien été modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le séparateur n'a pas pu être modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);

            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
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

        $gridId = filter_var($params['rgid'], FILTER_SANITIZE_NUMBER_INT);
        $criterionId = filter_var($params['id'], FILTER_SANITIZE_NUMBER_INT);

        $filename = 'grid_' . $gridId . '.xml';

        if (!Episciences_GridsManager::gridExists($filename)) {
            $message = '<strong>' . $this->view->translate("Cette grille n'existe pas.") . '</strong>';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);
            $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);
        }

        $oGrid = new Episciences_Rating_Grid();
        $oGrid->loadXML(REVIEW_GRIDS_PATH . $filename);
        $oCriterion = $oGrid->getCriterion($criterionId);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($oCriterion);
        $form = Episciences_GridsManager::getCriterionForm($defaults);

        if ($request->getPost('submit')) {

            if ($form->isValid($_POST)) {

                if ($this->savecriterion($oGrid, $oCriterion)) {
                    $message = '<strong>' . $this->view->translate("Le critère a bien été modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);
                } else {
                    $message = '<strong>' . $this->view->translate("Le critère n'a pas pu être modifié.") . '</strong>';
                    $this->_helper->FlashMessenger->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->addMessage($message);
                }
                $this->_helper->redirector('list', 'grid', null, [PREFIX_ROUTE => RVCODE]);

            } else {
                $message = '<strong>' . $this->view->translate("Ce formulaire comporte des erreurs.") . '</strong>';
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
        $params = $request->getParam('params');


        $rgid = filter_var($params['rgid'], FILTER_SANITIZE_NUMBER_INT);
        $filename = 'grid_' . $rgid . '.xml';
        $item_id = $params['id'];


        if (!Episciences_GridsManager::gridExists($filename)) {
            echo $this->view->translate("Cette grille n'existe pas." . $filename);
            return;
        }

        $oGrid = new Episciences_Rating_Grid;
        $oGrid->loadXML(REVIEW_GRIDS_PATH . $filename);
        $oGrid->removeCriterion($item_id);
        $oGrid->save();
        // la redirection vers "grid/list" est faite dans "js/library/es.dataTables.delete-buttons.js"
        $message = '<strong>' . $this->view->translate("La modification a été effectuée avec succès.") . '</strong>';
        $this->_helper->FlashMessenger->setNamespace('success')->addMessage($message);

        echo true;

    }

    public function sortcriterionAction()
    {
        $request = $this->getRequest();
        $params = $request->getPost();
        $params['rvid'] = RVID;

        $respond = Episciences_GridsManager::sortCriterion($params);
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->getHelper('layout')->disableLayout();
        echo $respond;
    }

}
