<?php

/**
 * Unit tests for Episciences_Tools_ArrayHelper
 *
 * Tests the array utility methods without requiring database access.
 * Focuses on edge cases, validation, and correct chunking behavior.
 */
class Episciences_Tools_ArrayHelperTest extends PHPUnit\Framework\TestCase
{
    /**
     * Test basic chunking with default size
     */
    public function testChunkForSqlWithDefaultSize()
    {
        // Create array of 1500 items
        $items = range(1, 1500);

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items);

        // Should create 3 chunks: 500 + 500 + 500
        $this->assertCount(3, $chunks);
        $this->assertCount(500, $chunks[0]);
        $this->assertCount(500, $chunks[1]);
        $this->assertCount(500, $chunks[2]);
    }

    /**
     * Test chunking with custom size
     */
    public function testChunkForSqlWithCustomSize()
    {
        $items = range(1, 1000);

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items, 250);

        // Should create 4 chunks of 250 each
        $this->assertCount(4, $chunks);
        foreach ($chunks as $chunk) {
            $this->assertCount(250, $chunk);
        }
    }

    /**
     * Test chunking with size larger than array
     */
    public function testChunkForSqlWithLargeSize()
    {
        $items = range(1, 100);

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items, 500);

        // Should create 1 chunk with all 100 items
        $this->assertCount(1, $chunks);
        $this->assertCount(100, $chunks[0]);
    }

    /**
     * Test chunking with uneven division
     */
    public function testChunkForSqlWithUnevenDivision()
    {
        $items = range(1, 1234);

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items, 500);

        // Should create 3 chunks: 500 + 500 + 234
        $this->assertCount(3, $chunks);
        $this->assertCount(500, $chunks[0]);
        $this->assertCount(500, $chunks[1]);
        $this->assertCount(234, $chunks[2]);
    }

    /**
     * Test chunking with empty array
     */
    public function testChunkForSqlWithEmptyArray()
    {
        $items = [];

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items);

        // Should return empty array
        $this->assertIsArray($chunks);
        $this->assertCount(0, $chunks);
    }

    /**
     * Test chunking with single item
     */
    public function testChunkForSqlWithSingleItem()
    {
        $items = [42];

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items);

        // Should create 1 chunk with 1 item
        $this->assertCount(1, $chunks);
        $this->assertCount(1, $chunks[0]);
        $this->assertEquals(42, $chunks[0][0]);
    }

    /**
     * Test that chunk size validation rejects size < 1
     */
    public function testChunkForSqlRejectsZeroSize()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Chunk size must be between/');

        $items = range(1, 100);
        Episciences_Tools_ArrayHelper::chunkForSql($items, 0);
    }

    /**
     * Test that chunk size validation rejects negative size
     */
    public function testChunkForSqlRejectsNegativeSize()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Chunk size must be between/');

        $items = range(1, 100);
        Episciences_Tools_ArrayHelper::chunkForSql($items, -10);
    }

    /**
     * Test that chunk size validation rejects size > 1000
     */
    public function testChunkForSqlRejectsTooLargeSize()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Chunk size must be between/');

        $items = range(1, 100);
        Episciences_Tools_ArrayHelper::chunkForSql($items, 1001);
    }

    /**
     * Test that chunk size validation accepts maximum size
     */
    public function testChunkForSqlAcceptsMaximumSize()
    {
        $items = range(1, 2000);

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items, 1000);

        // Should create 2 chunks of 1000 each
        $this->assertCount(2, $chunks);
        $this->assertCount(1000, $chunks[0]);
        $this->assertCount(1000, $chunks[1]);
    }

    /**
     * Test that chunking preserves values correctly
     */
    public function testChunkForSqlPreservesValues()
    {
        $items = [10, 20, 30, 40, 50];

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items, 2);

        // Should create 3 chunks: [10, 20], [30, 40], [50]
        $this->assertCount(3, $chunks);
        $this->assertEquals([10, 20], $chunks[0]);
        $this->assertEquals([30, 40], $chunks[1]);
        $this->assertEquals([50], $chunks[2]);
    }

    /**
     * Test chunking with associative array (keys are not preserved by default)
     */
    public function testChunkForSqlWithAssociativeArray()
    {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($items, 2);

        // Should create 2 chunks with numeric keys
        $this->assertCount(2, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertCount(1, $chunks[1]);

        // Keys are reset to 0, 1, 2...
        $this->assertEquals([1, 2], array_values($chunks[0]));
        $this->assertEquals([3], array_values($chunks[1]));
    }

    /**
     * Test chunkForSqlPreserveKeys preserves array keys
     */
    public function testChunkForSqlPreserveKeysPreservesKeys()
    {
        $items = ['uid_10' => 10, 'uid_20' => 20, 'uid_30' => 30];

        $chunks = Episciences_Tools_ArrayHelper::chunkForSqlPreserveKeys($items, 2);

        // Should create 2 chunks with keys preserved
        $this->assertCount(2, $chunks);
        $this->assertArrayHasKey('uid_10', $chunks[0]);
        $this->assertArrayHasKey('uid_20', $chunks[0]);
        $this->assertArrayHasKey('uid_30', $chunks[1]);
    }

    /**
     * Test chunkForSqlPreserveKeys with empty array
     */
    public function testChunkForSqlPreserveKeysWithEmptyArray()
    {
        $items = [];

        $chunks = Episciences_Tools_ArrayHelper::chunkForSqlPreserveKeys($items);

        $this->assertIsArray($chunks);
        $this->assertCount(0, $chunks);
    }

    /**
     * Test chunkForSqlPreserveKeys validation
     */
    public function testChunkForSqlPreserveKeysRejectsInvalidSize()
    {
        $this->expectException(InvalidArgumentException::class);

        $items = range(1, 100);
        Episciences_Tools_ArrayHelper::chunkForSqlPreserveKeys($items, 0);
    }

    /**
     * Test that chunking works with actual UIDs array (realistic scenario)
     */
    public function testChunkForSqlWithRealisticUidsScenario()
    {
        // Simulate 2500 user IDs
        $uids = range(1000, 3499);

        $chunks = Episciences_Tools_ArrayHelper::chunkForSql($uids);

        // Should create 5 chunks: 500 + 500 + 500 + 500 + 500
        $this->assertCount(5, $chunks);

        // Verify first chunk starts with 1000
        $this->assertEquals(1000, $chunks[0][0]);

        // Verify last chunk ends with 3499
        $lastChunk = end($chunks);
        $this->assertEquals(3499, end($lastChunk));

        // Verify total count is preserved
        $totalItems = array_reduce($chunks, function($carry, $chunk) {
            return $carry + count($chunk);
        }, 0);
        $this->assertEquals(2500, $totalItems);
    }
}
