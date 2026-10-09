<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\SuLogger;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Db_Adapter_Abstract;

/**
 * @covers \Episciences\User\SuLogger
 */
final class SuLoggerTest extends TestCase
{
    public function testAnonymizeIpIpv4AppliesSlash16Mask(): void
    {
        self::assertSame('1.1.0.0', SuLogger::anonymizeIp('1.1.1.1'));
        self::assertSame('192.168.0.0', SuLogger::anonymizeIp('192.168.12.34'));
        self::assertSame('10.20.0.0', SuLogger::anonymizeIp('10.20.30.40'));
    }

    public function testAnonymizeIpIpv6AppliesSlash48Mask(): void
    {
        $rawIpv6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $anonymized = SuLogger::anonymizeIp($rawIpv6);
        self::assertStringStartsWith('2001:db8:85a3:', $anonymized);
        self::assertStringEndsWith('::', $anonymized);
    }

    public function testLogInsertsRecordIntoUserSuLogTable(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->expects(self::once())
            ->method('insert')
            ->with(
                SuLogger::TABLE_NAME,
                self::callback(function (array $data): bool {
                    return $data['from_uid'] === 10
                        && $data['to_uid'] === 20
                        && $data['action'] === SuLogger::ACTION_GRANTED
                        && $data['rvid'] === 3
                        && $data['ip_address'] === '192.168.1.1'
                        && $data['user_agent'] === 'TestBrowser'
                        && $data['session_id'] === hash('sha256', 'sess123')
                        && $data['is_anonymized'] === 0
                        && isset($data['created_at']);
                })
            )
            ->willReturn(1);

        $result = SuLogger::log(
            fromUid: 10,
            toUid: 20,
            action: SuLogger::ACTION_GRANTED,
            rvid: 3,
            reason: null,
            ipAddress: '192.168.1.1',
            userAgent: 'TestBrowser',
            sessionId: 'sess123',
            details: ['key' => 'val'],
            adapter: $db
        );

        self::assertTrue($result);
    }

    public function testLogHandlesExceptionGracefully(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->expects(self::once())
            ->method('insert')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        // Avoid failing test on triggered warning
        $result = @SuLogger::log(
            fromUid: 10,
            toUid: 20,
            action: SuLogger::ACTION_DENIED,
            adapter: $db
        );

        self::assertFalse($result);
    }

    public function testLogNeverStoresTheRawSessionId(): void
    {
        $stored = null;
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->method('insert')->willReturnCallback(function (string $table, array $data) use (&$stored): int {
            $stored = $data;
            return 1;
        });

        SuLogger::log(10, 20, SuLogger::ACTION_UNSU, 3, sessionId: 'live-session-id', adapter: $db);

        self::assertIsArray($stored);
        self::assertSame(SuLogger::ACTION_UNSU, $stored['action']);
        self::assertStringNotContainsString('live-session-id', (string)json_encode($stored));
        self::assertSame(SuLogger::hashSessionId('live-session-id'), $stored['session_id']);
    }

    public function testHashSessionIdIgnoresMissingIds(): void
    {
        self::assertNull(SuLogger::hashSessionId(null));
        self::assertNull(SuLogger::hashSessionId(''));
        self::assertSame(64, strlen((string)SuLogger::hashSessionId('abc')));
    }

    public function testAnonymizeOldLogsProcessesMatchingRows(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->expects(self::once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'id' => 1,
                    'ip_address' => '1.1.1.1',
                    'details' => json_encode(['from_roles' => ['secretary'], SuLogger::DETAILS_FORWARDED_FOR => '203.0.113.9']),
                ],
                ['id' => 2, 'ip_address' => '192.168.5.5', 'details' => json_encode([SuLogger::DETAILS_FORWARDED_FOR => '203.0.113.9'])],
                ['id' => 3, 'ip_address' => null, 'details' => null],
            ]);

        $updates = [];
        $db->expects(self::exactly(3))
            ->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$updates): int {
                self::assertSame(SuLogger::TABLE_NAME, $table);
                $updates[$where['id = ?']] = $bind;
                return 1;
            });

        $count = SuLogger::anonymizeOldLogs(retentionDays: 365, adapter: $db);
        self::assertSame(3, $count);

        self::assertSame('1.1.0.0', $updates[1]['ip_address']);
        self::assertSame('192.168.0.0', $updates[2]['ip_address']);
        self::assertNull($updates[3]['ip_address']);

        foreach ($updates as $bind) {
            self::assertSame(1, $bind['is_anonymized']);
            self::assertNull($bind['user_agent']);
            self::assertNull($bind['session_id']);
            self::assertStringNotContainsString('203.0.113.9', (string)$bind['details']);
        }

        self::assertSame(['from_roles' => ['secretary']], json_decode((string)$updates[1]['details'], true));
        self::assertNull($updates[2]['details'], 'nothing left once the forwarded-for chain is removed');
    }

    public function testRequestContextTakesTheIpFromTheConnectionOnly(): void
    {
        $previousServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setHeader('Client-IP', '10.0.0.1');
        $request->setHeader('X-Forwarded-For', '203.0.113.9, 10.0.0.2');
        $request->setHeader('User-Agent', 'TestBrowser');

        try {
            $context = SuLogger::requestContext($request);
        } finally {
            $_SERVER = $previousServer;
        }

        self::assertSame('198.51.100.7', $context['ipAddress']);
        self::assertSame('TestBrowser', $context['userAgent']);
        self::assertSame([SuLogger::DETAILS_FORWARDED_FOR => '203.0.113.9, 10.0.0.2'], $context['details']);
    }

    public function testRequestContextOfANonHttpRequestIsEmpty(): void
    {
        self::assertSame(
            ['ipAddress' => null, 'userAgent' => null, 'details' => []],
            SuLogger::requestContext(new \stdClass())
        );
    }
}
