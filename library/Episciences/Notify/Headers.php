<?php
namespace Episciences\Notify;

trait Headers {


    /**
     * @return void
     */
    public static function addInboxAutodiscoveryHeader(): void
    {
        header(self::getInboxHeaderString());
    }

    /**
     * @return false|string
     */
    public static function addInboxAutodiscoveryLDN() {
        $ldJson['@context'] = "http://www.w3.org/ns/ldp";
        $ldJson['inbox'] = INBOX_URL;
        return json_encode($ldJson);
    }

    /**
     * @return string
     */
    public static function getInboxHeaderString(): string
    {
        return sprintf('Link: <%s>; rel="http://www.w3.org/ns/ldp#inbox"', INBOX_URL);
    }


}