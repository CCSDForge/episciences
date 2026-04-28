<?php

namespace unit\scripts;

use GetZbReviewsCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/GetZbReviewsCommand.php';

/**
 * Unit tests for GetZbReviewsCommand.
 *
 * Focuses on pure logic (no bootstrap, no DB) via reflection.
 */
class GetZbReviewsCommandTest extends TestCase
{
    private GetZbReviewsCommand $command;

    protected function setUp(): void
    {
        $this->command = new GetZbReviewsCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:zb-reviews', $this->command->getName());
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    // -------------------------------------------------------------------------
    // extractZbMathReviews() — tested via reflection
    // -------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function extractZbMathReviews(array $apiResponse): array
    {
        $method = new ReflectionMethod(GetZbReviewsCommand::class, 'extractZbMathReviews');
        $method->setAccessible(true);
        return $method->invoke($this->command, $apiResponse);
    }

    public function testExtractZbMathReviews_EmptyResult_ReturnsEmpty(): void
    {
        $this->assertSame([], $this->extractZbMathReviews(['result' => []]));
    }

    public function testExtractZbMathReviews_ResultKeyAbsent_ReturnsEmpty(): void
    {
        $this->assertSame([], $this->extractZbMathReviews([]));
    }

    public function testExtractZbMathReviews_NoEditorialContributions_ReturnsEmpty(): void
    {
        $response = ['result' => [['title' => 'Some paper']]];
        $this->assertSame([], $this->extractZbMathReviews($response));
    }

    public function testExtractZbMathReviews_NonReviewContribution_Skipped(): void
    {
        $response = ['result' => [[
            'id' => '7856.00001',
            'editorial_contributions' => [[
                'contribution_type' => 'abstract',
                'reviewer' => ['sign' => 'J. Doe', 'reviewer_id' => '123'],
                'language' => 'en',
            ]],
        ]]];
        $this->assertSame([], $this->extractZbMathReviews($response));
    }

    public function testExtractZbMathReviews_ReviewContribution_Extracted(): void
    {
        $response = ['result' => [[
            'id' => '7856.00001',
            'editorial_contributions' => [[
                'contribution_type' => 'review',
                'reviewer' => ['sign' => 'J. Doe', 'reviewer_id' => '123'],
                'language' => 'en',
            ]],
        ]]];
        $reviews = $this->extractZbMathReviews($response);

        $this->assertCount(1, $reviews);
        $this->assertSame('7856.00001', $reviews[0]['zbmathid']);
        $this->assertSame('en', $reviews[0]['language']);
        $this->assertSame(['sign' => 'J. Doe', 'reviewer_id' => '123'], $reviews[0]['reviewer']);
    }

    public function testExtractZbMathReviews_MultipleReviews_AllExtracted(): void
    {
        $response = ['result' => [[
            'id' => '7856.00001',
            'editorial_contributions' => [
                [
                    'contribution_type' => 'review',
                    'reviewer' => ['sign' => 'A. Smith', 'reviewer_id' => '1'],
                    'language' => 'en',
                ],
                [
                    'contribution_type' => 'review',
                    'reviewer' => ['sign' => 'B. Jones', 'reviewer_id' => '2'],
                    'language' => 'de',
                ],
            ],
        ]]];
        $this->assertCount(2, $this->extractZbMathReviews($response));
    }

    /**
     * HTML special characters in zbmathid and language must be escaped to prevent XSS.
     */
    public function testExtractZbMathReviews_XssInZbmathId_IsEscaped(): void
    {
        $response = ['result' => [[
            'id' => '<script>alert(1)</script>',
            'editorial_contributions' => [[
                'contribution_type' => 'review',
                'reviewer' => ['sign' => 'A', 'reviewer_id' => '1'],
                'language' => 'en',
            ]],
        ]]];
        $reviews = $this->extractZbMathReviews($response);
        $this->assertStringNotContainsString('<script>', $reviews[0]['zbmathid']);
    }

    public function testExtractZbMathReviews_XssInLanguage_IsEscaped(): void
    {
        $response = ['result' => [[
            'id' => '123',
            'editorial_contributions' => [[
                'contribution_type' => 'review',
                'reviewer' => ['sign' => 'A', 'reviewer_id' => '1'],
                'language' => '<img src=x onerror=alert(1)>',
            ]],
        ]]];
        $reviews = $this->extractZbMathReviews($response);
        $this->assertStringNotContainsString('<img', $reviews[0]['language']);
    }

    // -------------------------------------------------------------------------
    // buildLinkedReview() — tested via reflection
    // -------------------------------------------------------------------------

    /** @return array{0: string, 1: string, 2: string, 3: string, 4: \Episciences_Paper_DatasetMetadata} */
    private function buildLinkedReview(array $review): array
    {
        $method = new ReflectionMethod(GetZbReviewsCommand::class, 'buildLinkedReview');
        $method->setAccessible(true);
        return $method->invoke($this->command, $review);
    }

    public function testBuildLinkedReview_RelationshipIsHasReview(): void
    {
        [$relationship] = $this->buildLinkedReview($this->makeSampleReview());
        $this->assertSame('hasReview', $relationship);
    }

    public function testBuildLinkedReview_TypeLdIsZbmath(): void
    {
        [, $typeLd] = $this->buildLinkedReview($this->makeSampleReview());
        $this->assertSame('zbmath', $typeLd);
    }

    public function testBuildLinkedReview_ValueLdContainsZbmathId(): void
    {
        [,, $valueLd] = $this->buildLinkedReview($this->makeSampleReview('7856.00001'));
        $this->assertStringContainsString('7856.00001', $valueLd);
        $this->assertStringStartsWith('https://zbmath.org/', $valueLd);
    }

    public function testBuildLinkedReview_InputTypeLdIsPublication(): void
    {
        [,,, $inputTypeLd] = $this->buildLinkedReview($this->makeSampleReview());
        $this->assertSame('publication', $inputTypeLd);
    }

    public function testBuildLinkedReview_MetatextContainsReviewerName(): void
    {
        [,,,, $linkMetaText] = $this->buildLinkedReview($this->makeSampleReview());
        $metatextRaw = $linkMetaText->getMetatext();
        $decoded     = json_decode($metatextRaw, true);
        $this->assertArrayHasKey('citationFull', $decoded);
        $this->assertStringContainsString('J. Doe', $decoded['citationFull']);
    }

    public function testBuildLinkedReview_XssInReviewerSign_IsEscaped(): void
    {
        $review = $this->makeSampleReview();
        $review['reviewer']['sign'] = '<script>alert(1)</script>';
        [,,,, $linkMetaText] = $this->buildLinkedReview($review);
        $this->assertStringNotContainsString('<script>', $linkMetaText->getMetatext());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function makeSampleReview(string $zbmathid = '7856.00001'): array
    {
        return [
            'zbmathid'  => $zbmathid,
            'language'  => 'en',
            'reviewer'  => ['sign' => 'J. Doe', 'reviewer_id' => '42'],
        ];
    }
}
