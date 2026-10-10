<?php

namespace Episciences\Trait;

use Ccsd_Tools;
use Episciences_Review;
use Episciences_View_Helper_Url;

trait UrlBuilder
{
    /**
     * The method builds the URL to view an admin "paper" page.
     * It behaves differently depending on whether the code runs from a web request or from the command line (CLI).
     */
    final public static function buildAdminPaperUrl(int $docId, array $journalOptions = []): string
    {
        if (!Ccsd_Tools::isFromCli()) { // running in a web request

            $adminPaperUrl = (new Episciences_View_Helper_Url())->url(
                [
                    'controller' => 'administratepaper',
                    'action' => 'view',
                    'id' => $docId
                ]);

            return self::buildBaseUrl() . $adminPaperUrl;
        }

        // If running from CLI: construct the URL manually using journal options as base
        return self::processUri($journalOptions) . sprintf('/administratepaper/view/id/%s', $docId);

    }

    /**
     * The method builds the URL to view a public "paper" page.
     * It behaves differently depending on whether the code runs from a web request or from the command line (CLI).
     */
    final public static function buildPublicPaperUrl(int $docId, array $journalOptions = []): string
    {

        if (!Ccsd_Tools::isFromCli()) { // running in a web request

            $publicPaperUrl = (new Episciences_View_Helper_Url())->url(
                [
                    'controller' => 'paper',
                    'action' => 'view',
                    'id' => $docId
                ]);

            return self::buildBaseUrl() . $publicPaperUrl;

        }

        // If running from CLI: construct the URL manually using journal options as base
        return self::processUri($journalOptions) . sprintf('/paper/view/id/%s', $docId);


    }

    final public static function buildLostLoginUrl(array $journalOptions = []): string
    {

        if (!Ccsd_Tools::isFromCli()) {
            return sprintf('%s://%s%s', SERVER_PROTOCOL, $_SERVER['SERVER_NAME'], (new Episciences_View_Helper_Url())->url(['controller' => 'user', 'action' => 'lostlogin']));
        }

        return self::processUri($journalOptions) . '/user/lostlogin';

    }

    private static function processUri(array $journalOptions = []): string
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

    public static function buildBaseUrl(): string
    {
        return SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'];
    }

}