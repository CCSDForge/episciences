<?php
/**
 * Normalisation de licences + conversion SPDX + audit
 *
 */


declare(strict_types=1);
require_once "AbstractScript.php";

use Episciences\Paper\Spdx\LicenseSpdxResolver;
use scripts\AbstractScript;

class ResolveLicense extends AbstractScript
{

    public function __construct()
    {
        parent::__construct();
        $this->initLogging();
    }

    public function run(): void
    {
        echo PHP_EOL;
        $auditMsg = '===== AUDIT LICENSES =====';
        echo '===== AUDIT LICENSES =====' . PHP_EOL . PHP_EOL;
        $this->logger->info($auditMsg);

        $this->init();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db?->select()->from(T_PAPER_LICENCES, ['docid', 'licence']);
        $licenses = $db->fetchPairs($sql);
        $noAssertion = [];
        $sqlDump = '';
        $tableName = 'paper_license_code';
        $licensesResolved = [];

        $resolver = new LicenseSpdxResolver();

        foreach ($licenses as $doId => $url) {
            echo 'DOCID      : ' . $doId . PHP_EOL;
            echo 'Original   : ' . $url . PHP_EOL;
            $resolved = $resolver->resolve($url);

            if ($resolved === LicenseSpdxResolver::NO_ASSERTION) {
                echo 'SPDX       : NOT FOUND' . PHP_EOL;
                $noAssertion[$url][] = $doId;
            } else {
                echo 'Normalized : ' . $resolved . PHP_EOL;
                $sqlDump .= "INSERT INTO `$tableName` (`docid`, `code`) VALUES ($doId, '$resolved') ON DUPLICATE KEY UPDATE `code`= '$resolved';";
                $sqlDump .= PHP_EOL;
                $licensesResolved[$resolved][] = $doId;

            }

            echo str_repeat('-', 62) . PHP_EOL;
        }

        try {
            $db->beginTransaction();
            $db->query($sqlDump);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
        }

        echo PHP_EOL;
        echo 'List of licences available after URL resolution:';
        echo PHP_EOL;
        echo print_r(array_keys($licensesResolved), true);
        echo PHP_EOL;
        echo str_repeat('-', 62) . PHP_EOL;
        echo PHP_EOL;
        echo 'licences identified with no SPDX match: ';
        echo PHP_EOL;
        echo print_r(array_keys($noAssertion), true);

    }

    public function init(): void
    {
        defineSQLTableConstants();
        $this->initApp(false);
        $this->initDb();
    }

}


(new ResolveLicense())->run();



