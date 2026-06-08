<?php

namespace Episciences\Next;

use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
     *
     * @param string $rvcode Journal code (e.g. "epijinfo")
     * @param string $tag    Cache tag to invalidate (e.g. "article-42")
     */
    public static function enqueueTag(string $rvcode, string $tag): void
    {
        if (!defined('EPISCIENCES_ENABLE_NEXT_FRONT') || !EPISCIENCES_ENABLE_NEXT_FRONT) {
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
     * 4xx responses (wrong token, IP not whitelisted) are logged but NOT enqueued — retrying
     * a permanent client error is pointless.
     *
     * @param string $rvcode Journal code (e.g. "epijinfo")
     * @param string $tag    Cache tag to invalidate (e.g. "about-epijinfo")
     */
    public static function revalidateOrEnqueue(string $rvcode, string $tag): void
    {
        if (!defined('EPISCIENCES_ENABLE_NEXT_FRONT') || !EPISCIENCES_ENABLE_NEXT_FRONT) {
            return;
        }

        if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
            self::enqueueTag($rvcode, $tag);
            return;
        }

        $endpoint = rtrim(NEXT_BASE_URL, '/') . '/api/revalidate';
        $token    = self::resolveToken($rvcode);

        try {
            $client   = new Client(['timeout' => self::HTTP_TIMEOUT, 'http_errors' => false]);
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

            if ($status === 200) {
                return;
            }

            error_log(sprintf(
                '[RevalidationService] Non-200 response for tag "%s" (journal: %s): HTTP %d — %s',
                $tag,
                $rvcode,
                $status,
                substr($response->getBody()->getContents(), 0, 200)
            ));

            // 4xx = permanent client error (wrong token, IP not whitelisted) — do not retry
            if ($status >= 400 && $status < 500) {
                return;
            }

        } catch (GuzzleException $e) {
            error_log(sprintf(
                '[RevalidationService] HTTP error for tag "%s" (journal: %s): %s',
                $tag,
                $rvcode,
                $e->getMessage()
            ));
        }

        // Fallback: enqueue for retry by cron (5xx / network errors only reach here)
        self::enqueueTag($rvcode, $tag);
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
