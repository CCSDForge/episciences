<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 16/11/16
 * Time: 14:26
 */

class ProgressBar
{
    // progress bar total number of steps
    protected $_steps_total = 50;
    // character opening the progress bar
    protected $_opening_char = '';
    // character closing the progress bar
    protected $_closing_char = '';
    // character reprensenting a completed bar
    protected $_full_bar_char = '|';
    // character reprensenting an empty bar
    protected $_empty_bar_char = ' ';
    // do we want to show an estimation of remaining time ?
    protected $_show_remaining_time = true;

    protected $_init_time;
    protected $_output = '';
    // number of completed steps
    protected $_steps_complete = 0;
    // current progress, in percent
    protected $_progress = 0;
    protected $_started = false;


    public function __construct()
    {
        $this->_init_time = microtime(true);
    }

    public function setStepsComplete($i)
    {
        $this->_steps_complete = $i;
    }

    public function getStepsComplete()
    {
        return $this->_steps_complete;
    }

    public function setStepsTotal($i)
    {
        $this->_steps_total = $i;
    }

    public function getStepsTotal()
    {
        return $this->_steps_total;
    }

    public function calculateProgress()
    {
        $this->setProgress(round(100 * $this->getStepsComplete()) / $this->getStepsTotal());
    }

    /**
     * return current progress in percent, based on completed steps
     * @return int
     */
    public function getProgress()
    {
        return $this->_progress;
    }

    /**
     * set progress, in percent
     * @param $i
     */
    public function setProgress($i)
    {
        $this->_progress = $i;
        $this->setStepsFromProgress();
    }

    /**
     * add progress to current progress, in percent
     * @param $i
     */
    public function addProgress($i)
    {
        $this->_progress += $i;
        $this->setStepsFromProgress();
    }

    /**
     * set completed steps, based on current progress
     */
    protected function setStepsFromProgress()
    {
        $steps = floor(($this->getProgress() * $this->getStepsTotal()) / 100);
        if ($steps > $this->getStepsTotal()) {
            $steps = $this->getStepsTotal();
        }
        $this->setStepsComplete($steps);
    }

    public function getInitTime()
    {
        return $this->_init_time;
    }

    public function setFullBarChar($char)
    {
        $this->_full_bar_char = $char;
    }

    public function getFullBarChar()
    {
        return $this->_full_bar_char;
    }

    public function setEmptyBarChar($char)
    {
        $this->_empty_bar_char = $char;
    }

    public function getEmptyBarChar()
    {
        return $this->_empty_bar_char;
    }

    public function getOpeningChar()
    {
        return $this->_opening_char;
    }

    public function setOpeningChar($char)
    {
        $this->_opening_char = $char;
    }

    public function getClosingChar()
    {
        return $this->_closing_char;
    }

    public function setClosingChar($char)
    {
        $this->_closing_char = $char;
    }

    public function setShowRemainingTime($bool)
    {
        $this->_show_remaining_time = $bool;
    }

    public function getShowRemainingTime()
    {
        return $this->_show_remaining_time;
    }

    public function getOutput()
    {
        $output = $this->getOpeningChar()
            . str_pad(
                str_repeat($this->getFullBarChar(), $this->getStepsComplete()),
                $this->getStepsTotal(),
                $this->getEmptyBarChar())
            . $this->getClosingChar()
            .  ' ' . $this->getProgress() . '%';

        if ($this->getShowRemainingTime() == true) {
            $output .= ' remaining time: ' . $this->getRemainingTime() . 's';
        }


        return $output;
    }

    public function start()
    {
        $this->_started = true;
    }

    public function stop()
    {
        $this->_started = false;
    }

    public function isStarted()
    {
        return $this->_started;
    }

    /**
     * return estimated remaining time before end of script, in seconds
     * @return float
     */
    public function getRemainingTime()
    {
        return round($this->getStepAverageDuration() * ($this->getStepsTotal() - $this->getStepsComplete()));
    }

    /**
     * return estimated total running time, in seconds
     * @return float
     */
    public function getEstimatedTotalTime()
    {
        return round($this->getRunningTime() + $this->getRemainingTime());
    }

    /**
     * return running time since script started, in seconds
     */
    public function getRunningTime()
    {
        return round(microtime(true) - $this->_init_time);
    }

    /**
     * return average time needed for a step, in seconds
     */
    public function getStepAverageDuration()
    {
        return round($this->getRunningTime() / ($this->getStepsComplete() + 1));
    }

    /**
     * erase everything on bash screen
     */
    private function clear_screen()
    {
        echo chr(27) . '[2J';
    }
}