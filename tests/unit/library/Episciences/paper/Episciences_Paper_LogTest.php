<?php

namespace unit\library\Episciences;

use Episciences_Paper_Log;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class Episciences_Paper_LogTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructWithNoArgumentsLeavesAllFieldsNull(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertNull($log->getLogid());
        self::assertNull($log->getPaperid());
        self::assertNull($log->getDocid());
        self::assertNull($log->getUid());
        self::assertNull($log->getRvid());
        self::assertNull($log->getAction());
        self::assertNull($log->getDetail());
        self::assertNull($log->getDate());
    }

    public function testConstructWithNullOptionDoesNotCallSetOptions(): void
    {
        $log = new Episciences_Paper_Log(null);

        self::assertNull($log->getLogid());
    }

    public function testConstructWithOptionsArraySetsAllFields(): void
    {
        $log = new Episciences_Paper_Log([
            'logid'   => 1,
            'paperid' => 2,
            'docid'   => 3,
            'uid'     => 4,
            'rvid'    => 5,
            'action'  => 'submit',
            'detail'  => 'some detail',
            'date'    => '2026-01-15 10:00:00',
        ]);

        self::assertSame(1, $log->getLogid());
        self::assertSame(2, $log->getPaperid());
        self::assertSame(3, $log->getDocid());
        self::assertSame(4, $log->getUid());
        self::assertSame(5, $log->getRvid());
        self::assertSame('submit', $log->getAction());
        self::assertSame('some detail', $log->getDetail());
        self::assertSame('2026-01-15 10:00:00', $log->getDate());
    }

    // -------------------------------------------------------------------------
    // setOptions
    // -------------------------------------------------------------------------

    public function testSetOptionsReturnsSelf(): void
    {
        $log = new Episciences_Paper_Log();
        $result = $log->setOptions(['logid' => 42]);

        self::assertSame($log, $result);
    }

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        $log = new Episciences_Paper_Log();
        // Should not throw, unknown key is silently ignored
        $log->setOptions(['unknown_field' => 'value', 'logid' => 99]);

        self::assertSame(99, $log->getLogid());
    }

    public function testSetOptionsIsCaseInsensitiveOnKeys(): void
    {
        $log = new Episciences_Paper_Log();
        // Keys are lowercased before building the method name
        $log->setOptions(['LOGID' => 7, 'PaperId' => 8]);

        self::assertSame(7, $log->getLogid());
        self::assertSame(8, $log->getPaperid());
    }

    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    public function testToArrayContainsAllExpectedKeys(): void
    {
        $log = new Episciences_Paper_Log();
        $result = $log->toArray();

        self::assertArrayHasKey('logid', $result);
        self::assertArrayHasKey('paperid', $result);
        self::assertArrayHasKey('docid', $result);
        self::assertArrayHasKey('uid', $result);
        self::assertArrayHasKey('rvid', $result);
        self::assertArrayHasKey('action', $result);
        self::assertArrayHasKey('detail', $result);
        self::assertArrayHasKey('date', $result);
        self::assertCount(8, $result);
    }

    public function testToArrayReturnsCorrectValues(): void
    {
        $log = new Episciences_Paper_Log([
            'logid'   => 10,
            'paperid' => 20,
            'docid'   => 30,
            'uid'     => 40,
            'rvid'    => 50,
            'action'  => 'review',
            'detail'  => 'comment',
            'date'    => '2026-02-01 00:00:00',
        ]);

        $result = $log->toArray();

        self::assertSame(10, $result['logid']);
        self::assertSame(20, $result['paperid']);
        self::assertSame(30, $result['docid']);
        self::assertSame(40, $result['uid']);
        self::assertSame(50, $result['rvid']);
        self::assertSame('review', $result['action']);
        self::assertSame('comment', $result['detail']);
        self::assertSame('2026-02-01 00:00:00', $result['date']);
    }

    public function testToArrayWithNullsWhenNotSet(): void
    {
        $log = new Episciences_Paper_Log();
        $result = $log->toArray();

        foreach ($result as $value) {
            self::assertNull($value);
        }
    }

    // -------------------------------------------------------------------------
    // Individual getters / setters
    // -------------------------------------------------------------------------

    public function testSetAndGetLogid(): void
    {
        $log = new Episciences_Paper_Log();
        $return = $log->setLogid(123);

        self::assertSame($log, $return);
        self::assertSame(123, $log->getLogid());
    }

    public function testSetAndGetPaperid(): void
    {
        $log = new Episciences_Paper_Log();
        $return = $log->setPaperid(456);

        self::assertSame($log, $return);
        self::assertSame(456, $log->getPaperid());
    }

    public function testSetAndGetDocid(): void
    {
        $log = new Episciences_Paper_Log();
        $return = $log->setDocid(789);

        self::assertSame($log, $return);
        self::assertSame(789, $log->getDocid());
    }

    public function testSetAndGetUid(): void
    {
        $log = new Episciences_Paper_Log();
        $return = $log->setUid(1001);

        self::assertSame($log, $return);
        self::assertSame(1001, $log->getUid());
    }

    public function testSetAndGetRvid(): void
    {
        $log = new Episciences_Paper_Log();
        $return = $log->setRvid(5);

        self::assertSame($log, $return);
        self::assertSame(5, $log->getRvid());
    }

    public function testSetAndGetAction(): void
    {
        $log = new Episciences_Paper_Log();
        $return = $log->setAction('accept');

        self::assertSame($log, $return);
        self::assertSame('accept', $log->getAction());
    }

    public function testSetAndGetDate(): void
    {
        $log = new Episciences_Paper_Log();
        $return = $log->setDate('2026-03-01 12:00:00');

        self::assertSame($log, $return);
        self::assertSame('2026-03-01 12:00:00', $log->getDate());
    }

    // -------------------------------------------------------------------------
    // setDetail / getDetail — JSON handling
    // -------------------------------------------------------------------------

    public function testSetDetailReturnsSelf(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertSame($log, $log->setDetail('text'));
    }

    public function testSetDetailWithPlainStringStoresItAsIs(): void
    {
        $log = new Episciences_Paper_Log();
        $log->setDetail('plain text');

        self::assertSame('plain text', $log->getDetail());
    }

    public function testSetDetailWithJsonStringDecodesItToArray(): void
    {
        $log = new Episciences_Paper_Log();
        $log->setDetail('{"key":"value","count":3}');

        $result = $log->getDetail();

        self::assertIsArray($result);
        self::assertSame('value', $result['key']);
        self::assertSame(3, $result['count']);
    }

    public function testSetDetailWithJsonArrayDecodes(): void
    {
        $log = new Episciences_Paper_Log();
        $log->setDetail('["a","b","c"]');

        $result = $log->getDetail();

        self::assertIsArray($result);
        self::assertSame(['a', 'b', 'c'], $result);
    }

    public function testSetDetailWithNonStringStoresAsIs(): void
    {
        $log = new Episciences_Paper_Log();
        $payload = ['already' => 'decoded'];
        $log->setDetail($payload);

        self::assertSame($payload, $log->getDetail());
    }

    public function testSetDetailWithIntegerStoresAsIs(): void
    {
        $log = new Episciences_Paper_Log();
        $log->setDetail(42);

        self::assertSame(42, $log->getDetail());
    }

    public function testSetDetailWithNullStoresNull(): void
    {
        $log = new Episciences_Paper_Log();
        $log->setDetail(null);

        self::assertNull($log->getDetail());
    }

    public function testGetDetailOnJsonStringStoredInternallyDecodesIt(): void
    {
        // If $this->_detail contains a raw JSON string (e.g. loaded from DB via setOptions
        // without going through setDetail), getDetail() should decode it.
        $log = new Episciences_Paper_Log();

        $prop = new ReflectionProperty(Episciences_Paper_Log::class, '_detail');
        $prop->setAccessible(true);
        $prop->setValue($log, '{"raw":"json"}');

        $result = $log->getDetail();

        self::assertIsArray($result);
        self::assertSame('json', $result['raw']);
    }

    public function testGetDetailOnPlainStringStoredInternallyReturnsItAsIs(): void
    {
        $log = new Episciences_Paper_Log();

        $prop = new ReflectionProperty(Episciences_Paper_Log::class, '_detail');
        $prop->setAccessible(true);
        $prop->setValue($log, 'plain internal');

        self::assertSame('plain internal', $log->getDetail());
    }

    // -------------------------------------------------------------------------
    // Fluent interface — chaining
    // -------------------------------------------------------------------------

    public function testFluentInterfaceChaining(): void
    {
        $log = (new Episciences_Paper_Log())
            ->setLogid(1)
            ->setPaperid(2)
            ->setDocid(3)
            ->setUid(4)
            ->setRvid(5)
            ->setAction('submit')
            ->setDetail('note')
            ->setDate('2026-01-01 00:00:00');

        self::assertSame(1, $log->getLogid());
        self::assertSame(2, $log->getPaperid());
        self::assertSame(3, $log->getDocid());
        self::assertSame(4, $log->getUid());
        self::assertSame(5, $log->getRvid());
        self::assertSame('submit', $log->getAction());
        self::assertSame('note', $log->getDetail());
        self::assertSame('2026-01-01 00:00:00', $log->getDate());
    }

    // -------------------------------------------------------------------------
    // setOptions — cache performance (behaviour must be identical to uncached)
    // -------------------------------------------------------------------------

    public function testSetOptionsIsIdempotentAcrossMultipleCalls(): void
    {
        $log = new Episciences_Paper_Log();
        $log->setOptions(['logid' => 1, 'paperid' => 2]);
        // Second call must not be affected by the static cache from the first
        $log->setOptions(['logid' => 99, 'paperid' => 100]);

        self::assertSame(99, $log->getLogid());
        self::assertSame(100, $log->getPaperid());
    }

    // -------------------------------------------------------------------------
    // save — detail encoding (no DB path, logic only via getDetail)
    // -------------------------------------------------------------------------

    public function testSaveDetailEncodesArrayButNotScalar(): void
    {
        // After setDetail(['a' => 1]), getDetail() returns an array → save() will encode it.
        // After setDetail('plain'), getDetail() returns 'plain' → save() stores it as-is.
        // We can only verify the path via getDetail() since save() requires DB; covered here
        // for documentation and to protect the logic against regression.
        $log = new Episciences_Paper_Log();

        $log->setDetail(['key' => 'value']);
        self::assertIsArray($log->getDetail()); // save() would call Zend_Json::encode()

        $log->setDetail('plain scalar');
        self::assertIsString($log->getDetail()); // save() would store as-is
    }

    // -------------------------------------------------------------------------
    // load — non-DB path only
    // -------------------------------------------------------------------------

    public function testLoadReturnsFalseForNonNumericId(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertFalse($log->load('not-a-number'));
    }

    public function testLoadReturnsFalseForEmptyString(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertFalse($log->load(''));
    }

    public function testLoadReturnsFalseForNullId(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertFalse($log->load(null));
    }

    /**
     * filter_var(FILTER_VALIDATE_INT) rejects floats and scientific notation,
     * unlike the previous is_numeric() which accepted them.
     */
    public function testLoadReturnsFalseForFloatString(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertFalse($log->load('1.5'));
    }

    public function testLoadReturnsFalseForScientificNotation(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertFalse($log->load('1e5'));
    }

    public function testLoadReturnsFalseForZero(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertFalse($log->load(0));
    }

    public function testLoadReturnsFalseForNegativeId(): void
    {
        $log = new Episciences_Paper_Log();

        self::assertFalse($log->load(-1));
    }
}