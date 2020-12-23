<?php

trait Ccsd_Form_Trait_EventOnClick {
     
    protected $_event_on_click = "";

    public function getEvent_on_click ()
    {
        return $this->_event_on_click;
    }
    
    public function setEvent_on_click ( $str = "" )
    {
        $this->_event_on_click = $str;
        return $this;
    }
}