<?php

namespace Episciences\Tools\Http\Exceptions;
use Exception;

class FileGetContentsException extends Exception
{
    protected string $url;

    public function __construct(string $url, string $message, int $code = 0)
    {
        $this->url = $url;
        parent::__construct($message, $code);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

}