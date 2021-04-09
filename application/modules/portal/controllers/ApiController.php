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

    /**
     * Metrics for OpenAIRE formatted as Openmetrics
     */
    public function openaireMetricsAction()
    {

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();


        $serviceUnavailable = 'Service Unavailable';

        // current year all time users
        try {
            $nbOfSubmissionsCurrentYear = Episciences_PapersManager::getSubmittedPapersCountAfterDate('', '1970-01-01 00:00:00');
        } catch (Zend_Db_Statement_Exception $e) {
            $nbOfSubmissionsCurrentYear = $serviceUnavailable;
        }

        // current year, current year users
        try {
            $nbOfSubmissionsCurrentYearNewUsers = Episciences_PapersManager::getSubmittedPapersCountAfterDate('', '');
        } catch (Zend_Db_Statement_Exception $e) {
            $nbOfSubmissionsCurrentYearNewUsers = $serviceUnavailable;
        }

        // current year, users accounts created from 2021
        try {
            $nbOfSubmissionsNexusUsers = Episciences_PapersManager::getSubmittedPapersCountAfterDate('', '2021-01-01 00:00:00');
        } catch (Zend_Db_Statement_Exception $e) {
            $nbOfSubmissionsNexusUsers = $serviceUnavailable;
        }

        if ($nbOfSubmissionsCurrentYear === false) {
            $nbOfSubmissionsCurrentYear = $serviceUnavailable;
        }

        if ($nbOfSubmissionsCurrentYearNewUsers === false) {
            $nbOfSubmissionsCurrentYearNewUsers = $serviceUnavailable;
        }

        if ($nbOfSubmissionsNexusUsers === false) {
            $nbOfSubmissionsNexusUsers = $serviceUnavailable;
        }

        if (($nbOfSubmissionsCurrentYearNewUsers === $serviceUnavailable) && ($nbOfSubmissionsCurrentYear === $serviceUnavailable) && ($nbOfSubmissionsNexusUsers === $serviceUnavailable)) {
            $timeOut = 1800;
            header('HTTP/1.0 503 Service Unavailable');
            header('Retry-After: ' . $timeOut);
            die(sprintf("Sorry, this service is not available at the moment. We must be busy fixing a problem, please try again in %s seconds", $timeOut));
        }

        $this->getResponse()->setHeader('Content-type', 'text/plain');

        echo '# HELP episciences_user_submissions_total Count of submissions for the current year, from all user accounts, at the time of request';
        echo PHP_EOL . '# TYPE episciences_user_submissions_total counter';
        printf(PHP_EOL . 'episciences_user_submissions_total %s', $nbOfSubmissionsCurrentYear);
        echo PHP_EOL;
        echo PHP_EOL . '# HELP episciences_newuser_submissions_total Count of submissions for the current year, from user accounts created in the current year, at the time of request.';
        echo PHP_EOL . '# TYPE episciences_newuser_submissions_total counter';
        printf(PHP_EOL . 'episciences_newuser_submissions_total %s', $nbOfSubmissionsCurrentYearNewUsers);
        echo PHP_EOL;
        echo PHP_EOL . '# HELP episciences_nexususer_submissions_total Count of submissions for the current year, from user accounts created since 2021-01-01 00:00:00, at the time of request';
        echo PHP_EOL . '# TYPE episciences_nexususer_submissions_total counter';
        printf(PHP_EOL . 'episciences_nexususer_submissions_total %s', $nbOfSubmissionsNexusUsers);


    }
}