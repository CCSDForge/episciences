<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test suite for Episciences_Paper::isImported() method and its private helpers
 */
final class Episciences_Paper_IsImportedTest extends TestCase
{
    /**
     * Test isImported returns true when flag is explicitly 'imported'
     */
    public function testIsImportedWithExplicitFlag(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('imported');
        $paper->method('getPublication_date')->willReturn('2023-06-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-10 09:00:00');
        $paper->method('getWhen')->willReturn('2023-05-10 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_PUBLISHED);

        self::assertTrue($paper->isImported(), 'Paper with imported flag should be detected as imported');
    }

    /**
     * Test isImported returns true when publication date is before submission date
     */
    public function testIsImportedWithPublicationBeforeSubmission(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-05-01 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-06-15 09:00:00');
        $paper->method('getWhen')->willReturn('2023-05-10 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        self::assertTrue($paper->isImported(), 'Paper with publication before submission should be detected as imported');
    }

    /**
     * Test isImported returns true when publication date equals submission date
     */
    public function testIsImportedWithPublicationEqualsSubmission(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-05-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-15 10:00:00');
        $paper->method('getWhen')->willReturn('2023-05-10 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        self::assertTrue($paper->isImported(), 'Paper with publication equal to submission should be detected as imported');
    }

    /**
     * Test isImported returns true when submission year is before 2013
     */
    public function testIsImportedWithSubmissionBefore2013(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-06-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2012-12-31 09:00:00');
        $paper->method('getWhen')->willReturn('2012-12-31 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        self::assertTrue($paper->isImported(), 'Paper with submission date before 2013 should be detected as imported');
    }

    /**
     * Test isImported returns true when publication year is before 2013
     */
    public function testIsImportedWithPublicationBefore2013(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2012-05-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-10 09:00:00');
        $paper->method('getWhen')->willReturn('2012-05-10 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        self::assertTrue($paper->isImported(), 'Paper with publication date before 2013 should be detected as imported');
    }

    /**
     * Test isImported returns true when published paper has submission after WHEN
     */
    public function testIsImportedWithSubmissionAfterWhenForPublished(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-06-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-20 09:00:00');
        $paper->method('getWhen')->willReturn('2023-05-10 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_PUBLISHED);

        self::assertTrue($paper->isImported(), 'Published paper with submission after WHEN should be detected as imported');
    }

    /**
     * Test isImported returns true when published paper has publication before WHEN
     */
    public function testIsImportedWithPublicationBeforeWhenForPublished(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-05-01 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-05 09:00:00');
        $paper->method('getWhen')->willReturn('2023-05-10 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_PUBLISHED);

        self::assertTrue($paper->isImported(), 'Published paper with publication before WHEN should be detected as imported');
    }

    /**
     * Test isImported returns false with valid dates and no import criteria
     */
    public function testIsImportedWithValidDatesNotImported(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-06-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-10 09:00:00');
        $paper->method('getWhen')->willReturn('2023-05-10 08:00:00'); // WHEN same day as submission
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED); // Not published to avoid date inconsistency check

        self::assertFalse($paper->isImported(), 'Paper with valid consistent dates should not be detected as imported');
    }

    /**
     * Test isImported handles null publication date gracefully
     */
    public function testIsImportedWithNullPublicationDate(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn(null);
        $paper->method('getSubmission_date')->willReturn('2023-05-10 09:00:00');
        $paper->method('getWhen')->willReturn('2023-05-09 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        // Should not throw error and should return false (no criteria met)
        self::assertFalse($paper->isImported(), 'Paper with null publication date should not cause error');
    }

    /**
     * Test isImported handles null submission date gracefully
     */
    public function testIsImportedWithNullSubmissionDate(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-06-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn(null);
        $paper->method('getWhen')->willReturn('2023-05-09 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        // Should not throw error and should return false (no criteria met)
        self::assertFalse($paper->isImported(), 'Paper with null submission date should not cause error');
    }

    /**
     * Test isImported handles null WHEN date gracefully
     */
    public function testIsImportedWithNullWhenDate(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-06-15 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-10 09:00:00');
        $paper->method('getWhen')->willReturn(null);
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_PUBLISHED);

        // Should not throw error and should return false (no criteria met)
        self::assertFalse($paper->isImported(), 'Paper with null WHEN date should not cause error');
    }

    /**
     * Test isImported with all null dates
     */
    public function testIsImportedWithAllNullDates(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn(null);
        $paper->method('getSubmission_date')->willReturn(null);
        $paper->method('getWhen')->willReturn(null);
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        // Should not throw error and should return false (no criteria met)
        self::assertFalse($paper->isImported(), 'Paper with all null dates should not cause error');
    }

    /**
     * Test parseDateSafely with valid date
     */
    public function testParseDateSafelyWithValidDate(): void
    {
        $paper = new Episciences_Paper();
        $method = $this->getPrivateMethod('parseDateSafely');

        $result = $method->invoke($paper, '2023-05-15 10:30:45');

        self::assertInstanceOf(\DateTime::class, $result);
        self::assertEquals('2023-05-15 10:30:45', $result->format('Y-m-d H:i:s'));
    }

    /**
     * Test parseDateSafely with null value
     */
    public function testParseDateSafelyWithNull(): void
    {
        $paper = new Episciences_Paper();
        $method = $this->getPrivateMethod('parseDateSafely');

        $result = $method->invoke($paper, null);

        self::assertNull($result, 'parseDateSafely should return null for null input');
    }

    /**
     * Test parseDateSafely with empty string
     */
    public function testParseDateSafelyWithEmptyString(): void
    {
        $paper = new Episciences_Paper();
        $method = $this->getPrivateMethod('parseDateSafely');

        $result = $method->invoke($paper, '');

        self::assertNull($result, 'parseDateSafely should return null for empty string');
    }

    /**
     * Test parseDateSafely with invalid date format
     */
    public function testParseDateSafelyWithInvalidFormat(): void
    {
        $paper = new Episciences_Paper();
        $method = $this->getPrivateMethod('parseDateSafely');

        $result = $method->invoke($paper, 'not-a-date');

        self::assertNull($result, 'parseDateSafely should return null for invalid date format');
    }

    /**
     * Test isImported does not detect date inconsistencies for non-published papers
     */
    public function testIsImportedIgnoresDateInconsistenciesForNonPublished(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, [
            'getFlag',
            'getPublication_date',
            'getSubmission_date',
            'getWhen',
            'getStatus'
        ]);

        $paper->method('getFlag')->willReturn('submitted');
        $paper->method('getPublication_date')->willReturn('2023-05-01 10:00:00');
        $paper->method('getSubmission_date')->willReturn('2023-05-20 09:00:00');
        $paper->method('getWhen')->willReturn('2023-05-10 08:00:00');
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_SUBMITTED);

        // Date inconsistencies should only apply to published papers
        // In this case, publication is before submission which should trigger import
        self::assertTrue($paper->isImported(), 'Paper with publication before submission should be imported regardless of status');
    }

    /**
     * Helper method to access private methods for testing
     *
     * @param string $methodName
     * @return ReflectionMethod
     */
    private function getPrivateMethod(string $methodName): ReflectionMethod
    {
        $reflection = new \ReflectionClass(Episciences_Paper::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
