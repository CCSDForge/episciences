<?php

class Ccsd_Website_Navigation_Page_Rss extends Ccsd_Website_Navigation_Page
{
    protected $_feed    =   '';
    
    
    public function setOptions($options = array())
    {
        parent::setOptions($options);
        if (isset($options['feed'])) {
            $this->_feed = $options['feed'];
        } else if (isset($options['infos']['href'])) {
            $this->_feed = $options['infos']['href'];
        }
    }

    public function setFeed($value) {
        $this->_feed = $value;
    }
    
} 