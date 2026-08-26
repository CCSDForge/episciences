<?php

declare(strict_types=1);

namespace Episciences\Next\Messenger\Handler;

use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use Episciences\Next\Messenger\TokenResolver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * POSTs a single revalidateTag() request to the Next.js front. The
 * throw/return taxonomy below is deliberate — see docs/next-revalidation.md
 * and the plan this class was built from for the full reasoning; summary:
 *
 * - 2xx                         -> success, ack
 * - 3xx (Guzzle already follows redirects, so a visible one is the limit)
 *                               -> UnrecoverableMessageHandlingException (NEXT_BASE_URL is wrong)
 * - 408 / 425 / 429             -> plain RuntimeException (transient, retry)
 * - other 4xx                   -> UnrecoverableMessageHandlingException (bad token/IP/payload/route)
 * - 5xx                         -> plain RuntimeException (transient, retry)
 * - network/DNS/timeout         -> plain RuntimeException wrapping the Guzzle exception
 *
 * Critically, none of the retryable cases use
 * RecoverableMessageHandlingException: Symfony's
 * SendFailedMessageForRetryListener::shouldRetry() returns true
 * unconditionally for any RecoverableExceptionInterface, which would bypass
 * MultiplierRetryStrategy's bounded retry count entirely and retry forever.
 * A plain RuntimeException goes through the bounded strategy as intended.
 */
final class RevalidateTagMessageHandler
{
    private const HTTP_TIMEOUT = 5.0;
    private const HTTP_CONNECT_TIMEOUT = 2.0;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly TokenResolver $tokenResolver,
        private readonly string $baseUrl,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RevalidateTagMessage $message): void
    {
        // A message can only exist if the flag was on at enqueue time; there
        // is nothing to preserve by revalidating late once it's off.
        if (!defined('EPISCIENCES_ENABLE_NEXT_FRONT') || !EPISCIENCES_ENABLE_NEXT_FRONT) {
            return;
        }

        $endpoint = rtrim($this->baseUrl, '/') . '/api/revalidate';
        $token = $this->tokenResolver->resolve($message->rvcode);

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-episciences-token' => $token,
                ],
                'json' => [
                    'journalId' => $message->rvcode,
                    'tag' => $message->tag,
                ],
                'timeout' => self::HTTP_TIMEOUT,
                'connect_timeout' => self::HTTP_CONNECT_TIMEOUT,
                'http_errors' => false,
            ]);
        } catch (TransferException $e) {
            $this->logger?->warning(sprintf(
                'Next.js revalidation request failed for tag "%s" (journal: %s): %s',
                $message->tag,
                $message->rvcode,
                $e->getMessage()
            ));

            throw new RuntimeException(sprintf(
                'Next.js revalidation request failed for tag "%s" (journal: %s): %s',
                $message->tag,
                $message->rvcode,
                $e->getMessage()
            ), 0, $e);
        }

        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return;
        }

        if ($status >= 300 && $status < 400) {
            $this->logger?->error(sprintf(
                'Next.js revalidation got an unexpected HTTP %d redirect for tag "%s" (journal: %s) — check NEXT_BASE_URL.',
                $status,
                $message->tag,
                $message->rvcode
            ));

            throw new UnrecoverableMessageHandlingException(sprintf(
                'Next.js revalidation got an unexpected HTTP %d redirect for tag "%s" (journal: %s) — check NEXT_BASE_URL.',
                $status,
                $message->tag,
                $message->rvcode
            ));
        }

        if (in_array($status, [408, 425, 429], true)) {
            $this->logger?->warning(sprintf(
                'Next.js revalidation got a transient HTTP %d for tag "%s" (journal: %s).',
                $status,
                $message->tag,
                $message->rvcode
            ));

            throw new RuntimeException(sprintf(
                'Next.js revalidation got a transient HTTP %d for tag "%s" (journal: %s).',
                $status,
                $message->tag,
                $message->rvcode
            ));
        }

        if ($status >= 400 && $status < 500) {
            $errorBody = substr($response->getBody()->getContents(), 0, 200);

            $this->logger?->error(sprintf(
                'Next.js revalidation got a permanent HTTP %d for tag "%s" (journal: %s): %s',
                $status,
                $message->tag,
                $message->rvcode,
                $errorBody
            ));

            throw new UnrecoverableMessageHandlingException(sprintf(
                'Next.js revalidation got a permanent HTTP %d for tag "%s" (journal: %s): %s',
                $status,
                $message->tag,
                $message->rvcode,
                $errorBody
            ));
        }

        // 5xx
        $this->logger?->warning(sprintf(
            'Next.js revalidation got HTTP %d for tag "%s" (journal: %s).',
            $status,
            $message->tag,
            $message->rvcode
        ));

        throw new RuntimeException(sprintf(
            'Next.js revalidation got HTTP %d for tag "%s" (journal: %s).',
            $status,
            $message->tag,
            $message->rvcode
        ));
    }
}
