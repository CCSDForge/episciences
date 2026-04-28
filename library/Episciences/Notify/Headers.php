<?php

declare(strict_types=1);

namespace Episciences\Notify;

trait Headers
{

    /**
     * @return void
     */
    final public function addInboxAutodiscoveryHeader(): void
    {
        header($this->getInboxHeaderString('Link: '));
    }

    /**
     * @return string
     */
    final public  function addInboxAutodiscoveryLDN(): string
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
    final public function getInboxHeaderString(string $headerString = ''): string
    {
        return sprintf('%s<%s>; rel="http://www.w3.org/ns/ldp#inbox"', $headerString, INBOX_URL);
    }
}