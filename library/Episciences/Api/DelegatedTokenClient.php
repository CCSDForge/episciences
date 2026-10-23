<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Gets from the Episciences API a short-lived token on behalf of the connected user,
 * used to display the papers of the statistics to the journal administrators.
 * The API keeps the control of the rights of the user in the journal.
 */
class DelegatedTokenClient
{
    public const ENDPOINT = 'token/delegated';
    private const TIMEOUT = 5;
    private const MIN_SECRET_LENGTH = 32;

    public function __construct(
        private readonly Client $client,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $secret
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->apiUrl !== '' && strlen($this->secret) >= self::MIN_SECRET_LENGTH;
    }

    /**
     * @return string|null the token, or null if the feature is disabled or the exchange failed
     */
    public function get(int $uid, string $code): ?string
    {
        if (!$this->isEnabled() || $uid < 1 || $code === '') {
            return null;
        }

        $body = $this->buildRequest($uid, $code, time(), bin2hex(random_bytes(16)));

        try {
            $response = $this->client->request('POST', $this->apiUrl . self::ENDPOINT, [
                'json' => $body,
                'headers' => ['Accept' => 'application/json'],
                'timeout' => self::TIMEOUT,
                'connect_timeout' => self::TIMEOUT,
            ]);
            $content = json_decode((string)$response->getBody(), true, 4, JSON_THROW_ON_ERROR);
        } catch (GuzzleException|JsonException $e) {
            $this->logger->warning('Delegated token not obtained', ['uid' => $uid, 'code' => $code, 'error' => $e->getMessage()]);
            return null;
        }

        $token = is_array($content) ? ($content['token'] ?? null) : null;

        if (!is_string($token) || $token === '') {
            $this->logger->warning('Delegated token not obtained: unexpected answer', ['uid' => $uid, 'code' => $code]);
            return null;
        }

        return $token;
    }

    /**
     * @return array{uid: int, code: string, iat: int, nonce: string, signature: string}
     */
    public function buildRequest(int $uid, string $code, int $iat, string $nonce): array
    {
        return [
            'uid' => $uid,
            'code' => $code,
            'iat' => $iat,
            'nonce' => $nonce,
            'signature' => hash_hmac('sha256', sprintf('%d|%s|%d|%s', $uid, $code, $iat, $nonce), $this->secret),
        ];
    }
}
