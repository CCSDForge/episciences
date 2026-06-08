<?php

namespace Episciences\Next;

use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Next.js cache revalidation service.
 *
 * Two strategies:
 *  - enqueueTag() / enqueueTags()  — async via queue_messages (no web-request impact)
 *  - revalidateOrEnqueue()         — immediate HTTP POST, falls back to queue on transient failure
 *
 * All methods are no-ops when EPISCIENCES_ENABLE_NEXT_FRONT is not defined or falsy.
 */
class RevalidationService
{
    private const HTTP_TIMEOUT = 3.0;

    private static function isEnabled(): bool
    {
        return defined('EPISCIENCES_ENABLE_NEXT_FRONT') && (bool) EPISCIENCES_ENABLE_NEXT_FRONT;
    }

    /**
     * Enqueue multiple cache revalidation tags for a journal (async, queue-first).
     *
     * @param string   $rvcode Journal code (e.g. "epijinfo")
     * @param string[] $tags   Cache tags to invalidate
     */
    public static function enqueueTags(string $rvcode, array $tags): void
    {
        foreach ($tags as $tag) {
            self::enqueueTag($rvcode, $tag);
        }
    }

    /**
     * Enqueue a single cache revalidation tag for a journal (async, queue-first).
     */
    public static function enqueueTag(string $rvcode, string $tag): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $queue = new QueueMessage([
            'rvcode'  => $rvcode,
            'type'    => QueueMessageManager::TYPE_NEXT_REVALIDATION,
            'timeout' => QueueMessageManager::TYPE_NEXT_REVALIDATION_TIMEOUT,
            'message' => [
                'journalId' => $rvcode,
                'tag'       => $tag,
            ],
        ]);

        try {
            $queue->send();
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[RevalidationService] Failed to enqueue tag "%s" (journal: %s): %s',
                $tag,
                $rvcode,
                $e->getMessage()
            ));
        }
    }

    /**
     * Try an immediate HTTP POST to Next.js; fall back to queue only on transient failures.
     * 4xx responses (wrong token, IP not whitelisted) are logged but NOT enqueued.
     */
    public static function revalidateOrEnqueue(string $rvcode, string $tag): void
    {
        if (!self::isEnabled()) {
            return;
        }

        if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
            self::enqueueTag($rvcode, $tag);
            return;
        }

        $status = self::postRevalidation($rvcode, $tag);

        if ($status === 200 || ($status >= 400 && $status < 500)) {
            return; // 200 = success; 4xx = permanent error, do not enqueue
        }

        // 5xx or 0 (network / timeout) — enqueue for retry
        self::enqueueTag($rvcode, $tag);
    }

    /**
     * Execute a single HTTP POST to the Next.js revalidation endpoint.
     * Logs HTTP errors and network failures internally.
     * Returns the HTTP status code, or 0 on network / timeout error.
     */
    public static function postRevalidation(string $rvcode, string $tag, float $timeout = self::HTTP_TIMEOUT): int
    {
        if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
            return 0;
        }

        $endpoint = rtrim(NEXT_BASE_URL, '/') . '/api/revalidate';
        $token    = self::resolveToken($rvcode);

        try {
            $client   = new Client(['timeout' => $timeout, 'http_errors' => false]);
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type'        => 'application/json',
                    'x-episciences-token' => $token,
                ],
                'json' => [
                    'journalId' => $rvcode,
                    'tag'       => $tag,
                ],
            ]);

            $status = $response->getStatusCode();

            if ($status !== 200) {
                error_log(sprintf(
                    '[RevalidationService] Non-200 response for tag "%s" (journal: %s): HTTP %d — %s',
                    $tag,
                    $rvcode,
                    $status,
                    substr($response->getBody()->getContents(), 0, 200)
                ));
            }

            return $status;
        } catch (GuzzleException $e) {
            error_log(sprintf(
                '[RevalidationService] HTTP error for tag "%s" (journal: %s): %s',
                $tag,
                $rvcode,
                $e->getMessage()
            ));
            return 0;
        }
    }

    /**
     * Resolve the revalidation token for a journal.
     * Reads NEXT_REVALIDATION_TOKEN from data/{rvcode}/config/pwd.json,
     * falls back to the global NEXT_REVALIDATION_SECRET constant.
     */
    public static function resolveToken(string $rvcode): string
    {
        if (defined('APPLICATION_PATH')) {
            $configPath = APPLICATION_PATH . '/../data/' . $rvcode . '/config/pwd.json';

            if (file_exists($configPath)) {
                $fileContent = file_get_contents($configPath);
                if ($fileContent !== false) {
                    try {
                        $config = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
                        if (is_array($config) && isset($config['NEXT_REVALIDATION_TOKEN']) && $config['NEXT_REVALIDATION_TOKEN'] !== '') {
                            return (string) $config['NEXT_REVALIDATION_TOKEN'];
                        }
                    } catch (\JsonException $e) {
                        error_log(sprintf(
                            '[RevalidationService] Could not parse journal config for token resolution (rvcode: %s): %s',
                            $rvcode,
                            $e->getMessage()
                        ));
                    }
                }
            }
        }

        return defined('NEXT_REVALIDATION_SECRET') ? (string) NEXT_REVALIDATION_SECRET : '';
    }
}
