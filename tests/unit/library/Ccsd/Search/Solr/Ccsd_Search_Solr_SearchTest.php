<?php

namespace unit\library\Ccsd\Search\Solr;

use Ccsd_Search_Solr_Search;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Search_Solr_Search
 *
 * All methods tested here are pure (no Solr I/O). The constructor only
 * sets the core name constant; it does not open a connection.
 * Methods that call $this->getQuery() (queryAddSort, queryAddFacets,
 * queryAddFilters) are exercised via the non-query-dependent paths only.
 */
class Ccsd_Search_Solr_SearchTest extends TestCase
{
    private Ccsd_Search_Solr_Search $search;

    protected function setUp(): void
    {
        $this->search = new Ccsd_Search_Solr_Search();
    }

    // ------------------------------------------------------------------
    // Constants
    // ------------------------------------------------------------------

    public function testDefaultBooleanOperator(): void
    {
        $this->assertSame('AND', Ccsd_Search_Solr_Search::SOLR_DEFAULT_BOOLEAN_OPERATOR);
    }

    public function testDefaultSortType(): void
    {
        $this->assertSame('score desc', Ccsd_Search_Solr_Search::SOLR_DEFAULT_SORT_TYPE);
    }

    // ------------------------------------------------------------------
    // getDefaultFiltersAsURL (static, pure)
    // ------------------------------------------------------------------

    public function testGetDefaultFiltersAsUrlNull(): void
    {
        $this->assertNull(Ccsd_Search_Solr_Search::getDefaultFiltersAsURL());
    }

    public function testGetDefaultFiltersAsUrlEmptyArray(): void
    {
        // Empty array: no filters — returns empty string
        $this->assertSame('', Ccsd_Search_Solr_Search::getDefaultFiltersAsURL([]));
    }

    public function testGetDefaultFiltersAsUrlSingleFilter(): void
    {
        $result = Ccsd_Search_Solr_Search::getDefaultFiltersAsURL(['status_i:1']);
        $this->assertSame('&fq=' . urlencode('status_i:1'), $result);
    }

    public function testGetDefaultFiltersAsUrlMultipleFilters(): void
    {
        $result = Ccsd_Search_Solr_Search::getDefaultFiltersAsURL(['status_i:1', 'lang_s:fr']);
        $this->assertStringContainsString('&fq=' . urlencode('status_i:1'), $result);
        $this->assertStringContainsString('&fq=' . urlencode('lang_s:fr'), $result);
    }

    // ------------------------------------------------------------------
    // parseSolrError (static, pure)
    // ------------------------------------------------------------------

    public function testParseSolrErrorCode0(): void
    {
        $e = new \Exception('Connection refused', 0);
        $msg = Ccsd_Search_Solr_Search::parseSolrError($e);
        $this->assertStringContainsString('indisponible', $msg);
    }

    public function testParseSolrErrorCode400SortParam(): void
    {
        $e = new \Exception("sort param field can't be found", 400);
        $msg = Ccsd_Search_Solr_Search::parseSolrError($e);
        $this->assertStringContainsString('tri', $msg);
    }

    public function testParseSolrErrorCode400UndefinedField(): void
    {
        $e = new \Exception('undefined field myField', 400);
        $msg = Ccsd_Search_Solr_Search::parseSolrError($e);
        $this->assertStringContainsString("n'existe pas", $msg);
    }

    public function testParseSolrErrorCode400Generic(): void
    {
        $e = new \Exception('some other 400 error', 400);
        $msg = Ccsd_Search_Solr_Search::parseSolrError($e);
        $this->assertStringContainsString('indisponible', $msg);
    }

    public function testParseSolrErrorUnknownCode(): void
    {
        $e = new \Exception('unknown', 999);
        $msg = Ccsd_Search_Solr_Search::parseSolrError($e);
        $this->assertIsString($msg);
        $this->assertNotEmpty($msg);
    }

    // ------------------------------------------------------------------
    // RawSearchParams
    // ------------------------------------------------------------------

    public function testGetRawSearchParamsDefaultEmpty(): void
    {
        $this->assertSame([], $this->search->getRawSearchParams());
    }

    public function testSetAndGetRawSearchParams(): void
    {
        $params = ['q' => 'physics', 'rows' => '10'];
        $this->search->setRawSearchParams($params);
        $this->assertSame($params, $this->search->getRawSearchParams());
    }

    public function testSetRawSearchParamsReturnsSelf(): void
    {
        $result = $this->search->setRawSearchParams([]);
        $this->assertSame($this->search, $result);
    }

    public function testGetRawSearchParamsByKeyFound(): void
    {
        $this->search->setRawSearchParams(['q' => 'quantum', 'rows' => '20']);
        $this->assertSame('quantum', $this->search->getRawSearchParamsbyKey('q'));
        $this->assertSame('20', $this->search->getRawSearchParamsbyKey('rows'));
    }

    public function testGetRawSearchParamsByKeyNotFound(): void
    {
        $this->search->setRawSearchParams(['q' => 'test']);
        $this->assertNull($this->search->getRawSearchParamsbyKey('missing'));
    }

    public function testGetRawSearchParamsByKeyNullKey(): void
    {
        $this->assertNull($this->search->getRawSearchParamsbyKey(null));
    }

    public function testSetRawSearchParamsByKey(): void
    {
        $this->search->setRawSearchParamsbyKey('q', 'biology');
        $this->assertSame('biology', $this->search->getRawSearchParamsbyKey('q'));
    }

    public function testSetRawSearchParamsByKeyNullKeyIgnored(): void
    {
        $this->search->setRawSearchParamsbyKey(null, 'value');
        $this->assertSame([], $this->search->getRawSearchParams());
    }

    public function testSetRawSearchParamsByKeyNullValueIgnored(): void
    {
        $this->search->setRawSearchParamsbyKey('q', null);
        $this->assertSame([], $this->search->getRawSearchParams());
    }

    // ------------------------------------------------------------------
    // ParsedSearchParams
    // ------------------------------------------------------------------

    public function testGetParsedSearchParamsDefaultEmpty(): void
    {
        $this->assertSame([], $this->search->getParsedSearchParams());
    }

    public function testSetAndGetParsedSearchParams(): void
    {
        $params = ['q' => 'test', 'rows' => '10'];
        $this->search->setParsedSearchParams($params);
        $this->assertSame($params, $this->search->getParsedSearchParams());
    }

    public function testSetParsedSearchParamsReturnsSelf(): void
    {
        $result = $this->search->setParsedSearchParams([]);
        $this->assertSame($this->search, $result);
    }

    public function testSetParsedSearchParamsByKeyAdd(): void
    {
        $this->search->setParsedSearchParamsbyKey('rows', '50');
        $this->assertSame('50', $this->search->getParsedSearchParamsbyKey('rows'));
    }

    public function testSetParsedSearchParamsByKeyNullRemoves(): void
    {
        $this->search->setParsedSearchParams(['rows' => '50', 'q' => 'test']);
        $this->search->setParsedSearchParamsbyKey('rows', null);
        $this->assertNull($this->search->getParsedSearchParamsbyKey('rows'));
        $this->assertSame('test', $this->search->getParsedSearchParamsbyKey('q'));
    }

    public function testSetParsedSearchParamsByKeyNullKeyIgnored(): void
    {
        $this->search->setParsedSearchParamsbyKey(null, 'value');
        $this->assertSame([], $this->search->getParsedSearchParams());
    }

    public function testGetParsedSearchParamsByKeyNullKey(): void
    {
        $this->assertNull($this->search->getParsedSearchParamsbyKey(null));
    }

    public function testGetParsedSearchParamsByKeyMissing(): void
    {
        $this->assertNull($this->search->getParsedSearchParamsbyKey('missing'));
    }

    // ------------------------------------------------------------------
    // ParsedFilterParams
    // ------------------------------------------------------------------

    public function testGetParsedFilterParamsDefaultNull(): void
    {
        $this->assertNull($this->search->getParsedFilterParams());
    }

    public function testSetAndGetParsedFilterParams(): void
    {
        $filters = ['authFullName_s' => ['Alice', 'Bob']];
        $this->search->setParsedFilterParams($filters);
        $this->assertSame($filters, $this->search->getParsedFilterParams());
    }

    // ------------------------------------------------------------------
    // FilterExcludeTags
    // ------------------------------------------------------------------

    public function testGetFilterExcludeTagsDefaultNull(): void
    {
        $this->assertNull($this->search->getFilterExcludeTags());
    }

    public function testSetAndGetFilterExcludeTags(): void
    {
        $tags = ['tag0__authFullName_s'];
        $this->search->setFilterExcludeTags($tags);
        $this->assertSame($tags, $this->search->getFilterExcludeTags());
    }

    // ------------------------------------------------------------------
    // BooleanOperator
    // ------------------------------------------------------------------

    public function testGetBooleanOperatorDefaultNull(): void
    {
        $this->assertNull($this->search->getBooleanOperator());
    }

    public function testSetBooleanOperatorAnd(): void
    {
        $this->search->setBooleanOperator('AND');
        $this->assertSame('AND', $this->search->getBooleanOperator());
    }

    public function testSetBooleanOperatorOr(): void
    {
        $this->search->setBooleanOperator('OR');
        $this->assertSame('OR', $this->search->getBooleanOperator());
    }

    public function testSetBooleanOperatorInvalidDefaultsToAnd(): void
    {
        $this->search->setBooleanOperator('XOR');
        $this->assertSame(Ccsd_Search_Solr_Search::SOLR_DEFAULT_BOOLEAN_OPERATOR, $this->search->getBooleanOperator());
    }

    public function testSetBooleanOperatorNullDefaultsToAnd(): void
    {
        $this->search->setBooleanOperator(null);
        $this->assertSame(Ccsd_Search_Solr_Search::SOLR_DEFAULT_BOOLEAN_OPERATOR, $this->search->getBooleanOperator());
    }

    public function testSetBooleanOperatorReturnsSelf(): void
    {
        $result = $this->search->setBooleanOperator('AND');
        $this->assertSame($this->search, $result);
    }

    public function testSetBooleanOperatorStoredInParsedParams(): void
    {
        $this->search->setBooleanOperator('OR');
        $this->assertSame('OR', $this->search->getParsedSearchParamsbyKey('op'));
    }

    // ------------------------------------------------------------------
    // queryAddResultPerPage
    // ------------------------------------------------------------------

    public function testQueryAddResultPerPageNullPaginatorArrayReturnsEarly(): void
    {
        $result = $this->search->queryAddResultPerPage(null, 10);
        $this->assertSame($this->search, $result);
    }

    public function testQueryAddResultPerPageNullDefaultReturnsEarly(): void
    {
        $result = $this->search->queryAddResultPerPage([10, 20, 50], null);
        $this->assertSame($this->search, $result);
    }

    public function testQueryAddResultPerPageNoRowsParamUsesDefault(): void
    {
        // No 'rows' in rawSearchParams → use paginatordefaultNumberOfResults
        $this->search->setRawSearchParams([]);
        $this->search->queryAddResultPerPage([10, 20, 50], 20);
        $this->assertSame(20, $this->search->getParsedSearchParamsbyKey('rows'));
    }

    public function testQueryAddResultPerPageRowsInAllowedList(): void
    {
        // in_array() uses strict=true: raw param '50' (string) !== 50 (int) in array [10,20,50].
        // So it falls back to default. To match, the allowed list must contain the string value.
        $this->search->setRawSearchParams(['rows' => '50']);
        $this->search->queryAddResultPerPage(['10', '20', '50'], 20);
        $this->assertSame('50', $this->search->getParsedSearchParamsbyKey('rows'));
    }

    public function testQueryAddResultPerPageRowsNotInListFallsBack(): void
    {
        $this->search->setRawSearchParams(['rows' => '999']);
        $this->search->queryAddResultPerPage([10, 20, 50], 20);
        $this->assertSame(20, $this->search->getParsedSearchParamsbyKey('rows'));
    }
}
