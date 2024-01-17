<?php

class Episciences_View_Helper_PaperFilter extends Zend_View_Helper_Abstract
{
    public const NONE_KEY = '0';

    /**
     * @param $open
     * @return Zend_Form
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Form_Exception
     */
    public function PaperFilter($open = null): \Zend_Form
    {
        $review = Episciences_ReviewsManager::find(RVID);

        $controller = Zend_Controller_Front::getInstance()->getRequest()->getControllerName();
        $action = Zend_Controller_Front::getInstance()->getRequest()->getActionName();
        $params = Episciences_PapersManager::getFiltersParams();

        // filters are open if at least one is activated, closed otherwise
        if (!$open) {
            $open = !empty($params);
        }

        $form = new Zend_Form();
        $form->setMethod('get');
        $form->setAction('/'.$controller.'/'.$action);
        $form->setAttrib('class', 'form-inline');
        $form->setDecorators(array(array('ViewScript', array('viewScript'=>'/paper/filters.phtml', 'open'=>$open))));

        $decorators = array(
                array('ViewHelper', array('style' => 'clear: both')),
                'Errors',
                array('Description', array('tag' => 'p', 'class' => 'description')),
                'Label',
                array('HtmlTag', array('class' => 'form-group'))
        );

        // paper status
        $status = [];

        /** @var string $code */
        foreach (Episciences_PapersManager::getAllStatus(RVID, 'ASC') as $code) {
            $code = (int)$code;
            $status[$code] = ucfirst($this->view->translate(Episciences_PapersManager::getStatusLabel($code)));
        }

        asort($status);

        $form->addElement(new Zend_Form_Element_Multiselect(array(
                'name'			=> 'status',
                'label'			=> "Statut de l'article",
                'class'			=> 'form-control',
                'multiOptions'	=> ['' => $this->view->translate('Tous')] + $status,
                'value'			=>	'',
                'required'		=> true,
                'decorators'	=> $decorators
        )));

        // rating status
        if ($action === 'ratings') {

            $options = [''	=>	$this->view->translate('Tous')];
            foreach (Episciences_Reviewer_Reviewing::getStatusList() as $value=>$label) {
                $options[$value] = ucfirst($this->view->translate($label));
            }

            $form->addElement(new Zend_Form_Element_Multiselect(array(
                    'name'			=> 'ratingStatus',
                    'label'			=> 'État de ma relecture',
                    'class'			=> 'form-control',
                    'multiOptions'	=> $options,
                    'value'			=>	'',
                    'required'		=> true,
                    'decorators'	=> $decorators
            )));
        }

        // volumes
        $options = [''	=>	$this->view->translate('Tous')];
        foreach($review->getVolumes() as $oVolume) {
            $options[$oVolume->getVid()] = $oVolume->getNameKey();
        }

        $form->addElement(new Zend_Form_Element_Multiselect(array(
                'name'			=> 'vid',
                'label'			=> 'Volume',
                'class'			=> 'form-control',
                'multiOptions'	=> $options,
                'value'			=>	'',
                'required'		=> true,
                'decorators'	=> $decorators
        )));

        // sections
        $options = array(''	=>	$this->view->translate('Toutes'));
        foreach($review->getSections() as $oSection) {
            $options[$oSection->getSid()] = $this->view->translate($oSection->getNameKey());
        }

        $form->addElement(new Zend_Form_Element_Multiselect(array(
                'name'			=> 'sid',
                'label'			=> 'Rubrique',
                'class'			=> 'form-control',
                'multiOptions'	=> $options,
                'value'			=>	'',
                'required'		=> true,
                'decorators'	=> $decorators
        )));

        if (Episciences_Auth::isAllowedToManagePaper() || $review->getSetting(Episciences_Review::SETTING_CAN_PICK_EDITOR) > 0) {
            // editors
            $oEditors = Episciences_Review::getEditors(false);
            $this->sortOut($oEditors);
            $editors = Episciences_UsersManager::skipRootFullName($oEditors);

            $form->addElement(new Zend_Form_Element_Multiselect([
                'name' => 'editors',
                'label' => "Rédacteurs",
                'class' => 'form-control',
                'multiOptions' => ['' => $this->view->translate('Tous'), self::NONE_KEY => ucfirst($this->view->translate('aucun'))] + $editors,
                'value' => '',
                'required' => true,
                'decorators' => $decorators
            ]));
        }

        if (Episciences_Auth::isAllowedToManagePaper()) {
            //reviewers
            $sReviewers = Episciences_Review::getReviewers();
            $this->sortOut($sReviewers);
            $reviewers = Episciences_UsersManager::skipRootFullName($sReviewers);

            $form->addElement(new Zend_Form_Element_Multiselect([
                'name' => 'reviewers',
                'label' => "Relecteurs",
                'class' => 'form-control',
                'multiOptions' => ['' => $this->view->translate('Tous'), self::NONE_KEY => ucfirst($this->view->translate('aucun'))] + $reviewers,
                'value' => '',
                'required' => true,
                'decorators' => $decorators
            ]));



            // DOI
            $options = [
                ''             => $this->view->translate('Tous'),
                self::NONE_KEY => ucfirst($this->view->translate('aucun')),
                '-1'           => ucfirst($this->view->translate('avec'))
            ];

            $form->addElement(new Zend_Form_Element_Multiselect([
                'name' => 'doi',
                'label' => "DOI",
                'class' => 'form-control',
                'multiOptions' => $options,
                'value' => '',
                'required' => true,
                'decorators' => $decorators
            ]));


            // repositories

            $rLabels = Episciences_PapersManager::getOnlyActivatedRepositoriesLabels();
            $rOptions = array_merge(['' => $this->view->translate('Tous')], $rLabels);

            $form->addElement(new Zend_Form_Element_Multiselect([
                'name' => 'repositories',
                'label' => "Archives",
                'class' => 'form-control',
                'multiOptions' => $rOptions,
                'value' => '',
                'required' => true,
                'decorators' => $decorators
            ]));

        }

        $form->addElement(new Zend_Form_Element_Button([
                'name'		=> 'submit',
                'type'		=> 'submit',
                'class'		=> 'btn btn-default',
                'label'		=> 'Filtrer les articles'
        ]));

        $form->populate($params);

        return $form;
    }

    /**
     * @param Episciences_User[] $users
     */
    private function sortOut(array &$users): void
    {
        usort($users, static function ($a, $b) {
            /**
             * @var Episciences_User $a
             * @var Episciences_User $b
             */
            if ($a->getLastname() === $b->getLastname()) {
                if ($a->getFirstname() === $b->getFirstname()) {
                    return 0;
                }

                return ($a->getFirstname() > $b->getFirstname()) ? -1 : 1; // LOL
            }
            return ($a->getLastname() < $b->getLastname()) ? -1 : 1;
        });
    }

}