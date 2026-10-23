<?php

class Ccsd_Website_Navigation_Page_Rss extends Ccsd_Website_Navigation_Page
{
    protected $_feed    =   '';


    public function __construct(mixed ...$args)
    {
        triggedier_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        if (get_parent_class($this) !== false && method_exists(get_parent_class($this), '__construct')) {
            parent::__construct(...$args);
        }
    }

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
