<?php

/**
 * The remote OAI-PMH repository answered with an HTTP 5xx status: the repository is
 * unavailable or overloaded, so the same request may succeed later.
 *
 * Extends Ccsd_Error so that the callers catching Ccsd_Error keep handling it.
 */
class Ccsd_Oai_ServerErrorException extends Ccsd_Error
{
    private int $httpStatus;
    private string $url;
    private ?int $retryAfter;

    /**
     * @param int $httpStatus HTTP status of the response (500 to 599)
     * @param string $url requested URL
     * @param int|null $retryAfter seconds requested by the Retry-After header, null when absent
     */
    public function __construct(int $httpStatus, string $url, ?int $retryAfter = null)
    {
        $this->httpStatus = $httpStatus;
        $this->url = $url;
        $this->retryAfter = $retryAfter;

        parent::__construct(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE, $httpStatus);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Seconds requested by the Retry-After header; null when absent or given as an HTTP date
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
