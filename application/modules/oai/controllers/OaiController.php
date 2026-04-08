<?php


class OaiController  extends Zend_Controller_Action
{
    public function init(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }
    public function xslAction(): void
    {
        $this->forward('xsl', 'index');
    }
}