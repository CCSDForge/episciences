<?php

namespace Episciences\Trait;

use Ccsd_Tools;
use Episciences_Review;
use Episciences_View_Helper_Url;

trait UrlBuilder
{
    final public function buildAdminPaperUrl(int $docId, array $journalOptions = []): string
    {
        if (!Ccsd_Tools::isFromCli()) {

            $adminPaperUrl = (new Episciences_View_Helper_Url())->url(
                [
                    'controller' => 'administratepaper',
                    'action' => 'view',
                    'id' => $docId
                ]);

            return SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $adminPaperUrl;
        }

        return $this->processUri($journalOptions) . sprintf('/administratepaper/view/id/%s', $docId);

    }

    final public function buildPublicPaperUrl(int $docId, array $journalOptions = []): string
    {


        if (!Ccsd_Tools::isFromCli()) {

            $adminPaperUrl = (new Episciences_View_Helper_Url())->url(
                [
                    'controller' => 'paper',
                    'action' => 'view',
                    'id' => $docId
                ]);

            return SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $adminPaperUrl;

        }

        return $this->processUri($journalOptions) . sprintf('/paper/view/id/%s', $docId);


    }

    final public function buildLostLoginUrl(array $journalOptions = []): string
    {

        if (!Ccsd_Tools::isFromCli()) {
            return sprintf('%s://%s%s', SERVER_PROTOCOL, $_SERVER['SERVER_NAME'], (new Episciences_View_Helper_Url())->url(['controller' => 'user', 'action' => 'lostlogin']));
        }

        return $this->processUri($journalOptions) . '/user/lostlogin';

    }

    private function processUri(array $journalOptions = []): string
    {

        if (isset($journalOptions['rvCode'])) {

            if (
                isset($journalOptions[Episciences_Review::IS_NEW_FRONT_SWITCHED], $_ENV['MANAGER_APPLICATION_URL']) &&
                $journalOptions[Episciences_Review::IS_NEW_FRONT_SWITCHED]
            ) {
                $uri = rtrim($_ENV['MANAGER_APPLICATION_URL'], DIRECTORY_SEPARATOR);
                $uri .= DIRECTORY_SEPARATOR;
                $uri .= $journalOptions['rvCode'];
                return $uri;
            }

            $uri = SERVER_PROTOCOL . '://';
            $uri .= $journalOptions['rvCode'] . '.' . DOMAIN;


            return $uri;

        }

        return '';

    }

}