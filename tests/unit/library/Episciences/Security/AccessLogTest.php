<?php

declare(strict_types=1);

namespace unit\library\Episciences\Security;

use Episciences\Security\AccessLog;
use Episciences_User;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionProperty;
use RuntimeException;
use Stringable;
use Zend_Auth;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Front;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;

require_once __DIR__ . '/SecurityLogCaptureTrait.php';

/**
 * @covers \Episciences\Security\AccessLog
 */
final class AccessLogTest extends TestCase
{
    use SecurityLogCaptureTrait;

    private ?Zend_Controller_Request_Abstract $previousRequest;
    private Zend_Auth_Storage_Interface $previousStorage;
    /** @var array<mixed> */
    private array $previousGet;
    /** @var array<mixed> */
    private array $previousPost;

    protected function setUp(): void
    {
        // Zend_Controller_Request_Http reads and writes the superglobals
        $this->previousGet = $_GET;
        $this->previousPost = $_POST;
        $_GET = [];
        $_POST = [];

        $this->previousRequest = Zend_Controller_Front::getInstance()->getRequest();
        $this->previousStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
    }

    protected function tearDown(): void
    {
        // setRequest() refuses null, the request slot of the singleton is restored directly
        $property = new ReflectionProperty(Zend_Controller_Front::class, '_request');
        $property->setValue(Zend_Controller_Front::getInstance(), $this->previousRequest);
        Zend_Auth::getInstance()->setStorage($this->previousStorage);
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REQUEST_METHOD']);
        $_GET = $this->previousGet;
        $_POST = $this->previousPost;
    }

    private function currentRequest(): Zend_Controller_Request_Http
    {
        $request = new Zend_Controller_Request_Http();
        $request->setControllerName('administratepaper')->setActionName('view');
        Zend_Controller_Front::getInstance()->setRequest($request);

        return $request;
    }

    private function onlyRecord(): LogRecord
    {
        $records = $this->securityLogRecords();
        self::assertCount(1, $records);

        return $records[0];
    }

    public function testRecordCarriesTheUserJournalRouteAndClientContext(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_USER_AGENT'] = 'Test agent';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->currentRequest()->setParam('id', '42');

        $user = new Episciences_User(['UID' => 4242]);
        $user->setRoles([RVID => ['member']]);
        Zend_Auth::getInstance()->getStorage()->write($user);

        AccessLog::denied(AccessLog::ACL_DENIED, 'role_check', ['resource' => 'administratepaper-view']);

        $record = $this->onlyRecord();
        self::assertSame(AccessLog::ACL_DENIED, $record->message);
        self::assertSame(AccessLog::CHANNEL, $record->channel);
        $context = $record->context;
        self::assertSame(4242, $context['uid']);
        self::assertSame(['member'], $context['roles']);
        self::assertSame(RVID, $context['rvid']);
        self::assertSame('administratepaper', $context['controller']);
        self::assertSame('view', $context['action']);
        self::assertSame('GET', $context['method']);
        self::assertFalse($context['xhr']);
        self::assertSame('192.0.2.10', $context['ip']);
        self::assertSame('198.51.100.7', $context['forwarded_for']);
        self::assertSame('Test agent', $context['user_agent']);
        self::assertSame(['id' => '42'], $context['params']);
        self::assertSame('role_check', $context['reason']);
        self::assertSame('administratepaper-view', $context['resource']);
    }

    public function testGuestIsRecordedWithoutUid(): void
    {
        $this->currentRequest();

        AccessLog::denied(AccessLog::AUTH_REQUIRED);

        $context = $this->onlyRecord()->context;
        self::assertNull($context['uid']);
        self::assertSame(['guest'], $context['roles']);
        self::assertArrayNotHasKey('reason', $context);
    }

    public function testPostBodyIsNeverRecorded(): void
    {
        $request = $this->currentRequest();
        $request->setPost('comment', 'private text');
        $request->setQuery('page', '2');

        AccessLog::denied(AccessLog::CSRF_FAILED, AccessLog::REASON_CSRF_MISMATCH);

        $params = $this->onlyRecord()->context['params'];
        self::assertSame(['page' => '2'], $params);
    }

    public function testCredentialLikeParametersAreMasked(): void
    {
        $request = $this->currentRequest();
        $request->setParam('token', 'secret-route-token');
        $request->setQuery('authToken', 'secret-query-token');

        AccessLog::denied(AccessLog::TOKEN_INVALID);

        $params = $this->onlyRecord()->context['params'];
        self::assertSame('***', $params['token']);
        self::assertSame('***', $params['authToken']);
    }

    public function testLongParameterValuesAreTruncated(): void
    {
        $this->currentRequest()->setParam('q', str_repeat('a', 1000));

        AccessLog::denied(AccessLog::ACL_DENIED);

        self::assertSame(200, mb_strlen($this->onlyRecord()->context['params']['q']));
    }

    public function testEventsUseTheirDefaultLevel(): void
    {
        AccessLog::denied(AccessLog::AUTH_REQUIRED);
        AccessLog::denied(AccessLog::HTTP_FORBIDDEN);
        AccessLog::denied(AccessLog::ACL_DENIED);
        AccessLog::denied(AccessLog::ACL_MISSING);

        $levels = array_map(static fn(LogRecord $record): Level => $record->level, $this->securityLogRecords());
        self::assertSame([Level::Info, Level::Notice, Level::Warning, Level::Error], $levels);
    }

    public function testExplicitLevelWins(): void
    {
        AccessLog::denied(AccessLog::CSRF_FAILED, AccessLog::REASON_CSRF_NO_SESSION_TOKEN, [], Level::Notice);

        self::assertSame(Level::Notice, $this->onlyRecord()->level);
    }

    public function testRequestIsFlaggedOnceLogged(): void
    {
        self::assertFalse(AccessLog::wasLogged());

        AccessLog::denied(AccessLog::ACL_DENIED);
        self::assertTrue(AccessLog::wasLogged());

        AccessLog::reset();
        self::assertFalse(AccessLog::wasLogged());
    }

    public function testAFailingLoggerNeverBreaksTheRequest(): void
    {
        AccessLog::setLogger(new class extends AbstractLogger {
            public function log($level, string|Stringable $message, array $context = []): void
            {
                throw new RuntimeException('disk full');
            }
        });
        $errorLog = ini_set('error_log', sys_get_temp_dir() . '/access-log-test-' . getmypid() . '.log');

        try {
            AccessLog::denied(AccessLog::ACL_DENIED);
        } finally {
            @unlink((string)ini_get('error_log'));
            ini_set('error_log', $errorLog === false ? '' : $errorLog);
        }

        self::assertTrue(AccessLog::wasLogged());
    }
}
