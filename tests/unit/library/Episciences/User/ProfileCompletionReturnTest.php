<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\ProfileCompletionReturn;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Session;

/**
 * The page an account without Episciences data asked for is displayed once its profile is saved.
 */
final class ProfileCompletionReturnTest extends TestCase
{
    private bool $previousSessionTestMode;

    /** @var array<mixed>|null */
    private ?array $previousSession;

    protected function setUp(): void
    {
        $this->previousSessionTestMode = Zend_Session::$_unitTestEnabled;
        $this->previousSession = $_SESSION ?? null;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->previousSession;
        Zend_Session::$_unitTestEnabled = $this->previousSessionTestMode;
    }

    private function request(string $method, string $uri): Zend_Controller_Request_HttpTestCase
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($method)->setRequestUri($uri);

        return $request;
    }

    public function testPageAskedForIsGivenBackOnce(): void
    {
        ProfileCompletionReturn::remember($this->request('GET', '/reviewer/invitation/id/12/token/abc'));

        self::assertSame('/reviewer/invitation/id/12/token/abc', ProfileCompletionReturn::take());
        self::assertNull(ProfileCompletionReturn::take(), 'forgotten once read');
    }

    public function testNothingIsGivenBackWithoutAPageAskedFor(): void
    {
        self::assertNull(ProfileCompletionReturn::take());
    }

    public function testFormSentIsNotRemembered(): void
    {
        ProfileCompletionReturn::remember($this->request('POST', '/reviewer/invitation/id/12'));

        self::assertNull(ProfileCompletionReturn::take());
    }

    public function testScriptCallIsNotRemembered(): void
    {
        $request = $this->request('GET', '/paper/ajax-list');
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');

        ProfileCompletionReturn::remember($request);

        self::assertNull(ProfileCompletionReturn::take());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherHostProvider(): array
    {
        return [
            'protocol-relative' => ['//evil.example/reviewer/invitation'],
            'backslash' => ['/\\evil.example/reviewer/invitation'],
        ];
    }

    /**
     * @dataProvider otherHostProvider
     */
    public function testNeverGoesBackToAnotherHost(string $uri): void
    {
        ProfileCompletionReturn::remember($this->request('GET', '/user/dashboard'));
        ProfileCompletionReturn::remember($this->request('GET', $uri));

        self::assertNull(ProfileCompletionReturn::take(), 'and the page remembered before is not given back either');
    }
}
