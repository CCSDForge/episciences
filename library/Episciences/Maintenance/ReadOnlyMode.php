<?php

declare(strict_types=1);

namespace Episciences\Maintenance;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use IntlDateFormatter;

/**
 * Read-only maintenance mode: the web application must not write to the database.
 *
 * Enabled with the "EPISCIENCES.READ_ONLY_MODE" key of config/pwd.json (constant EPISCIENCES_READ_ONLY_MODE),
 * which is read on every request: switching it takes effect immediately, without reloading PHP.
 * The optional "READ_ONLY_MODE_START" / "READ_ONLY_MODE_END" keys (ISO 8601) are the planned schedule,
 * displayed to visitors only (and announced beforehand when the start is in the future):
 * they never switch the mode on or off.
 */
final class ReadOnlyMode
{
    public const CONSTANT_NAME = 'EPISCIENCES_READ_ONLY_MODE';
    public const START_CONSTANT_NAME = 'EPISCIENCES_READ_ONLY_MODE_START';
    public const END_CONSTANT_NAME = 'EPISCIENCES_READ_ONLY_MODE_END';

    /** Retry-After when no planned end is known, or when it is already past */
    public const DEFAULT_RETRY_AFTER_SECONDS = 3600;

    /** Never ask clients to come back sooner than this */
    private const MIN_RETRY_AFTER_SECONDS = 60;

    private static ?bool $override = null;

    /** @var array{start: string, end: string}|null */
    private static ?array $scheduleOverride = null;

    public static function isEnabled(): bool
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $configured = defined(self::CONSTANT_NAME) ? constant(self::CONSTANT_NAME) : false;

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Force the state (tests only). Pass null to restore the configured value.
     */
    public static function setOverride(?bool $enabled): void
    {
        self::$override = $enabled;
    }

    /**
     * Force the planned schedule (tests only). Pass nulls to restore the configured values.
     */
    public static function setScheduleOverride(?string $start, ?string $end): void
    {
        self::$scheduleOverride = $start === null && $end === null ? null : ['start' => $start ?? '', 'end' => $end ?? ''];
    }

    /**
     * Planned start of the maintenance, null when not configured or not a valid date.
     */
    public static function getPlannedStart(): ?DateTimeImmutable
    {
        return self::parse(self::$scheduleOverride['start'] ?? self::configuredString(self::START_CONSTANT_NAME));
    }

    /**
     * Planned end of the maintenance, null when not configured or not a valid date.
     */
    public static function getPlannedEnd(): ?DateTimeImmutable
    {
        return self::parse(self::$scheduleOverride['end'] ?? self::configuredString(self::END_CONSTANT_NAME));
    }

    /**
     * A read-only maintenance is announced: not enabled yet, with a planned start in the future.
     */
    public static function isAnnounced(?DateTimeInterface $now = null): bool
    {
        if (self::isEnabled()) {
            return false;
        }

        $start = self::getPlannedStart();

        return $start !== null && $start > ($now ?? new DateTimeImmutable());
    }

    /**
     * Delay before clients should retry: until the planned end when it is in the future, a default otherwise.
     */
    public static function getRetryAfterSeconds(?DateTimeInterface $now = null): int
    {
        $end = self::getPlannedEnd();
        $now ??= new DateTimeImmutable();

        if ($end === null || $end <= $now) {
            return self::DEFAULT_RETRY_AFTER_SECONDS;
        }

        return max(self::MIN_RETRY_AFTER_SECONDS, $end->getTimestamp() - $now->getTimestamp());
    }

    /**
     * Localized date and time, in the time zone the date was configured with, followed by its UTC offset
     * (e.g. "20 octobre 2026 à 08:00 (UTC+02:00)"), so that visitors from any time zone can convert it.
     */
    public static function formatDateTime(DateTimeImmutable $date, string $locale): string
    {
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::SHORT, $date->getTimezone());
        $formatted = $formatter->format($date);

        if ($formatted === false) {
            $formatted = $date->format('Y-m-d H:i');
        }

        return $formatted . ' (UTC' . $date->format('P') . ')';
    }

    private static function configuredString(string $constantName): string
    {
        $value = defined($constantName) ? constant($constantName) : '';

        return is_string($value) ? $value : '';
    }

    private static function parse(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
