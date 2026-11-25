<?php

declare(strict_types=1);

namespace unit\library\Episciences\Review;

use Episciences_Repositories;
use Episciences_Review;
use PHPUnit\Framework\TestCase;

/**
 * Episciences_Review::getRepositories() and acceptsRepository() when the
 * repositories setting is stored, empty or missing.
 */
final class ReviewRepositoriesTest extends TestCase
{
    /**
     * @param array<int, array{SETTING: string, VALUE: string}> $rows
     */
    private function reviewWithStoredSettings(array $rows): Episciences_Review
    {
        $review = new Episciences_Review();
        $review->applySettingsFromRows($rows);

        return $review;
    }

    /**
     * @return array{SETTING: string, VALUE: string}
     */
    private function repositoriesRow(string $json): array
    {
        return ['SETTING' => Episciences_Review::SETTING_REPOSITORIES, 'VALUE' => $json];
    }

    public function testMissingSettingGivesNoRepository(): void
    {
        $review = $this->reviewWithStoredSettings([]);

        self::assertSame([], $review->getRepositories());
        self::assertFalse($review->acceptsRepository(Episciences_Repositories::ARXIV_REPO_ID));
    }

    public function testInvalidStoredValueGivesNoRepository(): void
    {
        $review = $this->reviewWithStoredSettings([$this->repositoriesRow('not json')]);

        self::assertSame([], $review->getRepositories());
        self::assertFalse($review->acceptsRepository(Episciences_Repositories::ARXIV_REPO_ID));
    }

    public function testStoredRepositoriesAreReturned(): void
    {
        $review = $this->reviewWithStoredSettings([$this->repositoriesRow('["1","2"]')]);

        self::assertSame(['1', '2'], $review->getRepositories());
    }

    public function testAcceptsRepositoryWhateverTheIdType(): void
    {
        $review = $this->reviewWithStoredSettings([$this->repositoriesRow('[1,"2"]')]);

        self::assertTrue($review->acceptsRepository(1));
        self::assertTrue($review->acceptsRepository('1'));
        self::assertTrue($review->acceptsRepository(2));
        self::assertTrue($review->acceptsRepository(Episciences_Repositories::ARXIV_REPO_ID));
        self::assertFalse($review->acceptsRepository(3));
    }
}
