<?php

namespace unit\library\Ccsd\User;

use Ccsd_User_Models_UserFtpQuota;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_User_Models_UserFtpQuota
 *
 * Tests pure logic: default property values, setters/getters,
 * null-fallback to constants for bytes/files limits.
 * save() requires DB and is not tested here.
 *
 * @covers Ccsd_User_Models_UserFtpQuota
 */
class Ccsd_User_Models_UserFtpQuotaTest extends TestCase
{
    private Ccsd_User_Models_UserFtpQuota $quota;

    protected function setUp(): void
    {
        $this->quota = new Ccsd_User_Models_UserFtpQuota();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testFtpQuotaConstant(): void
    {
        $this->assertSame(5368709120, Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA);
    }

    public function testFtpQuotaFilesConstant(): void
    {
        $this->assertSame(5000, Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA_FILES);
    }

    // -------------------------------------------------------------------------
    // Default property values
    // -------------------------------------------------------------------------

    public function testDefaultQuotaTypeIsUser(): void
    {
        $this->assertSame('user', $this->quota->getQuota_type());
    }

    public function testDefaultParSessionIsFalse(): void
    {
        $this->assertSame('false', $this->quota->getPar_session());
    }

    public function testDefaultLimitTypeIsSoft(): void
    {
        $this->assertSame('soft', $this->quota->getLimit_type());
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorWithOptionsPopulatesFields(): void
    {
        $quota = new Ccsd_User_Models_UserFtpQuota([
            'Id'       => 3,
            'username' => 'jdoe',
        ]);

        $this->assertSame(3, $quota->getId());
        $this->assertSame('jdoe', $quota->getUsername());
    }

    // -------------------------------------------------------------------------
    // setId / getId
    // -------------------------------------------------------------------------

    public function testSetAndGetId(): void
    {
        $this->quota->setId(10);
        $this->assertSame(10, $this->quota->getId());
    }

    // -------------------------------------------------------------------------
    // setUsername / getUsername
    // -------------------------------------------------------------------------

    public function testSetAndGetUsername(): void
    {
        $this->quota->setUsername('alice');
        $this->assertSame('alice', $this->quota->getUsername());
    }

    // -------------------------------------------------------------------------
    // setQuota_type / getQuota_type
    // -------------------------------------------------------------------------

    public function testSetAndGetQuotaType(): void
    {
        $this->quota->setQuota_type('group');
        $this->assertSame('group', $this->quota->getQuota_type());
    }

    // -------------------------------------------------------------------------
    // setPar_session / getPar_session
    // -------------------------------------------------------------------------

    public function testSetAndGetParSession(): void
    {
        $this->quota->setPar_session('true');
        $this->assertSame('true', $this->quota->getPar_session());
    }

    // -------------------------------------------------------------------------
    // setLimit_type / getLimit_type
    // -------------------------------------------------------------------------

    public function testSetAndGetLimitType(): void
    {
        $this->quota->setLimit_type('hard');
        $this->assertSame('hard', $this->quota->getLimit_type());
    }

    // -------------------------------------------------------------------------
    // bytes_up_limit — null defaults to CCSD_FTP_QUOTA
    // -------------------------------------------------------------------------

    public function testSetBytesUpLimitWithNullDefaultsToConstant(): void
    {
        $this->quota->setBytes_up_limit(null);
        $this->assertSame(Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA, $this->quota->getBytes_up_limit());
    }

    public function testSetBytesUpLimitWithExplicitValue(): void
    {
        $this->quota->setBytes_up_limit(1073741824); // 1 GB
        $this->assertSame(1073741824, $this->quota->getBytes_up_limit());
    }

    // -------------------------------------------------------------------------
    // bytes_down_limit — null defaults to CCSD_FTP_QUOTA
    // -------------------------------------------------------------------------

    public function testSetBytesDownLimitWithNullDefaultsToConstant(): void
    {
        $this->quota->setBytes_down_limit(null);
        $this->assertSame(Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA, $this->quota->getBytes_down_limit());
    }

    public function testSetBytesDownLimitWithExplicitValue(): void
    {
        $this->quota->setBytes_down_limit(2147483648);
        $this->assertSame(2147483648, $this->quota->getBytes_down_limit());
    }

    // -------------------------------------------------------------------------
    // bytes_transfer_limit — null defaults to CCSD_FTP_QUOTA
    // -------------------------------------------------------------------------

    public function testSetBytesTransferLimitWithNullDefaultsToConstant(): void
    {
        $this->quota->setBytes_transfer_limit(null);
        $this->assertSame(Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA, $this->quota->getBytes_transfer_limit());
    }

    // -------------------------------------------------------------------------
    // files_up_limit — null defaults to CCSD_FTP_QUOTA_FILES
    // -------------------------------------------------------------------------

    public function testSetFilesUpLimitWithNullDefaultsToConstant(): void
    {
        $this->quota->setFiles_up_limit(null);
        $this->assertSame(Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA_FILES, $this->quota->getFiles_up_limit());
    }

    public function testSetFilesUpLimitWithExplicitValue(): void
    {
        $this->quota->setFiles_up_limit(100);
        $this->assertSame(100, $this->quota->getFiles_up_limit());
    }

    // -------------------------------------------------------------------------
    // files_down_limit — null defaults to CCSD_FTP_QUOTA_FILES
    // -------------------------------------------------------------------------

    public function testSetFilesDownLimitWithNullDefaultsToConstant(): void
    {
        $this->quota->setFiles_down_limit(null);
        $this->assertSame(Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA_FILES, $this->quota->getFiles_down_limit());
    }

    // -------------------------------------------------------------------------
    // files_transfer_limit — null defaults to CCSD_FTP_QUOTA_FILES
    // -------------------------------------------------------------------------

    public function testSetFilesTransferLimitWithNullDefaultsToConstant(): void
    {
        $this->quota->setFiles_transfer_limit(null);
        $this->assertSame(Ccsd_User_Models_UserFtpQuota::CCSD_FTP_QUOTA_FILES, $this->quota->getFiles_transfer_limit());
    }

    // -------------------------------------------------------------------------
    // Total counters (bytes)
    // -------------------------------------------------------------------------

    public function testSetAndGetBytesUpTotal(): void
    {
        $this->quota->setBytes_up_total(512000);
        $this->assertSame(512000, $this->quota->getBytes_up_total());
    }

    public function testSetAndGetBytesDownTotal(): void
    {
        $this->quota->setBytes_down_total(1024000);
        $this->assertSame(1024000, $this->quota->getBytes_down_total());
    }

    public function testSetAndGetBytesTransferTotal(): void
    {
        $this->quota->setBytes_transfer_total(2048000);
        $this->assertSame(2048000, $this->quota->getBytes_transfer_total());
    }

    // -------------------------------------------------------------------------
    // Total counters (files)
    // -------------------------------------------------------------------------

    public function testSetAndGetFilesUpTotal(): void
    {
        $this->quota->setFiles_up_total(10);
        $this->assertSame(10, $this->quota->getFiles_up_total());
    }

    public function testSetAndGetFilesDownTotal(): void
    {
        $this->quota->setFiles_down_total(20);
        $this->assertSame(20, $this->quota->getFiles_down_total());
    }

    public function testSetAndGetFilesTransferTotal(): void
    {
        $this->quota->setFiles_transfer_total(30);
        $this->assertSame(30, $this->quota->getFiles_transfer_total());
    }

    // -------------------------------------------------------------------------
    // Fluent interface
    // -------------------------------------------------------------------------

    public function testSettersReturnFluent(): void
    {
        $result = $this->quota
            ->setId(1)
            ->setUsername('user')
            ->setQuota_type('user')
            ->setPar_session('false')
            ->setLimit_type('soft')
            ->setBytes_up_limit(null)
            ->setBytes_down_limit(null)
            ->setFiles_up_limit(null);

        $this->assertInstanceOf(Ccsd_User_Models_UserFtpQuota::class, $result);
    }
}
