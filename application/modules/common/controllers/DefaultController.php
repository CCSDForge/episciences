<?php


class DefaultController extends Zend_Controller_Action
{
    protected function isPostMaxSizeReached(): bool
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $serverParams = $request->getServer(); // $_SERVER
        try {
            $postMaxSize = Episciences_Tools::convertToBytes(ini_get('post_max_size'));
        } catch (Exception $e) {
            error_log($e->getMessage());
            return true;
        }

        return (isset($serverParams['CONTENT_LENGTH']) && (int)$serverParams['CONTENT_LENGTH'] > $postMaxSize);
    }
}