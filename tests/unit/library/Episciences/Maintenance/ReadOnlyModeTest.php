<?php

declare(strict_types=1);

namespace unit\library\Episciences\Maintenance;

use DateTimeImmutable;
use Episciences\Maintenance\ReadOnlyMode;
use PHPUnit\Framework\TestCase;

final class ReadOnlyModeTest extends TestCase
{
    protected function tearDown(): void
    {
        ReadOnlyMode::setOverride(null);
        ReadOnlyMode::setScheduleOverride(null, null);
    }

    public function testFollowsTheConfiguredConstant(): void
    {
        // bdd_const.php always defines it (false when pwd.json has no READ_ONLY_MODE key)
        self::assertSame(self::configuredState(), ReadOnlyMode::isEnabled());
    }

    public function testOverrideForcesTheState(): void
    {
        ReadOnlyMode::setOverride(true);
        self::assertTrue(ReadOnlyMode::isEnabled());

        ReadOnlyMode::setOverride(false);
        self::assertFalse(ReadOnlyMode::isEnabled());
    }

    public function testNullOverrideRestoresTheConfiguredState(): void
    {
        ReadOnlyMode::setOverride(true);
        ReadOnlyMode::setOverride(null);

        self::assertSame(self::configuredState(), ReadOnlyMode::isEnabled());
    }

    public function testPlannedScheduleIsReadWithItsTimeZone(): void
    {
        ReadOnlyMode::setScheduleOverride('2026-10-20T08:00:00+02:00', '2026-10-20T12:30:00+02:00');

        self::assertSame('2026-10-20T08:00:00+02:00', ReadOnlyMode::getPlannedStart()?->format(DATE_ATOM));
        self::assertSame('2026-10-20T12:30:00+02:00', ReadOnlyMode::getPlannedEnd()?->format(DATE_ATOM));
    }

    /**
     * @dataProvider missingOrInvalidDateProvider
     */
    public function testMissingOrInvalidDatesAreIgnored(string $value): void
    {
        ReadOnlyMode::setScheduleOverride($value, $value);

        self::assertNull(ReadOnlyMode::getPlannedStart());
        self::assertNull(ReadOnlyMode::getPlannedEnd());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingOrInvalidDateProvider(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'not a date' => ['tomorrow morning-ish'],
        ];
    }

    public function testMaintenanceIsAnnouncedOnlyBeforeItsPlannedStartAndWhileDisabled(): void
    {
        $now = new DateTimeImmutable('2026-10-19T10:00:00+02:00');
        ReadOnlyMode::setScheduleOverride('2026-10-20T08:00:00+02:00', null);

        ReadOnlyMode::setOverride(false);
        self::assertTrue(ReadOnlyMode::isAnnounced($now), 'future start');
        self::assertFalse(ReadOnlyMode::isAnnounced(new DateTimeImmutable('2026-10-20T09:00:00+02:00')), 'past start');

        ReadOnlyMode::setOverride(true);
        self::assertFalse(ReadOnlyMode::isAnnounced($now), 'already enabled');

        ReadOnlyMode::setOverride(false);
        ReadOnlyMode::setScheduleOverride(null, '2026-10-20T12:00:00+02:00');
        self::assertFalse(ReadOnlyMode::isAnnounced($now), 'no planned start');
    }

    public function testRetryAfterLastsUntilThePlannedEnd(): void
    {
        ReadOnlyMode::setScheduleOverride(null, '2026-10-20T12:00:00+02:00');

        self::assertSame(5400, ReadOnlyMode::getRetryAfterSeconds(new DateTimeImmutable('2026-10-20T10:30:00+02:00')));
    }

    public function testRetryAfterNeverAsksToComeBackImmediately(): void
    {
        ReadOnlyMode::setScheduleOverride(null, '2026-10-20T12:00:00+02:00');

        self::assertSame(60, ReadOnlyMode::getRetryAfterSeconds(new DateTimeImmutable('2026-10-20T11:59:50+02:00')));
    }

    public function testRetryAfterFallsBackToTheDefaultWithoutAFuturePlannedEnd(): void
    {
        $now = new DateTimeImmutable('2026-10-20T13:00:00+02:00');

        ReadOnlyMode::setScheduleOverride(null, '2026-10-20T12:00:00+02:00');
        self::assertSame(ReadOnlyMode::DEFAULT_RETRY_AFTER_SECONDS, ReadOnlyMode::getRetryAfterSeconds($now), 'past end');

        ReadOnlyMode::setScheduleOverride('2026-10-20T08:00:00+02:00', null);
        self::assertSame(ReadOnlyMode::DEFAULT_RETRY_AFTER_SECONDS, ReadOnlyMode::getRetryAfterSeconds($now), 'no end');
    }

    public function testDateIsLocalizedWithItsUtcOffset(): void
    {
        $date = new DateTimeImmutable('2026-10-20T08:00:00+02:00');

        $french = ReadOnlyMode::formatDateTime($date, 'fr');
        $english = ReadOnlyMode::formatDateTime($date, 'en');

        self::assertStringContainsString('octobre', $french);
        self::assertStringContainsString('08:00', $french);
        self::assertStringEndsWith('(UTC+02:00)', $french);
        self::assertStringContainsString('October', $english);
        self::assertStringEndsWith('(UTC+02:00)', $english);
    }

    /**
     * ext/intl rejects some time zones accepted by ext/date: the date must still be formatted.
     *
     * @dataProvider timeZoneRejectedByIntlProvider
     */
    public function testDateWithATimeZoneRejectedByIntlIsFormatted(string $value, string $expectedTime, string $expectedOffset): void
    {
        $formatted = ReadOnlyMode::formatDateTime(new DateTimeImmutable($value), 'en');

        self::assertStringContainsString('October', $formatted);
        self::assertStringContainsString($expectedTime, $formatted);
        self::assertStringEndsWith($expectedOffset, $formatted);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function timeZoneRejectedByIntlProvider(): array
    {
        return [
            'Zulu' => ['2026-10-20T08:00:00Z', '8:00', '(UTC+00:00)'],
            'abbreviation' => ['2026-10-20 08:00 CEST', '8:00', '(UTC+02:00)'],
        ];
    }

    /**
     * @dataProvider flagProvider
     */
    public function testFlagValueIsParsedAsABoolean(mixed $value, bool $expected): void
    {
        self::assertSame($expected, ReadOnlyMode::parseFlag($value));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function flagProvider(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            'string true' => ['true', true],
            'one' => ['1', true],
            'string off' => ['off', false],
            'empty' => ['', false],
            'typo stays disabled' => ['oui', false],
            'not a scalar stays disabled' => [['true'], false],
        ];
    }

    private static function configuredState(): bool
    {
        return filter_var(constant(ReadOnlyMode::CONSTANT_NAME), FILTER_VALIDATE_BOOLEAN);
    }
}
