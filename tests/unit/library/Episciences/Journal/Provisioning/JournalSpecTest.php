<?php

namespace unit\library\Episciences\Journal\Provisioning;

use Episciences\Journal\Provisioning\JournalSpec;
use Episciences_Review;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * fromInput() needs a SymfonyStyle/InputInterface and, in interactive mode, the database
 * (Episciences_ReviewsManager::getList() for the template choice) — only the constructor's
 * pure invariant checks and assertValidCode() are unit-tested here.
 */
class JournalSpecTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private function makeSpec(array $overrides = []): JournalSpec
    {
        $defaults = [
            'code' => 'sandbox-journal',
            'name' => 'Sandbox Journal',
            'subtitle' => '',
            'templateRvcode' => 'dmtcs',
            'adminUid' => 1,
            'piwikId' => 0,
            'status' => Episciences_Review::DISABLED,
            'isNewFrontSwitched' => false,
            'resetDoi' => false,
        ];
        $args = array_merge($defaults, $overrides);

        return new JournalSpec(...array_values($args));
    }

    public function testValidSpecIsAccepted(): void
    {
        $spec = $this->makeSpec();
        $this->assertSame('sandbox-journal', $spec->code);
        $this->assertSame('dmtcs', $spec->templateRvcode);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCodeProvider(): array
    {
        return [
            'too short' => ['ab'],
            'uppercase' => ['Sandbox'],
            'starts with hyphen' => ['-sandbox'],
            'ends with hyphen' => ['sandbox-'],
            'underscore' => ['sand_box'],
            'empty' => [''],
            'reserved: portal' => ['portal'],
            'reserved: oai' => ['oai'],
            'reserved: default' => ['default'],
        ];
    }

    /** @dataProvider invalidCodeProvider */
    public function testInvalidCodeIsRejected(string $code): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['code' => $code]);
    }

    public function testMinimalValidCodeIsAccepted(): void
    {
        // 3 characters is the shortest the pattern allows: first + last + at least one middle char.
        $spec = $this->makeSpec(['code' => 'ab1']);
        $this->assertSame('ab1', $spec->code);
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['name' => '']);
    }

    public function testTooLongNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['name' => str_repeat('a', 2001)]);
    }

    public function testTooLongSubtitleIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['subtitle' => str_repeat('a', 256)]);
    }

    public function testEmptyTemplateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['templateRvcode' => '']);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidAdminUidProvider(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    /** @dataProvider invalidAdminUidProvider */
    public function testInvalidAdminUidIsRejected(int $adminUid): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['adminUid' => $adminUid]);
    }

    public function testNegativePiwikIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['piwikId' => -1]);
    }

    public function testInvalidStatusIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeSpec(['status' => '2']);
    }

    public function testAssertValidCodeAcceptsValidCode(): void
    {
        $this->expectNotToPerformAssertions();
        JournalSpec::assertValidCode('valid-code-1');
    }

    public function testAssertValidCodeRejectsReservedCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JournalSpec::assertValidCode('oai');
    }
}
