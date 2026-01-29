<?php

use Episciences\QueueMessage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use scripts\Queue;

include_once 'Queue.php';

class StatusQueue extends Queue
{

    public function run(): void
    {
        $this->init();
        $this->processQueue();
    }

    public function processQueue(): void
    {

        $currentRvCode = null;
        $currentPath = null;
        $currentEndPoint = null;
        $token = null;

        $queueMessages = $this->getQueueMessages();

        if (count($queueMessages) === 0) {
            $this->logger->info('No data to process');
            return;
        }

        /**
         * @var QueueMessage $queueMsg
         */

        foreach ($queueMessages as $queueMsg) {

            if (!$queueMsg->getRvcode()) {
                continue;
            }

            $this->initCurrentJournalParametersFromData($queueMsg, $currentRvCode, $currentPath, $currentEndPoint, $token);

            try {
                $response = $this->handleJob($currentEndPoint, $token, $queueMsg->getMessage());
                if ($response) {
                    $queueMsg->delete($queueMsg->getId(), true);
                }
            } catch (GuzzleException $e) {
                $this->logger->critical($e->getMessage());

            }
        }
    }

    /**
     * returns true if the job was handled successfully:
     * 200 OK
     * 201 Created
     * 202 Accepted
     * @param string $endPoint
     * @param string $token
     * @param string $data
     * @return bool
     * @throws GuzzleException
     */

    private function handleJob(string $endPoint, string $token, string $data): bool
    {
        $client = $this->getClient();
        $response = $client->post($endPoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ],
            'json' => $data

        ]);

        return in_array($response->getStatusCode(), [200, 201, 202]);
    }

    public function getClient(string $baseUri = ''): Client
    {
        return new Client([
            'timeout' => self::CLIENT_TIMEOUT,
            'base_uri' => $baseUri
        ]);

    }

    private function initCurrentJournalParametersFromData(
        QueueMessage $message,
        ?string      &$rvCode,
        ?string      &$configPath,
        ?string      &$endpoint,
        ?string      &$token
    ): void
    {
        if ($message->getRvcode() === $rvCode) {
            return;
        }

        $rvCode = $message->getRvcode();
        $configPath = sprintf('%s/../data/%s/config/pwd.json', __DIR__, $rvCode);

        if (!file_exists($configPath)) {
            return;
        }

        $fileContent = file_get_contents($configPath);
        try {
            $config = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->critical($e->getMessage());
            return;
        }

        if (empty($config['STATUS_ENDPOINT']) || empty($config['API_TOKEN'])) {
            return;
        }

        $endpoint = $config['STATUS_ENDPOINT'];
        $token = $config['API_TOKEN'];
    }

}

$statusJob = new StatusQueue();
$statusJob->setType(QueueMessage::TYPE_STATUS_CHANGED);
$statusJob->run();



