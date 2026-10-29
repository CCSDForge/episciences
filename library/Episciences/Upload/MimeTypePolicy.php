<?php

declare(strict_types=1);

namespace Episciences\Upload;

use InvalidArgumentException;
use JsonException;

/**
 * Which content types are acceptable for each file extension.
 *
 * Checking the extension alone lets a renamed file through, and checking the content type alone lets any
 * accepted type through under any accepted extension. The policy ties the two together: a file is acceptable
 * only if the content type detected from its bytes is one of those expected for its extension.
 */
final class MimeTypePolicy
{
    /** Key standing for "any extension" */
    private const ANY_EXTENSION = '*';

    /** @var array<string, list<string>> lowercase extension => accepted content types */
    private array $mimeTypesByExtension;

    /**
     * @param array<array-key, mixed> $mimeTypesByExtension extension (without dot) => accepted content types
     * @throws InvalidArgumentException when an extension has no content type or a value is not a string
     */
    public function __construct(array $mimeTypesByExtension)
    {
        $normalized = [];

        foreach ($mimeTypesByExtension as $extension => $mimeTypes) {
            $extension = strtolower((string)$extension);

            if ($extension === '' || !is_array($mimeTypes) || $mimeTypes === []) {
                throw new InvalidArgumentException(sprintf('No content type is declared for the extension "%s"', $extension));
            }

            foreach ($mimeTypes as $mimeType) {
                if (!is_string($mimeType) || $mimeType === '') {
                    throw new InvalidArgumentException(sprintf('Invalid content type for the extension "%s"', $extension));
                }
            }

            $normalized[$extension] = array_values(array_unique($mimeTypes));
        }

        $this->mimeTypesByExtension = $normalized;
    }

    /**
     * Policy that accepts the same content types whatever the extension is.
     *
     * @param list<string> $mimeTypes
     */
    public static function forAnyExtension(array $mimeTypes): self
    {
        return new self([self::ANY_EXTENSION => $mimeTypes]);
    }

    /**
     * @throws InvalidArgumentException when the file is not readable or is not valid JSON
     */
    public static function fromJsonFile(string $path, string $key = 'allowed_mimes_by_extension'): self
    {
        $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('The configuration file "%s" is not readable', $path));
        }

        try {
            $configuration = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('The configuration file "%s" is not valid JSON: %s', $path, $e->getMessage()), 0, $e);
        }

        if (!is_array($configuration) || !isset($configuration[$key]) || !is_array($configuration[$key])) {
            throw new InvalidArgumentException(sprintf('The configuration file "%s" has no "%s" entry', $path, $key));
        }

        return new self($configuration[$key]);
    }

    /**
     * Extension of a file name, lowercase and without the dot ('' when there is none).
     */
    public static function extensionOf(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    public function isExtensionAllowed(string $extension): bool
    {
        return $this->mimeTypesFor($extension) !== null;
    }

    /**
     * Whether a file with this extension may have this content type.
     */
    public function accepts(string $extension, string $mimeType): bool
    {
        return in_array($mimeType, $this->mimeTypesFor($extension) ?? [], true);
    }

    /**
     * Same policy, without these extensions.
     */
    public function without(string ...$extensions): self
    {
        $excluded = array_map('strtolower', $extensions);

        return new self(array_diff_key($this->mimeTypesByExtension, array_flip($excluded)));
    }

    /**
     * @return list<string> the extensions the policy knows about
     */
    public function extensions(): array
    {
        return array_values(array_diff(array_keys($this->mimeTypesByExtension), [self::ANY_EXTENSION]));
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->mimeTypesByExtension;
    }

    /**
     * Every content type the policy accepts, whatever the extension is.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->mimeTypesByExtension))));
    }

    /**
     * @return list<string>|null null when the extension is not accepted
     */
    private function mimeTypesFor(string $extension): ?array
    {
        return $this->mimeTypesByExtension[strtolower($extension)] ?? $this->mimeTypesByExtension[self::ANY_EXTENSION] ?? null;
    }
}
