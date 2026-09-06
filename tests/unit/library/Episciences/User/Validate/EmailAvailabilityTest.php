<?php

namespace unit\library\Episciences\User\Validate;

use Episciences\User\Validate\EmailAvailability;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\Validate\EmailAvailability
 */
class EmailAvailabilityTest extends TestCase
{
    public function testIsValidFalseWhenFinderSignalsAnotherUid(): void
    {
        $validator = new EmailAvailability(42, 'A record matching email (%value%) was found.', static fn(string $email): array => [42, 77]);

        self::assertFalse($validator->isValid('a@b.org'));
    }

    public function testGetMessagesContainsSubstitutedValueOnFailure(): void
    {
        $validator = new EmailAvailability(42, 'A record matching email (%value%) was found.', static fn(string $email): array => [42, 77]);

        $validator->isValid('a@b.org');

        self::assertSame(
            ['emailNotAvailable' => 'A record matching email (a@b.org) was found.'],
            $validator->getMessages()
        );
    }

    public function testIsValidTrueWhenOnlyExcludedUidMatches(): void
    {
        $validator = new EmailAvailability(42, EmailAvailability::DEFAULT_MESSAGE, static fn(string $email): array => [42]);

        self::assertTrue($validator->isValid('a@b.org'));
        self::assertSame([], $validator->getMessages());
    }

    public function testIsValidTrueWhenFinderReturnsNothing(): void
    {
        $validator = new EmailAvailability(0, EmailAvailability::DEFAULT_MESSAGE, static fn(string $email): array => []);

        self::assertTrue($validator->isValid('a@b.org'));
    }

    public function testIsValidWithExcludeUidZeroRejectsAnyMatch(): void
    {
        $validator = new EmailAvailability(0, EmailAvailability::DEFAULT_MESSAGE, static fn(string $email): array => [77]);

        self::assertFalse($validator->isValid('a@b.org'));
    }

    public function testMessagesAreResetBetweenCalls(): void
    {
        $calls = 0;
        $finder = function (string $email) use (&$calls): array {
            $calls++;
            return $calls === 1 ? [77] : [];
        };
        $validator = new EmailAvailability(0, EmailAvailability::DEFAULT_MESSAGE, $finder);

        self::assertFalse($validator->isValid('a@b.org'));
        self::assertNotEmpty($validator->getMessages());

        self::assertTrue($validator->isValid('a@b.org'));
        self::assertSame([], $validator->getMessages());
    }
}
