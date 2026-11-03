<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PaperDefaultController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

/**
 * PaperDefaultController::extractModalRecipientEmails(): CC/BCC addresses posted by the mail modals.
 *
 * The method reads the POST data only: it is called on a controller instantiated without its constructor.
 */
final class PaperDefaultControllerRecipientEmailsTest extends TestCase
{
    private PaperDefaultController $controller;

    protected function setUp(): void
    {
        $this->controller = (new ReflectionClass(PaperDefaultController::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function extract(array $data, string $field = 'cc'): array
    {
        $method = new ReflectionMethod(PaperDefaultController::class, 'extractModalRecipientEmails');

        return $method->invoke($this->controller, $data, $field);
    }

    private static function tags(string ...$values): string
    {
        return (string)json_encode(array_map(static fn(string $value): array => ['value' => $value], $values));
    }

    public function testTagsAreReadFromTheHiddenField(): void
    {
        self::assertSame(
            ['alice@example.org', 'bob@example.org'],
            $this->extract(['hidden_cc' => self::tags('alice@example.org', 'bob@example.org')])
        );
    }

    public function testTagsKeepOnlyTheAddressOfANamedRecipient(): void
    {
        self::assertSame(
            ['alice@example.org'],
            $this->extract(['hidden_cc' => self::tags('Alice Smith <alice@example.org>')])
        );
    }

    public function testTagsWithoutValueAreSkipped(): void
    {
        $hidden = (string)json_encode([['value' => ''], ['label' => 'no value'], ['value' => 'bob@example.org']]);

        self::assertSame(['bob@example.org'], $this->extract(['hidden_cc' => $hidden]));
    }

    public function testTagsTakePrecedenceOverTheTextField(): void
    {
        self::assertSame(
            ['alice@example.org'],
            $this->extract(['hidden_cc' => self::tags('alice@example.org'), 'cc' => 'bob@example.org'])
        );
    }

    public function testTextFieldIsSplitOnSemicolonsAndTrimmed(): void
    {
        self::assertSame(
            ['alice@example.org', 'bob@example.org'],
            $this->extract(['cc' => ' alice@example.org ;; bob@example.org; '])
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableTags(): iterable
    {
        yield 'malformed JSON' => ['[{"value": "alice@'];
        yield 'empty list' => ['[]'];
        yield 'tags without value' => ['[{"value": ""}]'];
        yield 'not a list' => ['"alice@example.org"'];
    }

    /**
     * @dataProvider unusableTags
     */
    public function testTextFieldIsUsedWhenTheTagsAreUnusable(string $hidden): void
    {
        self::assertSame(['bob@example.org'], $this->extract(['hidden_cc' => $hidden, 'cc' => 'bob@example.org']));
    }

    public function testAskEditorsSubformTagsAreRead(): void
    {
        self::assertSame(
            ['alice@example.org'],
            $this->extract(['askEditors' => ['hidden_cc' => self::tags('alice@example.org')]])
        );
    }

    public function testAskEditorsSubformTextFieldIsRead(): void
    {
        self::assertSame(
            ['alice@example.org', 'bob@example.org'],
            $this->extract(['askEditors' => ['cc' => 'alice@example.org;bob@example.org']])
        );
    }

    public function testTopLevelFieldsTakePrecedenceOverTheAskEditorsSubform(): void
    {
        $data = [
            'hidden_cc' => self::tags('alice@example.org'),
            'askEditors' => ['hidden_cc' => self::tags('bob@example.org')],
        ];

        self::assertSame(['alice@example.org'], $this->extract($data));
    }

    public function testEachFieldReadsItsOwnKeys(): void
    {
        $data = ['hidden_cc' => self::tags('alice@example.org'), 'bcc' => 'bob@example.org'];

        self::assertSame(['bob@example.org'], $this->extract($data, 'bcc'));
    }

    public function testNoRecipientWhenNothingIsPosted(): void
    {
        self::assertSame([], $this->extract([]));
        self::assertSame([], $this->extract(['hidden_cc' => '', 'cc' => ' ; ']));
    }
}
