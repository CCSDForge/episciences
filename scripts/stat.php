<?php
/**
 * Episciences
 * Script to process stats
 * Add in a Crontab
 */

$localopts = [
    'date-s' => "Process stats according to a date (yyyy-mm-dd; default: yesterday)",
];

define('DEFAULT_ENV', 'production');
define('STEP_OF_LINES', '500');

define('APPLICATION_PATH', __DIR__ . '/../application');

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}

/** @var Zend_Console_Getopt */
if ($opts->date) {
    preg_match('/^(\d{1,4})-(\d{1,2})-(\d{1,2})$/', $opts->date, $matches);
    if (isset($matches[1]) && isset($matches[2]) && isset($matches[3]) && checkdate($matches[2], $matches[3], $matches[1])) {
        $date = $opts->date;
    } else {
        println('Error: bad date format');
        help($opts);
        exit (1);
    }
} else {
    $date = date('Y-m-d', strtotime('-1 day'));
}

$db = Zend_Db_Table_Abstract::getDefaultAdapter();

if ($debug) {
    println("Processing Date: " . $date);
}


$linesProcessed = 0;
$linesIgnored = 0;
$linesInError = 0;
$linesFromRobots = 0;

$insertPrepared = $db->prepare("INSERT INTO `PAPER_STAT` (`DOCID`, `CONSULT`, `IP`, `ROBOT`, `AGENT`, `DOMAIN`, `CONTINENT`, `COUNTRY`, `CITY`, `LAT`, `LON`, `HIT`, `COUNTER`) VALUES (:DOCID, :CONSULT, :IP, :ROBOT, :AGENT, :DOMAIN, :CONTINENT, :COUNTRY, :CITY, :LAT, :LON, :HIT, :COUNTER) ON DUPLICATE KEY UPDATE COUNTER=COUNTER+1");

$DeletePrepared = $db->prepare("DELETE FROM `STAT_TEMP` WHERE DATE_FORMAT(DHIT, '%Y-%m-%d') <= :DATE_TO_DEL ORDER BY DHIT LIMIT " . STEP_OF_LINES);


try {

    $dateFormatted = date('Y-m-d H:i:s', strtotime($date));
    $sql = $db->select()->from('STAT_TEMP', new Zend_Db_Expr("COUNT('*')"))->where("DHIT <= ?", $dateFormatted);
    $count = $db->fetchOne($sql);
    if ($debug) {
        println("Environment: " . APPLICATION_ENV);
        println("Date to process is <= " . $date);
        println("Total of lines : " . $count);
    }
    if ($count > 0) {
        //Sélection des lignes par tranche de PAS
        while (true) {

            $sqlStatTemp = $db->select()->from('STAT_TEMP', new Zend_Db_Expr('*, INET_NTOA(IP) as TIP'))->where("DATE_FORMAT(DHIT, '%Y-%m-%d') <= ?", $date)->order('DHIT ASC')->limit(STEP_OF_LINES);

            $values = $db->fetchAll($sqlStatTemp);

            println("Dealing with: " . count($values) . ' lines');

            if ($values == null || sizeof($values) == 0) {
                break;
            }


            //Traitement sur les lignes sélectionnées
            foreach ($values as $value) {

                $ip = $value["TIP"];
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    $linesIgnored++;
                    continue;
                }

                $v = new Ccsd_Visiteurs($ip, $value['HTTP_USER_AGENT']);
                $vData = $v->getLocalisation();

                if ($v->isRobot()) {
                    // we do not keep robot hits
                    $linesFromRobots++;
                    continue;
                }

                $hit = substr($value["DHIT"], 0, 7) . '-00';

                $bind = [];
                $bind[':DOCID'] = $value["DOCID"];
                $bind[':CONSULT'] = $value["CONSULT"];
                $bind[':IP'] = $value["IP"];
                $bind[':ROBOT'] = (int)$v->isRobot();
                $bind[':AGENT'] = (string)$value["HTTP_USER_AGENT"];
                $bind[':DOMAIN'] = $vData['domain'];
                $bind[':CONTINENT'] = $vData['continent'];
                $bind[':COUNTRY'] = $vData['country'];
                $bind[':CITY'] = $vData['city'];
                $bind[':LAT'] = $vData['lat'];
                $bind[':LON'] = $vData['lon'];
                $bind[':HIT'] = $hit;
                $bind[':COUNTER'] = 1;
                try {
                    $insertPrepared->execute($bind);
                    $linesProcessed++;
                } catch (Zend_Db_Statement_Exception $exception) {
                    if ($debug) {
                        println($exception->getMessage());
                    }
                    $linesInError++;
                }


            }
            if ($debug) {
                println($linesProcessed . " lines were processed OK");
                if ($linesIgnored > 0) {
                    println($linesIgnored . " lines were ignored   NOK");
                }
                if ($linesInError > 0) {
                    println($linesInError . " lines with an error  NOK");
                }
                if ($linesFromRobots > 0) {
                    println($linesFromRobots . " ignored lines from robots");
                }
            }


            $bindDelete = [];
            $bindDelete[':DATE_TO_DEL'] = $date;

            try {
                //Suppression des lignes de la table STAT_TEMP
                if ($debug) {
                    println("Deleting " . STEP_OF_LINES . " processed lines from STAT_TEMP");
                }
                $DeletePrepared->execute($bindDelete);

            } catch (Zend_Db_Statement_Exception $exceptionDelete) {
                if ($debug) {
                    println("Error deleting " . STEP_OF_LINES . " lines in STAT_TEMP");
                    println($exceptionDelete->getMessage());
                }
            }


        }
    }
} catch (Exception $e) {
    println("> Errors in script : ");
    println("Message : " . $e->getMessage());
    println("Code : " . $e->getCode());
    echo "Trace : ";
    print_r($e->getTrace());
    exit(-1);
}


if ($debug) {
    println("OK Bye this is the end. We're fine now.");
}
exit;

/////////////////////////////
function help($consoleOtps)
{
    echo "** A script to process Episciences stats STAT_TEMP **";
    echo PHP_EOL;
    echo $consoleOtps->getUsageMessage();
    exit;
}
