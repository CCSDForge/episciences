<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 12/01/18
 * Time: 08:57
 */

require_once 'Runable.php';

class Ccsd_Daemon  extends Ccsd_Runable {
    /**
     * @return mixed
     */
    /** @var Ccsd_Script */
    private $subprgm = null;

    /** @var int  */
    private $killAfter = 0;
    /** @var int */
    private $start_time;
    /** @var int  */
    private $sleepTime=30;
    /** @var int */
    private $nbinstances = 1;
    /** @var  mixed $event : communication beetween event waited by daemon to run program */
    private $event;
    /**
     * @param Ccsd_Script $subprgm
     * @param array $options
     *
     */
    function __construct($subprgm, $options)
    {
        $this->subprgm = $subprgm;
        foreach ($options as $option => $value) {
            switch ($option) {
                case 'kill_after':
                    if (is_int($value)) {
                        $this->killAfter = $value;
                        unset($options[$option]);
                    } else {
                        $this->verbose('kill_after option need an integer as value');
                    }
                    break;
                case 'sleep_time':
                    if (is_int($value)) {
                        $this->sleepTime = $value;
                        unset($options[$option]);
                    } else {
                        $this->verbose('sleep_time option need an integer as value');
                    }
                    break;
                case 'instances':
                    if (is_int($value) && ($value > 0)) {
                        $this->nbinstances = $value;
                        unset($options[$option]);
                    } else {
                        $this->verbose('instances option need a positive integer as value');
                    }
                    break;

                default:
                    $this->verbose($option . ' :Bad option for Ccsd_Daemon class');
            }
        }
    }

    /**
     * return $mixed
     */
    protected function wait_before_event() {
        // Can be to wait for a connection event, must be define in subclass
        return null;
    }

    protected function wait_after_event() {
        // Can be to wait beetween run
        if ($this -> sleepTime > 0) {
            sleep($this -> sleepTime);
        }
    }

    /**
     * @return int
     */
    private function fork()
    {
        ini_set('max_execution_time', 0);
        $this -> start_time = time();
        $this -> open();
        $this->debug("Run $this->nbinstances of process");
        for ($i=0;$i < $this -> nbinstances;$i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                /* Échec du fork*/
                echo "Échec du fork!\n";
                exit(2);
            } elseif ($pid) {
                /* le process père */
                continue;
            } else {
                /* Process Fils*/
                //Fait du processus courant un chef de session
                posix_setsid();
                //Changement du répertoire courant pour l'exécution du script
                //Retourne l'identifiant du processus fils
                return posix_getpid();
            }
        }
        // Processus pere a termine
        $this -> close();
        exit(0);
    }

    /**
     * After each run of script, maybe we want to stop
     * @return bool
     */
    public function stopCondition() {
        return (($this -> killAfter != 0) &&  (time() - $this -> start_time) > $this -> killAfter);
    }
    /**
     * Hook called before forking in parent
     */
    public function open() {}
    /**
     * Hook called after forking in parent, before exiting parent process.
     */
    public function close() {}

    /**
     * Daemonization of script.
     * We lauch some script instances and for each one, we call the run command.
     * An event can be trapped before executing a loop into an instance, and after an execution
     * By default: Nothing before, and waiting some seconds after
     * If param is false, then run without launching daemon
     * @param bool $daemonMode
     */
    public function main($daemonMode = true) {
        if ($daemonMode) {
            // True deaemonization !
            $this->fork();
            // Seuls les fils viennent ici
            while (true) {
                $this->event = $this->wait_before_event();
                $this->subprgm->run();
                if ($this->stopCondition()) {
                    $this->subprgm->debug("Stopped"); // On utilise les capacites de debug/verbose du scripts
                    exit(0);
                }
                $this->wait_after_event();
            }
        } else {
            // Run directly without daemons...
            $this->subprgm->run();
        }
    }
}
