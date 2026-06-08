<?php

use Episciences\Next\RevalidationService;
use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
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

            $tag    = $message['tag'];
            $status = RevalidationService::postRevalidation($rvcode, $tag, self::NEXT_HTTP_TIMEOUT);

            if ($status === 200) {
                $queueMsg->delete((bool) $this->getParam('delProcessed'));
            } elseif ($status >= 400 && $status < 500) {
                $this->logger->warning('Permanent client error, discarding revalidation message', [
                    'status'  => $status,
                    'journal' => $rvcode,
                    'tag'     => $tag,
                ]);
                $queueMsg->delete(true);
            } else {
                $this->logger->warning('Transient error, revalidation message will be retried', [
                    'status'  => $status,
                    'journal' => $rvcode,
                    'tag'     => $tag,
                ]);
            }
        }
    }
}

$nextRevalidationJob = new NextRevalidationQueue();
$nextRevalidationJob->setType(QueueMessageManager::TYPE_NEXT_REVALIDATION);
$nextRevalidationJob->run();
