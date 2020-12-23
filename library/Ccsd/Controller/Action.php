<?php
/**
 * Created by PhpStorm.
 * User: yannick
 * Date: 17/10/2016
 * Time: 14:06
 */

class Ccsd_Controller_Action extends Zend_Controller_Action
{

    /**
     * Retourne les parametres POST d'une requete
     */
    protected function getParams()
    {
        return $this->getRequest()->getPost();
    }

    /**
     * Determine si la requete est une requete Ajax Post
     * @return bool
     */
    protected function isAjaxPost()
    {
        return $this->getRequest()->isXmlHttpRequest() && $this->getRequest()->isPost();
    }

    /**
     * @param bool $viewRender affichage du rendu de l'action
     * @param bool $viewLayout
     * @return void
     */
    protected function noRender($viewRender = false, $viewLayout = false)
    {
        if (!$viewLayout) {
            $this->_helper->layout()->disableLayout();
        }
        if (!$viewRender) {
            $this->_helper->viewRenderer->setNoRender();
        }
    }

    /**
     * Affiche les données en json
     * @param $data
     * @return string
     */
    protected function renderJson($data)
    {
        return json_encode($data);
    }

}