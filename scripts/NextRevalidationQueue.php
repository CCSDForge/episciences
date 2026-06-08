<?php

use Episciences\Next\RevalidationService;
use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use scripts\Queue;

include_once __DIR__ . '/Queue.php';

class NextRevalidationQueue extends Queue
{
    private const NEXT_HTTP_TIMEOUT = 5.0;

    public function run(): void
    {
        $this->init();
        $this->processQueue();
    }

    public function processQueue(): void
    {
        $queueMessages = $this->getQueueMessages();

        if (count($queueMessages) === 0) {
            $this->logger->info('No Next.js revalidation messages to process');
            return;
        }

        if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
            $this->logger->error('NEXT_BASE_URL is not defined — skipping all revalidation messages');
            return;
        }

        $client   = new Client(['timeout' => self::NEXT_HTTP_TIMEOUT, 'http_errors' => false]);
        $endpoint = rtrim(NEXT_BASE_URL, '/') . '/api/revalidate';

        /** @var QueueMessage $queueMsg */
        foreach ($queueMessages as $queueMsg) {
            $rvcode = $queueMsg->getRvcode();
            if ($rvcode === null) {
                $this->logger->warning('Next.js revalidation message has null rvcode, discarding', [
                    'id' => $queueMsg->getId(),
                ]);
                $queueMsg->delete(true);
                continue;
            }

            $message = $queueMsg->getMessage();
            if (!is_array($message) || !isset($message['tag'])) {
                $this->logger->warning('Malformed Next.js revalidation message, discarding', [
                    'id' => $queueMsg->getId(),
                ]);
                $queueMsg->delete(true);
                continue;
            }

            $token = RevalidationService::resolveToken($rvcode);

            try {
                $response = $client->post($endpoint, [
                    'headers' => [
                        'Content-Type'        => 'application/json',
                        'x-episciences-token' => $token,
                    ],
                    'json' => [
                        'journalId' => $message['journalId'] ?? $rvcode,
                        'tag'       => $message['tag'],
                    ],
                ]);

                $status = $response->getStatusCode();

                if ($status === 200) {
                    $queueMsg->delete((bool) $this->getParam('delProcessed'));
                } elseif ($status >= 400 && $status < 500) {
                    // 4xx: permanent client error (wrong token, IP) — discard to avoid endless retry
                    $this->logger->warning('Next.js revalidation permanent client error, discarding message', [
                        'status'  => $status,
                        'journal' => $rvcode,
                        'tag'     => $message['tag'],
                        'body'    => substr($response->getBody()->getContents(), 0, 200),
                    ]);
                    $queueMsg->delete(true);
                } else {
                    // 5xx or other: transient — leave in queue for next cron run
                    $this->logger->warning('Next.js revalidation transient error, will retry', [
                        'status'  => $status,
                        'journal' => $rvcode,
                        'tag'     => $message['tag'],
                        'body'    => substr($response->getBody()->getContents(), 0, 200),
                    ]);
                }
            } catch (GuzzleException $e) {
                // Network / timeout: transient — leave in queue for next cron run
                $this->logger->error('Next.js revalidation HTTP error, will retry', [
                    'journal' => $rvcode,
                    'tag'     => $message['tag'],
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}

$nextRevalidationJob = new NextRevalidationQueue();
$nextRevalidationJob->setType(QueueMessageManager::TYPE_NEXT_REVALIDATION);
$nextRevalidationJob->run();
