<?php

require_once APPLICATION_PATH.'/modules/common/controllers/ErrorDefaultController.php';

class ErrorController extends ErrorDefaultController
{
    public function init(): void
    {
        Zend_Layout::getMvcInstance()->setLayout('portal');
    }
}