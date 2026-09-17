<?php

namespace unit\library\Ccsd\User;

use Ccsd_User_Models_User;
use Ccsd_User_Models_UserMapper;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit tests for Ccsd_User_Models_UserMapper
 *
 * Focuses on the static find() cache path (no DB required).
 *
 * @covers Ccsd_User_Models_UserMapper
 */
class Ccsd_User_Models_UserMapperTest extends TestCase
{
    private static array $sampleRow = [
        'UID'              => 123,
        'USERNAME'         => 'jdoe',
        'EMAIL'            => 'jdoe@example.com',
        'CIV'              => 'M.',
        'FIRSTNAME'        => 'John',
        'MIDDLENAME'       => 'A.',
        'LASTNAME'         => 'Doe',
        'TIME_REGISTERED'  => '2020-01-01 00:00:00',
        'TIME_MODIFIED'    => '2024-06-01 00:00:00',
        'VALID'            => 1,
    ];

    private function setCacheEntry(int $uid, array $row): void
    {
        $ref = new ReflectionProperty(Ccsd_User_Models_UserMapper::class, '_cache');
        $ref->setAccessible(true);
        $cache = $ref->getValue(null);
        $cache[$uid] = $row;
        $ref->setValue(null, $cache);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Ccsd_User_Models_UserMapper::class, '_cache');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    // -------------------------------------------------------------------------
    // find() served from cache — no DB connection required
    // -------------------------------------------------------------------------

    public function testFindFromCachePopulatesUserObject(): void
    {
        $this->setCacheEntry(123, self::$sampleRow);

        $mapper = new Ccsd_User_Models_UserMapper();
        $user   = new Ccsd_User_Models_User();
        $result = $mapper->find(123, $user);

        $this->assertNotNull($result);
        $this->assertSame('jdoe', $user->getUsername());
        $this->assertSame('jdoe@example.com', $user->getEmail());
        $this->assertSame('John', $user->getFirstname());
        $this->assertSame('Doe', $user->getLastname());
    }

    public function testFindFromCacheReturnsTruthyValue(): void
    {
        $this->setCacheEntry(123, self::$sampleRow);

        $mapper = new Ccsd_User_Models_UserMapper();
        $result = $mapper->find(123);

        $this->assertNotNull($result);
        $this->assertTrue((bool) $result);
    }

    public function testFindFromCacheReturnedObjectHasUsernameProperty(): void
    {
        $this->setCacheEntry(123, self::$sampleRow);

        $mapper = new Ccsd_User_Models_UserMapper();
        $result = $mapper->find(123);

        $this->assertSame('jdoe', $result->USERNAME);
    }

    public function testFindFromCacheWorksWithoutUserParam(): void
    {
        $this->setCacheEntry(123, self::$sampleRow);

        $mapper = new Ccsd_User_Models_UserMapper();
        $result = $mapper->find(123);

        $this->assertNotNull($result);
    }

    public function testFindFromCacheHandlesStringUid(): void
    {
        $row = array_merge(self::$sampleRow, ['UID' => 456, 'USERNAME' => 'asmith']);
        $this->setCacheEntry(456, $row);

        $mapper = new Ccsd_User_Models_UserMapper();
        $result = $mapper->find('456');

        $this->assertNotNull($result);
        $this->assertSame('asmith', $result->USERNAME);
    }

    // -------------------------------------------------------------------------
    // emailIsUsedByAnotherAccount() — UID exclusion logic, no DB adapter required
    // -------------------------------------------------------------------------

    /**
     * @param array<string, int[]> $uidsByEmail
     */
    private function makeFakeTable(array $uidsByEmail): object
    {
        return new class($uidsByEmail) {
            private ?string $email = null;
            private ?int $excludeUid = null;

            public function __construct(private readonly array $uidsByEmail)
            {
            }

            public function select(): self
            {
                return $this;
            }

            public function from(self $table, $cols = '*'): self
            {
                return $this;
            }

            public function where(string $cond, $value = null): self
            {
                if ($cond === 'EMAIL = ?') {
                    $this->email = (string)$value;
                }
                if ($cond === 'UID != ?') {
                    $this->excludeUid = (int)$value;
                }

                return $this;
            }

            public function limit($count, $offset = null): self
            {
                return $this;
            }

            public function fetchRow($select): ?array
            {
                foreach ($this->uidsByEmail[$this->email] ?? [] as $uid) {
                    if ($uid !== $this->excludeUid) {
                        return ['UID' => $uid];
                    }
                }

                return null;
            }
        };
    }

    public function testEmailIsUsedByAnotherAccountReturnsFalseWhenOnlyExcludedUidMatches(): void
    {
        $mapper = $this->createPartialMock(Ccsd_User_Models_UserMapper::class, ['getDbTable']);
        $mapper->method('getDbTable')->willReturn($this->makeFakeTable(['a@b.org' => [42]]));

        $this->assertFalse($mapper->emailIsUsedByAnotherAccount('a@b.org', 42));
    }

    public function testEmailIsUsedByAnotherAccountReturnsTrueWhenAnotherUidMatches(): void
    {
        $mapper = $this->createPartialMock(Ccsd_User_Models_UserMapper::class, ['getDbTable']);
        $mapper->method('getDbTable')->willReturn($this->makeFakeTable(['a@b.org' => [42, 77]]));

        $this->assertTrue($mapper->emailIsUsedByAnotherAccount('a@b.org', 42));
    }

    public function testEmailIsUsedByAnotherAccountReturnsFalseWhenNoRowMatches(): void
    {
        $mapper = $this->createPartialMock(Ccsd_User_Models_UserMapper::class, ['getDbTable']);
        $mapper->method('getDbTable')->willReturn($this->makeFakeTable([]));

        $this->assertFalse($mapper->emailIsUsedByAnotherAccount('nobody@b.org', 0));
    }

}