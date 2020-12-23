<?php

/**
 * Class ApiController pour la fusion de comptes
 */
class ApiController extends Zend_Controller_Action
{
    /**
     * Action : Fusionner deux comptes
     * exp. https://www.episciences.org/api/merge/merger/100/keeper/200 (1ere requête )
     *  see https://wiki.ccsd.cnrs.fr/wikis/ccsd/index.php/Fusion_de_comptes
     * @throws Zend_Db_Statement_Exception
     */
    public function mergeAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost()) {
            // Requête de la confirmation de la fusion
            $token = (string)$request->getPost('token'); // Le token : renvoyé lors de la confirmation
            $authToken = (string)$request->getpost('authToken'); // Le token d'authentification

            // Dans mon code j'avais prévu ces deux params ci-dessous dans la requête POST.
            // finalement(point fait avec Sarah) l'appli. de fusion renvoie uniquement le tocken.

            $merger = 1;
            $keeper = 1;
            $doMerge = true;

        } else {
            $keeper = (int)$request->getParam('keeper'); // CASID du compte à conserver
            $merger = (int)$request->getParam('merger'); // CASID du compte à fusionner
            $authToken = (string)$request->getParam('authToken'); // Le token d'authentification
            $token = '';
            $doMerge = false;
        }

        if ($authToken !== FUSION_TOKEN_AUTH) { // sécuriser le fait que seule notre application peut faire de la fusion de comptes
            $httpCode = Episciences_Merge_MergingManager::HTTP_UNAUTHORIZED;
            $message = 'Authentication is required to access the resource';
            $response = ['httpStatusCode' => $httpCode, 'message' => $message];

        } else {
            $response = Episciences_Merge_MergingManager::mergeAccounts($merger, $keeper, $token, $doMerge);
        }

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(Zend_Json_Encoder::encode($response));
    }
}