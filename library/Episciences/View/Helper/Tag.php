<?php

class Episciences_View_Helper_Tag extends Zend_View_Helper_Abstract
{
    public static function Tag($tag)
    {
        if (!$tag) {
            return false;
        }

        return constant("Episciences_Mail_Tags::TAG_".$tag);
    }
}