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
     * @throws Zend_Db_Statement_Exception|Zend_Db_Select_Exception
     */
    public function mergeAction(): void
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

    public function ccsdMetricsAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        // Count of published documents
        try {
            $nbOfPublications = Episciences_PapersManager::getPublishedPapersCount();
        } catch (Zend_Db_Statement_Exception $e) {
            $nbOfPublications = 0;
        }


        // Count of Users for the current year, at the time of request
        try {
            $nbOfUsers = Episciences_User_UserMapper::getUserCountAfterDate('1970-01-01 00:00:00');
        } catch (Zend_Db_Statement_Exception $e) {
            $nbOfUsers = 0;
        }

        header('Content-Type: application/json');

        try {
            echo json_encode(['nbOfEpisciencesUsers' => $nbOfUsers, 'nbOfEpisciencesPublications' => $nbOfPublications], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $timeOut = 1800;
            header('HTTP/1.0 503 Service Unavailable');
            header('Retry-After: ' . $timeOut);
            printf("Sorry, this service is not available at the moment. We must be busy fixing a problem, please try again in %s seconds", $timeOut);
        }
    }

    /**
     * Metrics for OpenAIRE formatted as Openmetrics
     */
    public function openaireMetricsAction()
    {

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $countOfServiceUnavailable = 0;

        // current year all time users
        try {
            $nbOfSubmissionsCurrentYear = Episciences_PapersManager::getSubmittedPapersCountAfterDate('', '1970-01-01 00:00:00');
        } catch (Zend_Db_Statement_Exception $e) {
            $countOfServiceUnavailable++;
        }

        // current year, current year users
        try {
            $nbOfSubmissionsCurrentYearNewUsers = Episciences_PapersManager::getSubmittedPapersCountAfterDate('', '');
        } catch (Zend_Db_Statement_Exception $e) {
            $countOfServiceUnavailable++;
        }

        // current year, users accounts created from 2021
        try {
            $nbOfSubmissionsNexusUsers = Episciences_PapersManager::getSubmittedPapersCountAfterDate('', '2021-01-01 00:00:00');
        } catch (Zend_Db_Statement_Exception $e) {
            $countOfServiceUnavailable++;
        }


        // Count of Users for the current year, at the time of request
        try {
            $nbOfUsers = Episciences_User_UserMapper::getUserCountAfterDate('1970-01-01 00:00:00');
        } catch (Zend_Db_Statement_Exception $e) {
            $countOfServiceUnavailable++;
        }

        // Count of Users for the current year, only user accounts created in the current year, at the time of request
        try {
            $nbOfNexusUsersCreatedCurrentYear = Episciences_User_UserMapper::getUserCountAfterDate('');
        } catch (Zend_Db_Statement_Exception $e) {
            $countOfServiceUnavailable++;
        }

        // Count of Users user accounts created since 2021-01-01 00:00:00, at the time of request
        try {
            $nbOfNexusUsers = Episciences_User_UserMapper::getUserCountAfterDate('2021-01-01 00:00:00');
        } catch (Zend_Db_Statement_Exception $e) {
            $countOfServiceUnavailable++;
        }

        if ($nbOfSubmissionsCurrentYear === false) {
            $countOfServiceUnavailable++;
        }

        if ($nbOfSubmissionsCurrentYearNewUsers === false) {
            $countOfServiceUnavailable++;
        }

        if ($nbOfSubmissionsNexusUsers === false) {
            $countOfServiceUnavailable++;
        }

        if ($nbOfUsers === false) {
            $countOfServiceUnavailable++;
        }

        if ($nbOfNexusUsersCreatedCurrentYear === false) {
            $countOfServiceUnavailable++;
        }

        if ($nbOfNexusUsers === false) {
            $countOfServiceUnavailable++;
        }

        $nbOfActiveJournals = Episciences_ReviewsManager::findActiveJournalsWithAtLeastOneSubmission();
        $nbOfActiveNexusJournals = Episciences_ReviewsManager::findActiveJournalsWithAtLeastOneSubmission('2021-01-01 00:00:00');


        if ($countOfServiceUnavailable > 0) {
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
        echo PHP_EOL;
        echo PHP_EOL . '# HELP episciences_journals_total Count of Journals, at the time of request';
        echo PHP_EOL . '# TYPE episciences_journals_total counter';
        printf(PHP_EOL . 'episciences_journals_total %d', $nbOfActiveJournals);
        echo PHP_EOL;
        echo PHP_EOL . '# HELP episciences_nexusjournals_total Count of Journals created since 2021-01-01 00:00:00, at the time of request';
        echo PHP_EOL . '# TYPE episciences_nexusjournals_total counter';
        printf(PHP_EOL . 'episciences_nexusjournals_total %d', $nbOfActiveNexusJournals);
        echo PHP_EOL;
        echo PHP_EOL . '# HELP episciences_users_total Count of Users for the current year, at the time of request';
        echo PHP_EOL . '# TYPE episciences_users_total counter';
        printf(PHP_EOL . 'episciences_users_total %d', $nbOfUsers);
        echo PHP_EOL;
        echo PHP_EOL . '# HELP episciences_newusers_total Count of Users for the current year, only user accounts created in the current year, at the time of request';
        echo PHP_EOL . '# TYPE episciences_newusers_total counter';
        printf(PHP_EOL . 'episciences_newusers_total %d', $nbOfNexusUsersCreatedCurrentYear);
        echo PHP_EOL;
        echo PHP_EOL . '# HELP episciences_nexususers_total Count of Users user accounts created since 2021-01-01 00:00:00, at the time of request';
        echo PHP_EOL . '# TYPE episciences_nexususers_total counter';
        printf(PHP_EOL . 'episciences_nexususers_total %d', $nbOfNexusUsers);
    }

    public function journalsAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $journalArray = Episciences_ReviewsManager::findActiveJournals();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $format = $request->getParam('format', 'json');

        if ($format === 'json') {
            $journals = [];
            $headers = array_shift($journalArray);
            foreach ($journalArray as $journalNumber => $journalRow) {
                foreach ($journalRow as $rowNumber => $journalInfo) {
                    $journals[$journalNumber][$rowNumber] = [$headers[$rowNumber] => $journalInfo];
                }
            }

            header('Content-Type: application/json');
            echo Zend_Json_Encoder::encode($journals);
            exit;
        }

        if ($format === 'csv') {
            $filename = 'journals_' . date('Y-m-d_H-i-s') . '.csv';
            $now = gmdate("D, d M Y H:i:s");
            header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
            header("Last-Modified: $now GMT");
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header("Content-Disposition: attachment;filename=$filename");
            header("Content-Transfer-Encoding: binary");

            if (count($journalArray) === 0) {
                return null;
            }

            ob_start();
            $df = fopen("php://output", 'w');

            foreach ($journalArray as $row) {
                fputcsv($df, $row);
            }
            fclose($df);
            echo ob_get_clean();
        }
    }

}