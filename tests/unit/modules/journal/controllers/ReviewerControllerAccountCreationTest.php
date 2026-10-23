<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * The account created from a reviewer invitation must only receive the fields of the
 * account form: request data must never be able to set UID (which would turn the INSERT
 * into an UPDATE of an existing account), VALID, roles, etc.
 */
final class ReviewerControllerAccountCreationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewerController.php';
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privilegedKeyProvider(): array
    {
        return [
            'UID' => ['UID'],
            'lower case uid' => ['uid'],
            'mixed case Uid' => ['Uid'],
            'VALID' => ['VALID'],
            'ROLE' => ['ROLE'],
            'ROLEID' => ['ROLEID'],
            'TIME_REGISTERED' => ['TIME_REGISTERED'],
            'UUID' => ['UUID'],
            'key containing an allowed name' => ['EMAIL_VERIFIED'],
            'allowed name with a prefix' => ['XEMAIL'],
            'allowed name with a trailing space' => ['EMAIL '],
        ];
    }

    /**
     * @dataProvider privilegedKeyProvider
     */
    public function testPrivilegedKeysAreDropped(string $key): void
    {
        $filtered = \ReviewerController::filterAccountCreationData([
            'EMAIL' => 'a@example.org',
            $key => '1',
        ]);

        self::assertSame(['EMAIL' => 'a@example.org'], $filtered);
    }

    public function testLegitimateFieldsAreKeptWhateverTheirCase(): void
    {
        $data = [
            'USERNAME' => 'jdoe',
            'PASSWORD' => 'secret',
            'firstname' => 'Jane',
            'LastName' => 'Doe',
            'EMAIL' => 'jane@example.org',
            'SCREEN_NAME' => 'Jane Doe',
            'ORCID' => '0000-0000-0000-0000',
            'AFFILIATIONS' => [],
            'SOCIAL_MEDIAS' => '',
            'WEB_SITES' => '',
            'BIOGRAPHY' => '',
            'LANGUEID' => 'fr',
        ];

        self::assertSame($data, \ReviewerController::filterAccountCreationData($data));
    }

    public function testNumericKeysAndEmptyInputAreHandled(): void
    {
        self::assertSame([], \ReviewerController::filterAccountCreationData([]));
        self::assertSame([], \ReviewerController::filterAccountCreationData([0 => 'x', 1 => 'y']));
    }

    public function testFilteredDataCannotSetTheUidOfTheReviewer(): void
    {
        $reviewer = new \Episciences_Reviewer(\ReviewerController::filterAccountCreationData([
            'UID' => 42,
            'uid' => 43,
            'EMAIL' => 'a@example.org',
            'FIRSTNAME' => 'Jane',
        ]));

        self::assertEmpty($reviewer->getUid(), 'a new account must never carry an UID');
        self::assertSame('a@example.org', $reviewer->getEmail());
    }
}
