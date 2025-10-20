<?php

require_once "JournalScript.php";


class NewFlagFieldUpgradePapers extends JournalScript
{

    public function __construct()
    {
        $this->displayInfo('*** Upgrade Papers table' . PHP_EOL, true, ['bold']);
        $args = [];
        $this->setArgs(array_merge($this->getArgs(), $args));
        parent::__construct();
    }

    public function run()
    {
        $startTime = time();

        if ($this->isVerbose()) {
            $this->displayInfo("Verbose mode: 1");
            $this->displayInfo("Debug mode: " . $this->isDebug());
        }

        $this->checkAppEnv();

        defineSQLTableConstants();
        defineApplicationConstants();

        $this->initApp();
        $this->initDb();

        if ($this->upgradeTable()) {
            $this->displayInfo('Update completed. Good Bye ! =)', true);
        } else {
            $this->displayCritical('Update incomplete.', true);
        }

        $endTime = time();
        $passedTime = $endTime - $startTime;

        $this->displayTrace("The script took $passedTime seconds to run.", true);
    }

    /**
     * @return bool
     */
    public function upgradeTable(): bool
    {
        return $this->upgradeProcessing();

    }

    private function upgradeProcessing(): bool
    {
        $countUpdatedRows = 0;

        $db = $this->getDb();

        $dataQuery = $db->select()->from(T_PAPERS)->order('DOCID DESC');

        $docIds = $db->fetchCol($dataQuery);


        $count = count($docIds);

        $this->getProgressBar()->start();

        foreach ($docIds as $key => $docId) {


            $this->displayInfo('*** Update processing (' . $key . '/' . $count . ') [ DOCID = ' . $docId . ' ] ***', true);
            $progress = round((($key + 1) * 100) / $count);
            $this->getProgressBar()->setProgress($progress);


            try {

                /** @var Episciences_Paper $paper */

                $paper = Episciences_PapersManager::get($docId, false);

                if ($paper->isPublished() && $paper->getPublication_date() < $paper->getSubmission_date()) {

                    if ($paper->getFlag() !== 'imported') {
                        $paper->setFlag('imported');
                        $paper->save();
                        ++$countUpdatedRows;
                        $this->displaySuccess('*** Action successfully complete ***', true);
                    }
                } else {
                    $this->displayTrace("*** No updates [ flag = 'submitted' ] ***", true);
                }

            } catch (Exception $e) {
                $this->displayError($e->getMessage());
                return false;
            }


        }// end foreach

        $this->getProgressBar()->stop();

        $this->displayInfo('*** Update summary ***', true);
        $this->displaySuccess('Updated rows: ' . $countUpdatedRows, true);


        return true;
    }
}

$script = new NewFlagFieldUpgradePapers();
$script->run();