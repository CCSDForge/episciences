<?php

namespace unit\library\Episciences\user;

use Episciences_User;
use Episciences_UsersManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_UsersManager
 *
 * Covers pure logic methods that do not require a database connection.
 *
 * @covers Episciences_UsersManager
 */
class Episciences_UsersManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // sortByName
    // -------------------------------------------------------------------------

    public function testSortByNameSortsAlphabeticallyByLastname(): void
    {
        $userZ = $this->makeUser('Zola', 'Emile');
        $userA = $this->makeUser('Balzac', 'Honoré');
        $userM = $this->makeUser('Maupassant', 'Guy');

        $sorted = Episciences_UsersManager::sortByName([$userZ, $userM, $userA]);

        $this->assertSame('Balzac', $sorted[0]->getLastname());
        $this->assertSame('Maupassant', $sorted[1]->getLastname());
        $this->assertSame('Zola', $sorted[2]->getLastname());
    }

    public function testSortByNameWithSameLastnameSortsByFirstname(): void
    {
        $userB = $this->makeUser('Curie', 'Pierre');
        $userA = $this->makeUser('Curie', 'Marie');

        $sorted = Episciences_UsersManager::sortByName([$userB, $userA]);

        // sortByName comparison returns -1 when firstname a > b (descending for firstname)
        // Pierre > Marie, so Pierre comes first (returns -1 → stays first)
        $this->assertSame('Pierre', $sorted[0]->getFirstname());
        $this->assertSame('Marie', $sorted[1]->getFirstname());
    }

    public function testSortByNameWithIdenticalNamesReturnsZero(): void
    {
        $user1 = $this->makeUser('Dupont', 'Jean');
        $user2 = $this->makeUser('Dupont', 'Jean');

        $sorted = Episciences_UsersManager::sortByName([$user1, $user2]);

        $this->assertCount(2, $sorted);
        $this->assertSame('Dupont', $sorted[0]->getLastname());
        $this->assertSame('Dupont', $sorted[1]->getLastname());
    }

    public function testSortByNameWithSingleElement(): void
    {
        $user = $this->makeUser('Solo', 'Han');
        $sorted = Episciences_UsersManager::sortByName([$user]);

        $this->assertCount(1, $sorted);
        $this->assertSame('Solo', $sorted[0]->getLastname());
    }

    public function testSortByNameWithEmptyArray(): void
    {
        $sorted = Episciences_UsersManager::sortByName([]);
        $this->assertSame([], $sorted);
    }

    public function testSortByNamePreservesAllUsers(): void
    {
        $users = [
            $this->makeUser('Zola', 'Emile'),
            $this->makeUser('Balzac', 'Honoré'),
            $this->makeUser('Flaubert', 'Gustave'),
        ];

        $sorted = Episciences_UsersManager::sortByName($users);
        $this->assertCount(3, $sorted);
    }

    // -------------------------------------------------------------------------
    // skipRootFullName
    // -------------------------------------------------------------------------

    public function testSkipRootFullNameExcludesUserWithUid1(): void
    {
        $root    = $this->makeUser('Root', 'Admin', 1);
        $regular = $this->makeUser('Doe', 'Jane', 2);

        $result = Episciences_UsersManager::skipRootFullName([$root, $regular]);

        $this->assertArrayNotHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
    }

    public function testSkipRootFullNameReturnsFullNameValues(): void
    {
        $user = $this->makeUser('Doe', 'Jane', 42);
        $result = Episciences_UsersManager::skipRootFullName([$user]);

        $this->assertArrayHasKey(42, $result);
        $this->assertSame($user->getFullName(), $result[42]);
    }

    public function testSkipRootFullNameWithNoRootUser(): void
    {
        $user1 = $this->makeUser('Smith', 'Alice', 10);
        $user2 = $this->makeUser('Jones', 'Bob', 20);

        $result = Episciences_UsersManager::skipRootFullName([$user1, $user2]);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
    }

    public function testSkipRootFullNameWithOnlyRootUser(): void
    {
        $root = $this->makeUser('Root', 'Admin', 1);
        $result = Episciences_UsersManager::skipRootFullName([$root]);

        $this->assertCount(0, $result);
    }

    public function testSkipRootFullNameWithEmptyArray(): void
    {
        $result = Episciences_UsersManager::skipRootFullName([]);
        $this->assertSame([], $result);
    }

    public function testSkipRootFullNameKeysAreUserIds(): void
    {
        $user1 = $this->makeUser('Alpha', 'A', 5);
        $user2 = $this->makeUser('Beta', 'B', 99);

        $result = Episciences_UsersManager::skipRootFullName([$user1, $user2]);

        $this->assertSame([5, 99], array_keys($result));
    }

    // -------------------------------------------------------------------------
    // VALID_USER constant
    // -------------------------------------------------------------------------

    public function testValidUserConstantEqualsOne(): void
    {
        $this->assertSame(1, Episciences_UsersManager::VALID_USER);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Creates a minimal Episciences_User with lastname, firstname, and optionally uid.
     */
    private function makeUser(string $lastname, string $firstname, int $uid = 0): Episciences_User
    {
        $user = new Episciences_User();
        $user->setLastname($lastname);
        $user->setFirstname($firstname);
        if ($uid > 0) {
            $user->setUid($uid);
        }
        return $user;
    }
}
