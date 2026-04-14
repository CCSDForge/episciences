<?php

use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use scripts\Queue;

include_once 'Queue.php';

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

        $client = new Client(['timeout' => self::NEXT_HTTP_TIMEOUT]);

        /** @var QueueMessage $queueMsg */
        foreach ($queueMessages as $queueMsg) {
            $rvcode = $queueMsg->getRvcode();
            if ($rvcode === null) {
                continue;
            }

            $message = $queueMsg->getMessage();
            if (!is_array($message) || !isset($message['tag'])) {
                $this->logger->warning('Malformed Next.js revalidation message, skipping', [
                    'id' => $queueMsg->getId(),
                ]);
                continue;
            }

            if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
                $this->logger->error('NEXT_BASE_URL is not defined — skipping revalidation');
                return;
            }

            $endpoint = rtrim(NEXT_BASE_URL, '/') . '/api/revalidate';
            $token    = $this->resolveToken($rvcode);

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

                if ($response->getStatusCode() === 200) {
                    $queueMsg->delete((bool) $this->getParam('delProcessed'));
                } else {
                    $this->logger->warning('Next.js revalidation returned non-200', [
                        'status'  => $response->getStatusCode(),
                        'journal' => $rvcode,
                        'tag'     => $message['tag'],
                        'body'    => substr($response->getBody()->getContents(), 0, 200),
                    ]);
                }
            } catch (GuzzleException $e) {
                $this->logger->error('Next.js revalidation HTTP error', [
                    'journal' => $rvcode,
                    'tag'     => $message['tag'],
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolveToken(string $rvcode): string
    {
        $configPath = sprintf('%s/../data/%s/config/pwd.json', __DIR__, $rvcode);

        if (file_exists($configPath)) {
            $fileContent = file_get_contents($configPath);
            if ($fileContent !== false) {
                try {
                    $config = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($config) && isset($config['NEXT_REVALIDATION_TOKEN']) && $config['NEXT_REVALIDATION_TOKEN'] !== '') {
                        return (string) $config['NEXT_REVALIDATION_TOKEN'];
                    }
                } catch (\JsonException $e) {
                    $this->logger->warning('Could not parse journal config for token resolution', [
                        'rvcode' => $rvcode,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }
        }

        return defined('NEXT_REVALIDATION_SECRET') ? (string) NEXT_REVALIDATION_SECRET : '';
    }
}

$nextRevalidationJob = new NextRevalidationQueue();
$nextRevalidationJob->setType(QueueMessageManager::TYPE_NEXT_REVALIDATION);
$nextRevalidationJob->run();