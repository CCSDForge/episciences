<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\SuLogger;
use PHPUnit\Framework\TestCase;
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
                        && $data['session_id'] === 'sess123'
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

    public function testAnonymizeOldLogsProcessesMatchingRows(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->expects(self::once())
            ->method('fetchAll')
            ->willReturn([
                ['id' => 1, 'ip_address' => '1.1.1.1'],
                ['id' => 2, 'ip_address' => '192.168.5.5'],
            ]);

        $db->expects(self::exactly(2))
            ->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where): int {
                self::assertSame(SuLogger::TABLE_NAME, $table);
                self::assertSame(1, $bind['is_anonymized']);
                self::assertTrue($bind['ip_address'] === '1.1.0.0' || $bind['ip_address'] === '192.168.0.0');
                return 1;
            });

        $count = SuLogger::anonymizeOldLogs(retentionDays: 365, adapter: $db);
        self::assertSame(2, $count);
    }
}
