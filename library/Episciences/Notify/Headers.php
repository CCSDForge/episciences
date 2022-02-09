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
     * @return string
     */
    public static function addInboxAutodiscoveryLDN(): string
    {
        $ldJson['@context'] = "http://www.w3.org/ns/ldp";
        $ldJson['inbox'] = INBOX_URL;

        try {
            $jsonEncoded = json_encode($ldJson, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $jsonEncoded = '';
        }

        return $jsonEncoded;

    }

    /**
     * @param string $headerString
     * @return string
     */
    public static function getInboxHeaderString(string $headerString = ''): string
    {
        return sprintf('%s<%s>; rel="http://www.w3.org/ns/ldp#inbox"', $headerString, INBOX_URL);
    }
}