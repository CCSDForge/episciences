<?php

declare(strict_types=1);

namespace Episciences\Notify;

/**
 * Validates COAR Notify payloads for "Offer" patterns (Request Review /
 * Request Endorsement) as defined in the COAR Notify 1.0.1 specification.
 *
 * Pure value object — no side effects, no external dependencies, fully testable.
 *
 * @see https://notify.coar-repositories.org/specification/
 *
 * Pattern-specific requirements (e.g. the `context` property required by
 * "Announce Endorsement") are NOT checked here and must be handled upstream.
 */
final class PayloadValidator
{
    private const ACTIVITY_STREAMS_URI = 'https://www.w3.org/ns/activitystreams';

    private const COAR_NOTIFY_URIS = [
        'https://coar-notify.net',        // preferred
        'https://purl.org/coar/notify',   // deprecated but still valid
    ];

    /**
     * @param string[] $expectedType
     */
    public function __construct(
        private readonly array  $expectedType,
        private readonly string $expectedOriginInbox,
        private readonly string $expectedTargetDomain
    ) {}

    public function validate(array $payload): ValidationResult
    {
        // --- @context --------------------------------------------------------
        // REQUIRED: must be an array containing the ActivityStreams URI and one
        // of the COAR Notify context URIs.
        $atContext = $payload['@context'] ?? null;
        if (!is_array($atContext)) {
            return ValidationResult::failure("'@context' must be an array of URIs");
        }
        if (!in_array(self::ACTIVITY_STREAMS_URI, $atContext, true)) {
            return ValidationResult::failure(
                sprintf("'@context' must include %s", self::ACTIVITY_STREAMS_URI)
            );
        }
        if (count(array_intersect(self::COAR_NOTIFY_URIS, $atContext)) === 0) {
            return ValidationResult::failure(
                sprintf("'@context' must include one of: %s", implode(', ', self::COAR_NOTIFY_URIS))
            );
        }

        // --- id --------------------------------------------------------------
        // REQUIRED: must be a URI (URN:UUID recommended, HTTP URI also allowed).
        $id = $payload['id'] ?? null;
        if ($id === null || (!filter_var($id, FILTER_VALIDATE_URL) && !str_starts_with((string) $id, 'urn:'))) {
            return ValidationResult::failure(
                sprintf("'id' must be a URI (HTTP or URN): %s", $id ?? 'null')
            );
        }

        // --- origin ----------------------------------------------------------
        // REQUIRED: must have id (HTTP URI) and type.
        $originId = $payload['origin']['id'] ?? null;
        if ($originId === null || !filter_var($originId, FILTER_VALIDATE_URL)) {
            return ValidationResult::failure(
                sprintf("'origin.id' must be an HTTP URI: %s", $originId ?? 'null')
            );
        }
        if (empty($payload['origin']['type'])) {
            return ValidationResult::failure("'origin.type' is required");
        }

        // Origin inbox must match the configured source.
        $originInbox = $payload['origin']['inbox'] ?? null;
        if ($originInbox !== $this->expectedOriginInbox) {
            return ValidationResult::failure(
                sprintf("the 'origin' property doesn't match: %s", $this->expectedOriginInbox)
            );
        }

        // --- type ------------------------------------------------------------
        // REQUIRED: all expected types must be present in the payload.
        $matchedTypes = array_intersect($this->expectedType, $payload['type'] ?? []);
        if ($matchedTypes !== $this->expectedType) {
            return ValidationResult::failure(
                sprintf("the 'type' property doesn't match: %s", implode(', ', $this->expectedType))
            );
        }

        // --- object ----------------------------------------------------------
        // REQUIRED: id must be an HTTP URI (landing page); type must be present.
        // ietf:item is required for Offer patterns (Request Review / Request Endorsement)
        // and must contain id (HTTP URI), type, and mediaType.
        $objectId = $payload['object']['id'] ?? null;
        if ($objectId === null || !filter_var($objectId, FILTER_VALIDATE_URL)) {
            return ValidationResult::failure(
                sprintf("'object.id' must be an HTTP URI: %s", $objectId ?? 'null')
            );
        }
        if (empty($payload['object']['type'])) {
            return ValidationResult::failure("'object.type' is required");
        }
        $ietfItem = $payload['object']['ietf:item'] ?? null;
        if (!is_array($ietfItem)) {
            return ValidationResult::failure("'object.ietf:item' is required");
        }
        $ietfItemId = $ietfItem['id'] ?? null;
        if ($ietfItemId === null || !filter_var($ietfItemId, FILTER_VALIDATE_URL)) {
            return ValidationResult::failure(
                sprintf("'object.ietf:item.id' must be an HTTP URI: %s", $ietfItemId ?? 'null')
            );
        }
        if (empty($ietfItem['type'])) {
            return ValidationResult::failure("'object.ietf:item.type' is required");
        }
        if (empty($ietfItem['mediaType'])) {
            return ValidationResult::failure("'object.ietf:item.mediaType' is required");
        }

        // --- target ----------------------------------------------------------
        // REQUIRED: id must be a valid URL containing the expected domain;
        // type must be present; inbox must be an HTTP URI.
        $targetId = $payload['target']['id'] ?? null;
        if (
            $targetId === null ||
            !filter_var($targetId, FILTER_VALIDATE_URL) ||
            !str_contains($targetId, $this->expectedTargetDomain)
        ) {
            return ValidationResult::failure(
                sprintf('Not valid notify target => %s', $targetId ?? 'null')
            );
        }
        if (empty($payload['target']['type'])) {
            return ValidationResult::failure("'target.type' is required");
        }
        $targetInbox = $payload['target']['inbox'] ?? null;
        if ($targetInbox === null || !filter_var($targetInbox, FILTER_VALIDATE_URL)) {
            return ValidationResult::failure(
                sprintf("'target.inbox' must be an HTTP URI: %s", $targetInbox ?? 'null')
            );
        }

        return ValidationResult::success();
    }
}
