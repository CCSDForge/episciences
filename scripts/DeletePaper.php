<?php

require_once "JournalScript.php";

/**
 * Class DeletePaper
 * script for deleting an Episciences Paper
 */
class DeletePaper extends JournalScript
{
    public function __construct()
    {
        // missing required parameters will be asked later
        $this->setRequiredParams([]);

        $this->display('*** Deleting paper', true, ['bold']);

        $args = [
            'rvid=i'    => "set journal id",
            'rvcode=i'  => "set journal code",
            'docid=i'   => "docid of the paper to be deleted (ex: 123)",
            'docids=s'  => "docids of the papers to be deleted (ex: '123,124,125')",
        ];
        $this->setArgs(array_merge($this->getArgs(), $args));
        parent::__construct();
    }

    public function run()
    {
        if ($this->isVerbose()) {
            $this->displayInfo("Verbose mode: 1");
            $this->displayInfo("Debug mode: " . $this->isDebug());
        }

        $this->checkAppEnv();

        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();

        $this->initApp();
        $this->initDb();

        $this->checkRvid();
        $this->checkRvcode();

        defineJournalConstants($this->getParam('rvcode'));


        $this->checkMethod();

        if ($this->getParam('method') == 'single') {
            $this->checkDocid();
            $this->delete($this->getParam('docid'), $this->getParam('rvid'));
        } elseif ($this->getParam('method') == 'multiple') {
            $this->checkDocids();
            $docids = explode(',', str_replace([';', ' '], [',', ''], $this->getParam('docids')));
            $this->delete_multiple_papers($docids, $this->getParam('rvid'));
        }
    }

    public function checkMethod()
    {
        $method = null;

        if ((!$this->hasParam('docid') && !$this->hasParam('docids')) || ($this->hasParam('docid') && $this->hasParam('docids'))) {
            $input = $this->ask("Do you want to delete a single paper, or multiple papers ?", ['single paper', 'multiple papers']);
            $method = ($input == 0) ? 'single' : 'multiple';
        } elseif ($this->hasParam('docid')) {
            $method = 'single';
        } elseif ($this->hasParam('docids')) {
            $method = 'multiple';
        }

        $this->setParam('method', $method);
    }

    public function delete_multiple_papers($docids, $rvid=false)
    {
        if (!is_array($docids) || empty($docids)) {
            $this->displayError("Missing docids");
            return false;
        }

        foreach ($docids as $docid) {
            $this->delete($docid, $rvid);
        }
    }

    public function delete($docid, $rvid=false)
    {
        $this->displayInfo("deleting docid: " . $docid);

        $paper = Episciences_PapersManager::get($docid);
        if (!$paper) {
            $this->displayError("This paper does not exist");
            return false;
        }
        if ($rvid && $paper->getRvid() != $rvid) {
            $this->displayError("This paper does not belong to this journal");
            return false;
        }

        if (!$this->isDebug()) {
            try {
                Episciences_PapersManager::delete($docid);
                $this->displaySuccess('#'.$docid. ' was successfully deleted');
            } catch (Zend_Exception $e) {
                $this->displayError($e->getMessage());
            }
        }

        return true;
    }

    private function checkDocid()
    {
        // if missing docid, ask for it
        if (!$this->hasParam('docid')) {
            $docid = $this->ask('Missing document id. Please enter it: ', null, static::BASH_YELLOW);
            $this->setParam('docid', $docid);
        }
        return $this->hasParam('docid');
    }

    private function checkDocids()
    {
        // if missing docids, ask for it
        if (!$this->hasParam('docids')) {
            $docids = $this->ask('Missing document ids. Please enter them: (ex: 123, 124, 125)', null, static::BASH_YELLOW);
            $this->setParam('docids', $docids);
        }
        return $this->hasParam('docids');
    }

    protected function checkRvcode()
    {
        // if missing rvcode, try to fetch it
        if (!$this->hasParam('rvcode')) {
            $this->checkRvid();
            $sql = $this->getDb()
                ->select()
                ->from(T_REVIEW, ['CODE'])
                ->where('RVID = ?', $this->getParam('rvid'));
            $rvcode = $this->getDb()->fetchOne($sql);

            if ($rvcode) {
                $this->setParam('rvcode', $rvcode);
                defineProtocol();
            } else {
                $this->displayError('rvcode could not be found');
                return false;
            }
        }

        return $this->hasParam('rvcode');
    }
}

$script = new DeletePaper();
$script->run();