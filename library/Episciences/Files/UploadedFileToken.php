<?php

declare(strict_types=1);

namespace Episciences\Files;

use JsonException;
use LogicException;

/**
 * Proof that a file was uploaded by a user: handed to the client with the upload response (see
 * FileController::uploadAction()) and sent back to remove the file or to attach it to an answer. A user can
 * only remove, or attach, the files they uploaded themselves, not the other files of the same directory.
 *
 * The token is an HMAC of the user, the storage directory, the file name and an expiry date, keyed with a global
 * secret (UPLOADED_FILE.HMAC_SECRET in config/pwd.json). Nothing is recorded on the server: concurrent uploads
 * cannot overwrite each other's record, whatever the session handler, and nothing grows with the uploads.
 *
 * An empty or short global secret (under MIN_SECRET_LENGTH characters, e.g. a placeholder left as is) is a
 * configuration error: signing throws and every token is invalid (fail closed).
 */
final class UploadedFileToken
{
    public const SECRET_CONSTANT_NAME = 'UPLOADED_FILE_HMAC_SECRET';

    /** Request field of the tokens, in the order of the attachments[] names (see es.fileupload.js) */
    public const POST_KEY = 'attachment_tokens';

    /** Lifetime of a token, in seconds: longer than an answer can reasonably take to write */
    public const TTL = 86400;

    /** Shortest secret accepted: 32 random bytes in hexadecimal (openssl rand -hex 32) */
    public const MIN_SECRET_LENGTH = 64;

    private const ALGORITHM = 'sha256';

    private string $secret;
    private int $now;

    /**
     * @param int $uid the user uploading or handling the file
     * @param string|null $secret the global secret; defaults to the configured one
     * @param int|null $now current timestamp; defaults to the current time
     */
    public function __construct(private readonly int $uid, ?string $secret = null, ?int $now = null)
    {
        $this->secret = $secret ?? self::configuredSecret();
        $this->now = $now ?? time();
    }

    public function isConfigured(): bool
    {
        return strlen($this->secret) >= self::MIN_SECRET_LENGTH;
    }

    /**
     * @param string $folder storage directory of the file
     * @param string $file name of the file in that directory
     * @throws LogicException when the global secret is not configured
     */
    public function sign(string $folder, string $file): string
    {
        $expiresAt = $this->now + self::TTL;

        return $expiresAt . '.' . $this->compute($folder, $file, $expiresAt);
    }

    /**
     * @param mixed $token the token taken from the request (any type: request values are untrusted)
     */
    public function isValid(string $folder, string $file, mixed $token): bool
    {
        if (!$this->isConfigured() || $file === '' || !is_string($token)
            || !preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/', $token, $matches)) {
            return false;
        }

        $expiresAt = (int)$matches[1];

        if ($expiresAt < $this->now) {
            return false;
        }

        try {
            return hash_equals($this->compute($folder, $file, $expiresAt), $matches[2]);
        } catch (JsonException) {
            // A name that is not valid UTF-8 cannot have been signed (see sign())
            return false;
        }
    }

    /**
     * The names whose token, at the same position in $tokens, is valid for the folder
     *
     * @param array<mixed> $names names submitted by the client
     * @param mixed $tokens tokens submitted by the client (any type: request values are untrusted)
     * @return array<int|string, string> accepted names, with their keys in $names
     */
    public function acceptedNames(string $folder, array $names, mixed $tokens): array
    {
        $tokens = is_array($tokens) ? $tokens : [];
        $accepted = [];

        foreach ($names as $key => $name) {
            if (is_string($name) && $this->isValid($folder, $name, $tokens[$key] ?? null)) {
                $accepted[$key] = $name;
            }
        }

        return $accepted;
    }

    private function compute(string $folder, string $file, int $expiresAt): string
    {
        if (!$this->isConfigured()) {
            throw new LogicException(sprintf('The uploaded file secret (%s) is not configured', self::SECRET_CONSTANT_NAME));
        }

        $payload = json_encode([$this->uid, self::normalizeFolder($folder), $file, $expiresAt], JSON_THROW_ON_ERROR);

        return hash_hmac(self::ALGORITHM, $payload, $this->secret);
    }

    /**
     * The folder is given with or without its trailing separator, depending on the caller
     */
    private static function normalizeFolder(string $folder): string
    {
        return rtrim($folder, '/\\') . '/';
    }

    private static function configuredSecret(): string
    {
        if (!defined(self::SECRET_CONSTANT_NAME)) {
            return '';
        }

        return (string)constant(self::SECRET_CONSTANT_NAME);
    }
}
