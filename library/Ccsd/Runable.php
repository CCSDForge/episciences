<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 22/01/18
 * Time: 17:28
 */
abstract class Ccsd_Runable {
    /** To be runable, we just need a callback function
     * @param Zend_Console_Getopt $getopt
     */
    abstract public function main($getopt);

    const BASH_RED = 'red';
    const BASH_BLUE = 'blue';
    const BASH_GREEN = 'green';
    const BASH_CYAN = 'cyan';
    const BASH_PURPLE = 'purple';
    const BASH_LIGHT_GREY = 'light_grey';
    const BASH_DARK_GREY = 'dark_grey';
    const BASH_LIGHT_BLUE = 'light_blue';
    const BASH_LIGHT_GREEN = 'light_green';
    const BASH_LIGHT_CYAN = 'light_cyan';
    const BASH_LIGHT_RED = 'light_red';
    const BASH_LIGHT_PURPLE = 'light_purple';
    const BASH_YELLOW = 'yellow';
    const BASH_BOLD = 'bold';
    const BASH_DEFAULT = 'default';

    const SEVERITY_DEBUG = 6;
    const SEVERITY_TRACE = 5;
    const SEVERITY_INFO = 4;
    const SEVERITY_WARNING = 3;
    const SEVERITY_ERROR = 2;
    const SEVERITY_CRITICAL = 1;

    protected static $bashColors = [
        self::BASH_RED          => "\033[0;31m",
        self::BASH_BLUE         => "\033[0;34m",
        self::BASH_GREEN        => "\033[0;32m",
        self::BASH_CYAN         => "\033[0;36m",
        self::BASH_PURPLE       => "\033[0;35m",
        self::BASH_LIGHT_GREY   => "\033[0;37m",
        self::BASH_DARK_GREY    => "\033[1;30m",
        self::BASH_LIGHT_BLUE   => "\033[1;34m",
        self::BASH_LIGHT_GREEN  => "\033[1;32m",
        self::BASH_LIGHT_CYAN   => "\033[1;36m",
        self::BASH_LIGHT_RED    => "\033[1;31m",
        self::BASH_LIGHT_PURPLE => "\033[1;35m",
        self::BASH_YELLOW       => "\033[1;33m",
        self::BASH_BOLD         => "\033[1m",
        self::BASH_DEFAULT      => "\033[0m",
    ];


    private $isDebug = false;
    private $isVerbose = false;
    private $debugLevel = 3;  // ERROR, CRITICAL and WARNINGS are displayed
    /** @var bool  */
    protected $colorize = true;

    /**
     * Verify that running user is ok.  If yes, return true.
     * If user is not the needed user, exit the program with error message.
     * If $exit is false, return false and don't exit...
     * @param string $spec
     * @param bool   $exit
     * @return bool
     */
    function need_user($spec, $exit = true)
    {
        $processUser = posix_getpwuid(posix_geteuid());
        $user = $processUser['name'];
        $progname = $_SERVER["SCRIPT_FILENAME"];
        switch ($spec) {
            case 'nobody':
            case 'httpd':
            case 'apache':
                $neededUser = defined('APACHE_USER') ? APACHE_USER : $spec;
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
    /**
     * @param $color string
     * @return bool
     */
    private function colorIsOk($color) {
        return ($color && array_key_exists($color, static::$bashColors));
    }
    /**
     * Add maybe color array message
     * @param string $message
     * @param string $color
     * @param bool $bold
     * @return string
     */
    private function colorize($message , $color, $bold = false) {
        if ($message != '') {
            $prefix = '';
            $postfix = '';
            if ($this->colorize) {
                if ($bold) {
                    $prefix .= static::$bashColors[static::BASH_BOLD];
                }
                if ($this->colorIsOk($color)) {
                    $prefix .= static::$bashColors[$color];
                }
                // On remet la color a normal
                $postfix = static::$bashColors[static::BASH_DEFAULT];
            }
            $message = $prefix . $message . $postfix;
        }
        return $message;
    }
    /**
     * Récupération/Affichage des paramètres dans la console: le suffixe $s est affiche sans couleur, puis le message est colorise si demande
     * @param string $s
     * @param string $v
     * @param string $color
     * @param bool   $newline
     */
    function println($s = '', $v = '', $color = self::BASH_DEFAULT, $newline = true)
    {
        $maybeNewline = '';
        if ($newline) {
            $maybeNewline = PHP_EOL;
        }
        if (($v == '') || $this->colorize) {
            echo $s, $v, $maybeNewline;
        } else {
            $codecolor = isset(self::$bashColors[$color]) ? self::$bashColors[$color] : self::BASH_DEFAULT;
            $colorEnd = self::BASH_DEFAULT;
            echo $s, $codecolor, $v, $colorEnd, $maybeNewline;
        }
    }


    /**
     * @param string $prompt
     * @return bool|string
     */
    function readline_terminal($prompt = '') {
        $prompt && print $prompt;
        $terminal_device = '/dev/tty';
        $h = fopen($terminal_device, 'r');
        if ($h === false) {
            #throw new RuntimeException("Failed to open terminal device $terminal_device");
            return false; # probably not running in a terminal.
        }
        $line = rtrim(fgets($h),"\r\n");
        fclose($h);
        return $line;
    }

    /**
     * @param string $prompt
     * @param bool $default
     * @return bool
     */
    public function y_or_n($prompt, $default) {
        $ok   = false;
        $YorN = $default ? "O/n" : "o/N";
        $rep  = $default;
        while (!$ok) {
            $input = strtolower($this->readline_terminal("$prompt($YorN): "));
            switch ($input) {
                case 'o':
                case 'y':
                case 'oui':
                case 'yes':
                    $ok = true;
                    $rep = true;
                    break;
                case 'n':
                case 'N':
                    $ok = true;
                    $rep = false;
                    break;
                case '':
                    if (($default===true) || ($default===false)) {
                        $ok = true;
                        $rep =  $default;
                    } else {
                        print "Please enter 'y' or 'n' (or 'o')\n";;
                    }
                    break;
                default:
                    print "Please enter 'y' or 'n' (or 'o')\n";
            }
        }
        return $rep;
    }


    /**
     * ask for a user input, and return it
     * @param string $message      [optional] <p>
     *                             if specified, message will be shown before waiting for user input
     *                             </p>
     * @param array  $valid_inputs [optional]
     * @param string $color
     * @param string $default
     * @return string
     */
    public function ask($message = '', $valid_inputs = [], $color = null, $default = '')
    {
        // format and display message
        $this->println('', $this->colorize($message, $color, true), null , false);

        // if valid inputs array is specified, display it so the user can choose
        $valid_inputs_string = array();
        foreach ($valid_inputs as $i => $valid_input) {
            $valid_inputs_string[] = $this->colorize($i,static::$bashColors[static::BASH_YELLOW]) . ': ' . $valid_input;
        }
        $this->println(implode(PHP_EOL, $valid_inputs_string), null, false);

        // get user input
        echo $this->colorize('> ', null, true);
        $input = trim(fgets(STDIN));

        // set empty user input to default, if $default id specified
        if (empty($input) && $default != '') {
            $input = $default;
        }

        // check that user input is valid
        if (!empty($valid_inputs) && !array_key_exists($input, $valid_inputs)) {
            $this->displayError('Invalid input.');
            $input = $this->ask('Please pick one of these:', $valid_inputs, $color,$default);
        }

        return $input;
    }

    /**
     * display a dark grey formatted text
     * @param      $message
     */
    public function debug($message)
    {
        if ($this->debugLevel >= self::SEVERITY_DEBUG) {
            $this->println('[:debug] ', $message, static::BASH_DARK_GREY);
        }
    }

    /**
     * display a light grey formatted text
     * @param      $message
     */
    public function displayTrace($message)
    {
        if ($this->debugLevel >= self::SEVERITY_TRACE) {
            $this->println('[:trace] ', $message, static::BASH_LIGHT_GREY);
        }
    }

    /**
     * display a blue formatted text
     * @param      $message
     */
    public function displayInfo($message)
    {
        if ($this->debugLevel >= self::SEVERITY_INFO) {
            $this->println('[:info] ', $message, static::BASH_BLUE);
        }
    }

    /** Alias
     * @param string $message
     */
    public function verbose($message)
    {
        $this->displayInfo($message);
    }

    /**
     * display a yellow formatted text
     * @param      $message
     */
    public function displayWarning($message)
    {
        if ($this->debugLevel >= self::SEVERITY_WARNING) {
            $this->println('[:warning] ', $message, static::BASH_YELLOW);
        }
    }

    /**
     * display a red formatted text
     * @param      $message
     */
    public function displayError($message)
    {
        if ($this->debugLevel >= self::SEVERITY_ERROR) {
            $this->println('[:error] ', $message, static::BASH_RED);
        }
    }

    /**
     * display a red bold formatted text
     * @param      $message
     */
    public function displayCritical($message)
    {
        if ($this->debugLevel >= self::SEVERITY_CRITICAL) {
            $this->println('[:critical] ', $message, static::BASH_RED);
        }
    }

    /**
     * display a green formatted text
     * @param      $message
     */
    public function displaySuccess($message)
    {
        $this->println('[:success] ', $message, static::BASH_GREEN);
    }

    /** @return bool */
    public function isVerbose()
    {
        return ($this->isVerbose);
    }

    /** @return bool */
    public function isDebug()
    {
        return ($this->isDebug);
    }

    protected $_log_enabled = true;

    /**
     *
     */
    public function enableLogs()
    {
        $this->_log_enabled = true;
    }
    /**
     *
     */
    public function disableLogs()
    {
        $this->_log_enabled = false;
    }

    /**
     * setter
     * @param $value
     */
    public function setVerbosity($value) {
        $this -> debugLevel = $value;
    }

    /**
     * @param bool $value
     */
    public function setDebug($value) {
        $this->isDebug = $value;
    }

    /**
     * @param bool $value
     */
    public function setVerbose($value) {
        $this->isVerbose = $value;
    }
}