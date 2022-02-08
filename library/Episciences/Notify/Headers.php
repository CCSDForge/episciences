<?php

namespace Episciences\Notify;

trait Headers
{


    /**
     * @return void
     */
    public static function addInboxAutodiscoveryHeader(): void
    {
        header(self::getInboxHeaderString('Link: '));
    }

    /**
     * @return false|string
     */
    public static function addInboxAutodiscoveryLDN()
    {
        $ldJson['@context'] = "http://www.w3.org/ns/ldp";
        $ldJson['inbox'] = INBOX_URL;
        return json_encode($ldJson);
    }

    /**
     * @return string
     */
    public static function getInboxHeaderString($headerString = ''): string
    {
        return sprintf('%s<%s>; rel="http://www.w3.org/ns/ldp#inbox"', $headerString, INBOX_URL);
    }


}