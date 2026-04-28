<?php

set_time_limit(0);
ini_set("memory_limit", '2048M');
ini_set("display_errors", '1');
ini_set('xdebug.mode',('off'));
date_default_timezone_set('Europe/Paris');


$timestart = microtime(true);

require __DIR__ . '/../vendor/autoload.php';

// Environnements
$listEnv = ['development', 'testing', 'preprod', 'production'];
$defaultEnv = 'development';

// Récupération des paramètres du script -- > COMMON OPTIONS + LOCAL OPTIONS
$commonopts = [
    'help|h' => 'Aide',
    'env|e=s' => 'Environnement (' . implode('|', $listEnv) . ') (défaut: ' . $defaultEnv . ')',
    'debug|d' => 'Affichage des messages de debug',
    'verbose|v' => 'Affichage des messages de debug (version complete)',
    'nocolor' => 'Affichage des messages sans la couleur',
];
if ((isset($localopts)) && is_array($localopts)) {
    $opts_array = array_merge($localopts, $commonopts);
} else {
    $opts_array = $commonopts;
}
$opts = new Zend_Console_Getopt($opts_array);
try {
    $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
    die($e->getMessage() . PHP_EOL . $opts->getUsageMessage() . PHP_EOL . PHP_EOL);
}

// Affichage de l'aide
if (isset($opts->h)) {
    die($opts->getUsageMessage());
}

$debug = isset($opts->debug);
$verbose = isset($opts->verbose);
$nocolor = isset($opts->nocolor);


require_once __DIR__ . '/../public/const.php';
require_once __DIR__ . '/../public/bdd_const.php';
defineSimpleConstants();
defineSQLTableConstants();
defineApplicationConstants();
defineProtocol();

// Environnement
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (isset($opts->e) && in_array($opts->e, $listEnv)) ? $opts->e : $defaultEnv);

// Tant que les library ne sont pas toutes de xxx/library de l'application
try {
    /*---------  Création de la Zend Application -----------*/
    $application = new Zend_Application(APPLICATION_ENV, APPLICATION_INI);
    $application->getBootstrap()->bootstrap(['db']);
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
} catch (Exception $e) {
    die($e->getMessage());
}

/*---------  Choix de la langue -----------*/
Zend_Registry::set('languages', ['fr', 'en']);
Zend_Registry::set('Zend_Locale', new Zend_Locale('fr'));


/**
 * add verbosity
 * @param $msg
 * @param string $colormsg
 * @param string $color
 * @param bool $newline
 */
function verbose($msg, $colormsg = '', $color = '', $newline = true)
{
    global $verbose;

    if ($verbose) {
        println($msg, $colormsg, $color, $newline);
    }
}

/**
 * add debug
 * @param $msg
 * @param string $colormsg
 * @param string $color
 * @param bool $newline
 */
function debug($msg, $colormsg = '', $color = '', $newline = true)
{
    global $debug;
    if ($debug) {
        println($msg, $colormsg, $color, $newline);
    }

}

/**  */
function loginfile($file, $msg)
{
    # devrait se faire en plusieurs etapes, open, log, close
    # Car: si beaucoup de logs, alors, le open/close est vraiment trop important.
    #
    if (isset($file)) {
        $monfichier = fopen($file, 'a');
        fputs($monfichier, $msg . PHP_EOL);
        fclose($monfichier);
    }
}

/**  */
function loganddebug($msg, $colormsg = '', $color = '', $file = '')
{
    debug($msg, $colormsg, $color);
    if (isset($file)) {
        loginfile($file, $msg . $colormsg);
    }
}

/**
 * Récupération/Affichage des paramètres dans la console
 */
function println($s = '', $v = '', $color = '', $newline = true)
{
    global $nocolor;
    $maybeNewline = '';
    if ($newline) {
        $maybeNewline = PHP_EOL;
    }
    if (($v == '') || $nocolor) {
        echo $s, $v, $maybeNewline;
    } else {
        $color_array = ['red' => '31m', 'green' => '32m', 'yellow' => '33m', 'blue' => '34m'];
        $colorStart = "\033[";
        $colorEnd = "\033[0m";
        $c = isset($color_array[$color]) ? $color_array[$color] : '30m';
        echo $s, $colorStart, $c, $v, $colorEnd, $maybeNewline;
    }
}

/**
 *
 * @param string $text
 * @param bool $required
 * @param string[] $values
 * @param string $default
 * @return string
 * @throws Exception if Default value not in $values (in case of $values not empty)
 */
function getParam($text, $required = true, $values = [], $default = '')
{

    $labels = [];  // Label correspondant aux valeurs (avec default)
    $listeFermee = count($values);
    $hasdefault = ($default !== '');
    $defaultNonFound = true;
    foreach ($values as $v) {
        $defaultP = '';
        if ($v == $default) {
            $defaultP = '[default]';
            $defaultNonFound = false;
        }
        $labels[] = $v . $defaultP;
    }
    if ($listeFermee && $defaultNonFound) {
        throw new Exception("Default value must be in list");
    }

    $text .= '(' . implode(', ', $labels) . ')';
    $res = '';
    while (true) {
        print($text . ' : ');
        $res = trim(fgets(STDIN));
        if ($res == '' && $hasdefault) {
            $res = $default;
        }

        if ($listeFermee && !in_array($res, $values)) {
            $res = ''; // on accepte pas la reponse!
        }

        if (!$required || $res !== '') {
            break;
        }
    }
    return $res;
}

/**
 * Verify that running user is ok.  If yes, return true.
 * If user is not the needed user, exit the program with error message.
 * If $exit is false, return false and don't exit...
 * @param string $spec
 * @param bool $exit
 * @return bool
 */
function need_user($spec, $exit = true)
{
    $processUser = posix_getpwuid(posix_geteuid());
    $user = $processUser['name'];
    $progname = $_SERVER["SCRIPT_FILENAME"];
    switch ($spec) {
        case 'apache':
            $neededUser = APACHE_USER;
            break;
        case 'root':
            $neededUser = 'root';
            break;
        default:
            error_log("need_user: unknown user specification ($spec)");
            exit(1);
    }

    if ($user == $neededUser) {
        return true;
    }
    if ($exit) {
        error_log("$progname script need to be run as $neededUser");
        exit(1);
    } else {
        return false;
    }
}
