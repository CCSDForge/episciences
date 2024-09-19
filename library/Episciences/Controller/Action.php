<?php

class Episciences_Controller_Action extends Zend_Controller_Action
{

    public function url(array $urlOptions = [], $name = null, $reset = false, $encode = true): string
    {
        return (new Episciences_View_Helper_Url())->url($urlOptions, $name , $reset, $encode);
    }
}