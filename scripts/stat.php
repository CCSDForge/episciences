<?php
/**
 * Episciences
 * Script to process stats
 * Add in a Crontab
 */

$localopts = [
    'date-s' => "Process stats according to a date (yyyy-mm-dd; default: yesterday)",
    'all|a' => "Process ALL stats regardless of date (WARNING: potentially resource intensive)",
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
// Validate mutual exclusion between --date and --all
if ($opts->date && $opts->all) {
    println('Error: --date and --all options are mutually exclusive');
    help($opts);
    exit(1);
}

$processAll = false;
if ($opts->all) {
    $processAll = true;
    $date = null; // No date filtering
} elseif ($opts->date) {
    preg_match('/^(\d{1,4})-(\d{1,2})-(\d{1,2})$/', $opts->date, $matches);
    if (isset($matches[1]) && isset($matches[2]) && isset($matches[3]) && checkdate($matches[2], $matches[3], $matches[1])) {
        $date = $opts->date;
    } else {
        println('Error: bad date format');
        help($opts);
        exit(1);
    }
} else {
    $date = date('Y-m-d', strtotime('-1 day'));
}

// Autoloader
require_once('Zend/Loader/Autoloader.php');
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);

$db = Zend_Db_Table_Abstract::getDefaultAdapter();
$debug = $opts->debug;

// Safety confirmation for --all option
if ($processAll && !$debug) {
    println("WARNING: You are about to process ALL statistics regardless of date.");
    println("This operation may take a very long time and consume significant resources.");
    $confirmation = getParam("Are you sure you want to continue", false, ['y', 'n'], 'n');
    if ($confirmation !== 'y') {
        println("Operation cancelled.");
        exit(0);
    }
}

if ($debug) {
    if ($processAll) {
        println("Processing ALL statistics (no date filtering)");
    } else {
        println("Processing Date: " . $date);
    }
}


$linesProcessed = 0;
$linesIgnored = 0;
$linesInError = 0;
$linesFromRobots = 0;

$insertPrepared = $db->prepare("INSERT INTO `PAPER_STAT` (`DOCID`, `CONSULT`, `IP`, `ROBOT`, `AGENT`, `DOMAIN`, `CONTINENT`, `COUNTRY`, `CITY`, `LAT`, `LON`, `HIT`, `COUNTER`) VALUES (:DOCID, :CONSULT, :IP, :ROBOT, :AGENT, :DOMAIN, :CONTINENT, :COUNTRY, :CITY, :LAT, :LON, :HIT, :COUNTER) ON DUPLICATE KEY UPDATE COUNTER=COUNTER+1");

// Prepare delete statement based on processing mode
if ($processAll) {
    $DeletePrepared = $db->prepare("DELETE FROM `STAT_TEMP` ORDER BY DHIT LIMIT " . STEP_OF_LINES);
} else {
    $DeletePrepared = $db->prepare("DELETE FROM `STAT_TEMP` WHERE DATE_FORMAT(DHIT, '%Y-%m-%d') <= :DATE_TO_DEL ORDER BY DHIT LIMIT " . STEP_OF_LINES);
}


try {

    // Build count query based on processing mode
    $sql = $db->select()->from('STAT_TEMP', new Zend_Db_Expr("COUNT('*')"));
    if (!$processAll) {
        $dateFormatted = date('Y-m-d H:i:s', strtotime($date));
        $sql->where("DHIT <= ?", $dateFormatted);
    }
    $count = $db->fetchOne($sql);
    
    if ($debug) {
        // Only show environment in development/testing environments
        if (in_array(APPLICATION_ENV, ['development', 'testing'])) {
            println("Environment: " . APPLICATION_ENV);
        }
        if ($processAll) {
            println("Processing ALL statistics (no date limit)");
        } else {
            println("Date to process is <= " . $date);
        }
        println("Total of lines : " . $count);
    }

    $giReader = new GeoIp2\Database\Reader(GEO_IP_DATABASE_PATH . GEO_IP_DATABASE);

    if ($count > 0) {

        //Sélection des lignes par tranche de PAS
        while (true) {

            $sqlStatTemp = $db->select()->from('STAT_TEMP', new Zend_Db_Expr('*, INET_NTOA(IP) as TIP'));
            if (!$processAll) {
                $sqlStatTemp->where("DATE_FORMAT(DHIT, '%Y-%m-%d') <= ?", $date);
            }
            $sqlStatTemp->order('DHIT ASC')->limit(STEP_OF_LINES);

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
                $vData = $v->getLocalisation($giReader);

                if ($v->isRobot()) {
                    // we do not keep robot hits
                    $linesFromRobots++;
                    continue;
                }

                $hit = substr($value["DHIT"], 0, 7) . '-01';

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
                        println("Database error: " . $exception->getMessage());
                    } else {
                        println("Database insertion error occurred (enable debug for details)");
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


            try {
                //Suppression des lignes de la table STAT_TEMP
                if ($debug) {
                    println("Deleting " . STEP_OF_LINES . " processed lines from STAT_TEMP");
                }
                
                if ($processAll) {
                    $DeletePrepared->execute();
                } else {
                    $bindDelete = [];
                    $bindDelete[':DATE_TO_DEL'] = $date;
                    $DeletePrepared->execute($bindDelete);
                }

            } catch (Zend_Db_Statement_Exception $exceptionDelete) {
                if ($debug) {
                    println("Error deleting " . STEP_OF_LINES . " lines in STAT_TEMP");
                    println("Error details: " . ($debug ? $exceptionDelete->getMessage() : 'Database error occurred'));
                }
            }


        }
    }

    // Closes the GeoIP database
    $giReader->close();
} catch (Exception $e) {
    println("> Errors in script : ");
    if ($debug) {
        println("Message : " . $e->getMessage());
        println("Code : " . $e->getCode());
        echo "Trace : ";
        print_r($e->getTrace());
    } else {
        println("A critical error occurred. Run with --debug for details.");
        println("Error code: " . $e->getCode());
    }
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
