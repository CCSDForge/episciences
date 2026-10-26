<?php

declare(strict_types=1);

namespace unit\modules\support;

use Zend_Controller_Response_HttpTestCase;

/**
 * What a dispatched action answered: the response, and what the action echoed.
 */
final class DispatchResult
{
    public function __construct(
        public readonly Zend_Controller_Response_HttpTestCase $response,
        public readonly string $output,
        public readonly bool $stopped
    ) {
    }

    public function statusCode(): int
    {
        return $this->response->getHttpResponseCode();
    }

    /**
     * Echoed output followed by the response body.
     */
    public function body(): string
    {
        return $this->output . $this->response->getBody();
    }

    /**
     * Target of the redirection, null when the action did not redirect.
     */
    public function redirectUrl(): ?string
    {
        if (!$this->response->isRedirect()) {
            return null;
        }

        return $this->header('Location');
    }

    /**
     * Value of the last response header named $name, null when it was not set.
     */
    public function header(string $name): ?string
    {
        $value = null;
        foreach ($this->response->getHeaders() as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                $value = (string)$header['value'];
            }
        }

        return $value;
    }

    /**
     * Body decoded as JSON.
     *
     * @throws \JsonException when the body is not JSON
     */
    public function json(): mixed
    {
        return json_decode($this->body(), true, 512, JSON_THROW_ON_ERROR);
    }
}
