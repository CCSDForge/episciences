<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences\Mail\RecipientDirectory;
use Episciences\Mail\RecipientListValidator;
use PHPUnit\Framework\TestCase;

final class RecipientListValidatorTest extends TestCase
{
    private const RVID = 3;
    private const PAPERID = 90;

    /**
     * Accounts of the journal: UID => email
     */
    private const ACCOUNTS = [
        1 => 'author@example.org',
        2 => 'reviewer@example.org',
        3 => 'editor@example.org',
    ];

    private const PAPER_ROLES = [1 => 'author', 2 => 'reviewer', 3 => 'editor'];

    private static function validator(): RecipientListValidator
    {
        $directory = new class (self::ACCOUNTS, self::PAPER_ROLES) implements RecipientDirectory {
            /**
             * @param array<int, string> $accounts
             * @param array<int, string> $paperRoles
             */
            public function __construct(private readonly array $accounts, private readonly array $paperRoles)
            {
            }

            public function resolveEmails(int $rvid, array $emails): array
            {
                return array_intersect_key(array_flip($this->accounts), array_flip($emails));
            }

            public function reviewUserEmails(int $rvid, array $uids): array
            {
                return array_intersect_key($this->accounts, array_flip($uids));
            }

            public function paperRoles(int $rvid, int $paperId, bool $withReviewers): array
            {
                return $this->paperRoles;
            }

            public function temporaryReviewerEmails(int $rvid, int $paperId): array
            {
                return ['invited@example.org'];
            }
        };

        return new RecipientListValidator($directory, self::RVID);
    }

    /**
     * @param list<array<string, mixed>> $recipients
     */
    private static function json(array $recipients): string
    {
        return json_encode($recipients, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array{errors: list<array{message: string, argument: string|int|null}>} $result
     * @return list<string>
     */
    private static function messages(array $result): array
    {
        return array_column($result['errors'], 'message');
    }

    public function testMainRecipientIsRequired(): void
    {
        $result = self::validator()->validate(['hidden_cc' => self::json([['key' => 'a', 'value' => 'x@example.org']])]);

        self::assertTrue($result['isDetectedErrors']);
        self::assertSame([RecipientListValidator::ERROR_MISSING], self::messages($result));
    }

    public function testAnyRecipientIsEnoughWhenNotStrict(): void
    {
        $result = self::validator()->validate(['hidden_bcc' => self::json([['key' => 'a', 'value' => 'x@example.org']])], false);

        self::assertFalse($result['isDetectedErrors']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedLists(): array
    {
        return [
            'not JSON' => ['[{'],
            'object instead of list' => ['{"a": {"value": "x@example.org"}}'],
            'scalar' => ['"x@example.org"'],
            'item without value' => ['[{"key": "a"}]'],
            'value not a string' => ['[{"value": ["x@example.org"]}]'],
            'too deep' => ['[{"value": "x@example.org", "uid": [[[1]]]}]'],
        ];
    }

    /**
     * @dataProvider malformedLists
     */
    public function testMalformedListIsRefused(string $json): void
    {
        $result = self::validator()->validate(['hidden_to' => $json, 'hidden_cc' => self::json([['value' => 'x@example.org']])], false);

        self::assertContains(RecipientListValidator::ERROR_INVALID_LIST, self::messages($result));
    }

    public function testInvalidAddressIsReported(): void
    {
        $result = self::validator()->validate(['hidden_to' => self::json([['key' => 'a', 'value' => 'Somebody <not-an-address>']])]);

        self::assertSame([RecipientListValidator::ERROR_INVALID_EMAIL], self::messages($result));
        self::assertSame('not-an-address', $result['errors'][0]['argument']);
    }

    public function testNumberOfRecipientsPerFieldIsLimited(): void
    {
        $many = [];
        for ($i = 0; $i <= RecipientListValidator::MAX_PER_FIELD; $i++) {
            $many[] = ['key' => (string)$i, 'value' => 'user' . $i . '@example.org'];
        }

        $result = self::validator()->validate(['hidden_to' => self::json($many)]);

        self::assertSame([RecipientListValidator::ERROR_TOO_MANY], self::messages($result));
        self::assertSame(RecipientListValidator::MAX_PER_FIELD, $result['errors'][0]['argument']);
    }

    public function testTotalNumberOfRecipientsIsLimited(): void
    {
        $post = [];
        foreach (['to', 'cc', 'bcc'] as $field) {
            $list = [];
            for ($i = 0; $i < 40; $i++) {
                $list[] = ['key' => $field . $i, 'value' => $field . $i . '@example.org'];
            }
            $post['hidden_' . $field] = self::json($list);
        }

        $result = self::validator()->validate($post);

        self::assertSame([RecipientListValidator::ERROR_TOO_MANY], self::messages($result));
        self::assertSame(RecipientListValidator::MAX_TOTAL, $result['errors'][0]['argument']);
    }

    public function testUidIsKeptOnlyWhenTheAccountHasThisAddress(): void
    {
        $result = self::validator()->validate(['hidden_to' => self::json([
            ['key' => 'a', 'value' => 'Editor <editor@example.org>', 'uid' => 3],
            ['key' => 'b', 'value' => 'Someone <outside@example.org>', 'uid' => 3],
            ['key' => 'c', 'value' => 'outside@example.org', 'uid' => 999],
        ])]);

        self::assertFalse($result['isDetectedErrors']);
        self::assertSame([3, null, null], array_column($result['recipients']['to'], 'uid'));
    }

    public function testTypedAddressOfAnAccountIsResolved(): void
    {
        $result = self::validator()->validate(['hidden_to' => self::json([['key' => 'a', 'value' => '&lt;EDITOR@example.org&gt;']])]);

        self::assertSame(3, $result['recipients']['to'][0]['uid']);
    }

    public function testAuthorAndReviewerOfThePaperCannotBothBeVisibleRecipients(): void
    {
        $result = self::validator()->validate([
            'hidden_to' => self::json([['key' => 'a', 'value' => 'author@example.org']]),
            'hidden_cc' => self::json([['key' => 'b', 'value' => 'reviewer@example.org']]),
        ], true, self::PAPERID);

        self::assertSame([RecipientListValidator::ERROR_ANONYMITY], self::messages($result));
    }

    public function testReviewerInBccKeepsTheAnonymity(): void
    {
        $result = self::validator()->validate([
            'hidden_to' => self::json([['key' => 'a', 'value' => 'author@example.org']]),
            'hidden_bcc' => self::json([['key' => 'b', 'value' => 'reviewer@example.org']]),
        ], true, self::PAPERID);

        self::assertFalse($result['isDetectedErrors']);
    }

    public function testAnonymityIsOnlyCheckedForAMailAboutAPaper(): void
    {
        $result = self::validator()->validate([
            'hidden_to' => self::json([['key' => 'a', 'value' => 'author@example.org']]),
            'hidden_cc' => self::json([['key' => 'b', 'value' => 'reviewer@example.org']]),
        ]);

        self::assertFalse($result['isDetectedErrors']);
    }

    public function testReviewerWithoutAuthorIsAllowed(): void
    {
        $result = self::validator()->validate([
            'hidden_to' => self::json([['key' => 'b', 'value' => 'reviewer@example.org']]),
            'hidden_cc' => self::json([['key' => 'c', 'value' => 'editor@example.org']]),
        ], true, self::PAPERID);

        self::assertFalse($result['isDetectedErrors']);
    }

    public function testReviewerInvitedWithoutAccountIsFoundByAddress(): void
    {
        $result = self::validator()->validate([
            'hidden_to' => self::json([['key' => 'a', 'value' => 'author@example.org']]),
            'hidden_cc' => self::json([['key' => 'b', 'value' => 'Invited Reviewer <INVITED@example.org>']]),
        ], true, self::PAPERID);

        self::assertSame([RecipientListValidator::ERROR_ANONYMITY], self::messages($result));
    }

    public function testReviewerInvitedWithoutAccountAloneIsAllowed(): void
    {
        $result = self::validator()->validate([
            'hidden_to' => self::json([['key' => 'b', 'value' => 'invited@example.org']]),
            'hidden_cc' => self::json([['key' => 'c', 'value' => 'editor@example.org']]),
        ], true, self::PAPERID);

        self::assertFalse($result['isDetectedErrors']);
    }
}
