<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlternativePipelineConfigurationTest extends TestCase
{
    /** @dataProvider configurations */
    public function testAvailabilityAndActivation(array $repositories, int $setting, bool $available, bool $enabled): void
    {
        $review = $this->getMockBuilder(Episciences_Review::class)->onlyMethods(['loadSettings'])->getMock();
        $review->setSetting(Episciences_Review::SETTING_REPOSITORIES, $repositories);
        $review->setSetting(Episciences_Review::SETTING_ALTERNATIVE_PIPELINE, $setting);
        self::assertSame($available, $review->isAlternativePipelineAvailable());
        self::assertSame($enabled, $review->isAlternativePipelineEnabled());
    }

    public static function configurations(): iterable
    {
        yield 'enabled' => [[Episciences_Repositories::ARXIV_REPO_ID], 1, true, true];
        yield 'disabled' => [[Episciences_Repositories::ARXIV_REPO_ID], 0, true, false];
        yield 'no repositories' => [[], 1, false, false];
        yield 'other repository' => [[999], 1, false, false];
        yield 'multiple repositories' => [[Episciences_Repositories::ARXIV_REPO_ID, 999], 1, false, false];
        yield 'duplicate ids' => [[Episciences_Repositories::ARXIV_REPO_ID, (int)Episciences_Repositories::ARXIV_REPO_ID], 1, true, true];
    }
}
