<?php

declare(strict_types=1);

namespace Episciences\Next\Messenger;

use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Resolves the Next.js revalidation token for a journal: reads
 * NEXT_REVALIDATION_TOKEN from data/{rvcode}/config/pwd.json, falling back to
 * the global NEXT_REVALIDATION_SECRET constant.
 *
 * Memoized per rvcode for the lifetime of the instance — a worker builds one
 * TokenResolver and keeps it for its whole run, so a rotated token is only
 * picked up when the worker recycles (bounded by --time-limit).
 *
 * $dataRoot is injectable so tests can point it at a temporary directory
 * instead of the real data/ tree.
 */
final class TokenResolver
{
    /** @var array<string, string> */
    private array $resolved = [];

    public function __construct(
        private readonly ?string $dataRoot,
        private readonly string $globalSecret,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public static function fromConstants(?LoggerInterface $logger = null): self
    {
        return new self(
            defined('APPLICATION_PATH') ? APPLICATION_PATH . '/../data' : null,
            defined('NEXT_REVALIDATION_SECRET') ? (string)NEXT_REVALIDATION_SECRET : '',
            $logger,
        );
    }

    public function resolve(string $rvcode): string
    {
        return $this->resolved[$rvcode] ??= $this->resolveUncached($rvcode);
    }

    private function resolveUncached(string $rvcode): string
    {
        if ($this->dataRoot !== null) {
            $configPath = $this->dataRoot . '/' . $rvcode . '/config/pwd.json';

            if (file_exists($configPath)) {
                $fileContent = file_get_contents($configPath);

                if ($fileContent !== false) {
                    try {
                        $config = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);

                        if (is_array($config) && isset($config['NEXT_REVALIDATION_TOKEN']) && $config['NEXT_REVALIDATION_TOKEN'] !== '') {
                            return (string)$config['NEXT_REVALIDATION_TOKEN'];
                        }
                    } catch (JsonException $e) {
                        $this->logger?->warning(sprintf(
                            'Could not parse journal config for token resolution (rvcode: %s): %s',
                            $rvcode,
                            $e->getMessage()
                        ));
                    }
                }
            }
        }

        return $this->globalSecret;
    }
}
