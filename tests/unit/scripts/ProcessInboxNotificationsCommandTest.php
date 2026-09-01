<?php

declare(strict_types=1);

namespace unit\scripts;

require_once __DIR__ . '/../../../scripts/ProcessInboxNotificationsCommand.php';

use Episciences\Notify\Notification;
use Episciences\Notify\NotifySourceConfig;
use Episciences\Notify\NotifySourceRegistry;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ProcessInboxNotificationsCommand;
use Symfony\Component\Console\Input\InputDefinition;

class ProcessInboxNotificationsCommandTest extends TestCase
{
    private InputDefinition $definition;
    private ProcessInboxNotificationsCommand $command;
    private Logger $logger;
    private TestHandler $testHandler;

    protected function setUp(): void
    {
        $this->command     = new ProcessInboxNotificationsCommand();
        $this->definition  = $this->command->getDefinition();
        $this->testHandler = new TestHandler();
        $this->logger      = new Logger('testLogger');
        $this->logger->pushHandler($this->testHandler);
    }

    // -------------------------------------------------------------------------
    // Command metadata & options
    // -------------------------------------------------------------------------

    public function testCommandNameAndAliases(): void
    {
        $this->assertSame('notify:process-inbox', $this->command->getName());
        $this->assertContains('inbox:process', $this->command->getAliases());
    }

    public function testCommandHasDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testDryRunIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('dry-run'));
        $this->assertFalse($this->definition->getOption('dry-run')->acceptValue(), 'dry-run must be a boolean flag');
    }

    public function testDelNotifsOption(): void
    {
        $this->assertTrue($this->definition->hasOption('delNotifs'));
        $option = $this->definition->getOption('delNotifs');
        $this->assertSame('d', $option->getShortcut());
        $this->assertFalse($option->acceptValue(), 'delNotifs must be a boolean flag');
    }

    public function testLimitOption(): void
    {
        $this->assertTrue($this->definition->hasOption('limit'));
        $option = $this->definition->getOption('limit');
        $this->assertSame('l', $option->getShortcut());
        $this->assertTrue($option->isValueRequired(), 'limit must require a value');
        $this->assertSame(1000, $option->getDefault());
    }

    public function testNotificationIdOption(): void
    {
        $this->assertTrue($this->definition->hasOption('notification-id'));
        $this->assertTrue($this->definition->getOption('notification-id')->isValueRequired());
    }

    // -------------------------------------------------------------------------
    // Payload checking & validation
    // -------------------------------------------------------------------------

    public function testCheckNotifyPayloadsReturnsTrueForValidPayload(): void
    {
        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);

        $result = $this->command->checkNotifyPayloads($validPayload, $this->buildHalSource(), $this->logger);

        $this->assertTrue($result);
        $this->assertFalse($this->testHandler->hasWarningRecords());
    }

    public function testCheckNotifyPayloadsReturnsFalseAndLogsWarningForWrongOrigin(): void
    {
        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);

        $source = new NotifySourceConfig(
            repoId:       1,
            label:        'DIFFERENT',
            originId:     'https://different.url/',
            originInbox:  'https://different.inbox/',
            acceptedTypes: ['Offer', 'coar-notify:ReviewAction']
        );

        $result = $this->command->checkNotifyPayloads($validPayload, $source, $this->logger);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasWarningRecords());
    }

    public function testCheckNotifyPayloadsReturnsFalseAndLogsWarningForWrongType(): void
    {
        $payload         = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);
        $payload['type'] = ['Announce', 'coar-notify:EndorsementAction'];

        $result = $this->command->checkNotifyPayloads($payload, $this->buildHalSource(), $this->logger);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasWarningRecords());
    }

    // -------------------------------------------------------------------------
    // URL parsing & Journal code extraction
    // -------------------------------------------------------------------------

    public function testRvCodeFromUrlExtractsCode(): void
    {
        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('revue-test', $this->command->getRvCodeFromUrl($validPayload['target']['id'], $this->logger));
    }

    public function testRvCodeFromUrlHandlesEmptyOrNull(): void
    {
        $this->assertSame('', $this->command->getRvCodeFromUrl('', $this->logger));
        $this->assertSame('', $this->command->getRvCodeFromUrl(null, $this->logger));
    }

    public function testDataFromUrlExtractsIdentifierAndVersion(): void
    {
        $res1 = $this->command->dataFromUrl('https://hal.science/hal-03697346v3');
        $this->assertSame('hal-03697346', $res1['identifier']);
        $this->assertSame(3, $res1['version']);

        $res2 = $this->command->dataFromUrl('https://hal.science/hal-03697346');
        $this->assertSame('hal-03697346', $res2['identifier']);
        $this->assertSame(1, $res2['version']);

        $res3 = $this->command->dataFromUrl('');
        $this->assertSame('', $res3['identifier']);
        $this->assertSame(1, $res3['version']);
    }

    // -------------------------------------------------------------------------
    // Actor UID extraction
    // -------------------------------------------------------------------------

    public function testExtractUidVariants(): void
    {
        $this->assertSame(1099714, $this->command->extractUid('1099714'));
        $this->assertSame(1099714, $this->command->extractUid('1099714@ccsd.cnrs.fr'));
        $this->assertSame(1099714, $this->command->extractUid('mailto:1099714@ccsd.cnrs.fr'));
        $this->assertSame(1099714, $this->command->extractUid('https://hal.science/user/1099714'));
        $this->assertSame(999, $this->command->extractUid('mailto:999@example.org'));
        $this->assertSame(0, $this->command->extractUid('not-a-number'));
    }

    // -------------------------------------------------------------------------
    // notificationsProcess error logging
    // -------------------------------------------------------------------------

    public function testNotificationsProcessLogsCriticalOnInvalidJson(): void
    {
        $notification = Notification::fromRow([
            'id'        => 'urn:uuid:test-invalid-json',
            'fromId'    => 'https://hal.science/',
            'toId'      => 'https://revue-test.episciences.org',
            'type'      => '["Offer","coar-notify:ReviewAction"]',
            'status'    => '0',
            'original'  => 'INVALID-JSON-PAYLOAD',
            'direction' => Notification::DIRECTION_INBOUND,
        ]);

        $registry = new NotifySourceRegistry([]);
        $result   = $this->command->notificationsProcess($notification, $registry, $this->logger);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasCriticalRecords(), 'Must log a critical record when JSON is malformed');
    }

    public function testNotificationsProcessLogsWarningOnUnknownOriginInbox(): void
    {
        $payload = $this->payloadTest('hal-02558198v1');
        $notification = Notification::fromRow([
            'id'        => 'urn:uuid:test-unknown-origin',
            'fromId'    => 'https://hal.science/',
            'toId'      => 'https://revue-test.episciences.org',
            'type'      => '["Offer","coar-notify:ReviewAction"]',
            'status'    => '0',
            'original'  => $payload,
            'direction' => Notification::DIRECTION_INBOUND,
        ]);

        $registry = new NotifySourceRegistry([]); // Empty registry
        $result   = $this->command->notificationsProcess($notification, $registry, $this->logger);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasWarningRecords(), 'Must log a warning record when origin inbox is unknown');
    }

    public function testNotificationsProcessLogsWarningOnMissingActor(): void
    {
        $payloadArr = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);
        unset($payloadArr['actor']);
        $payloadJson = json_encode($payloadArr, JSON_THROW_ON_ERROR);

        $notification = Notification::fromRow([
            'id'        => 'urn:uuid:test-missing-actor',
            'fromId'    => 'https://hal.science/',
            'toId'      => 'https://revue-test.episciences.org',
            'type'      => '["Offer","coar-notify:ReviewAction"]',
            'status'    => '0',
            'original'  => $payloadJson,
            'direction' => Notification::DIRECTION_INBOUND,
        ]);

        $source   = $this->buildHalSource();
        $registry = new NotifySourceRegistry([$source->getOriginInbox() => $source]);
        $result   = $this->command->notificationsProcess($notification, $registry, $this->logger);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasWarningRecords());
    }

    public function testNotificationsProcessLogsWarningOnMissingOrInvalidObject(): void
    {
        $payloadArr = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);
        $payloadArr['object']['ietf:cite-as'] = 'not-a-valid-url';
        $payloadJson = json_encode($payloadArr, JSON_THROW_ON_ERROR);

        $notification = Notification::fromRow([
            'id'        => 'urn:uuid:test-invalid-object',
            'fromId'    => 'https://hal.science/',
            'toId'      => 'https://revue-test.episciences.org',
            'type'      => '["Offer","coar-notify:ReviewAction"]',
            'status'    => '0',
            'original'  => $payloadJson,
            'direction' => Notification::DIRECTION_INBOUND,
        ]);

        $source   = $this->buildHalSource();
        $registry = new NotifySourceRegistry([$source->getOriginInbox() => $source]);
        $result   = $this->command->notificationsProcess($notification, $registry, $this->logger);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasWarningRecords());
    }

    public function testNotificationsProcessLogsWarningOnUnknownJournal(): void
    {
        $payloadArr = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);
        $payloadArr['target']['id'] = 'https://unknown-journal-xyz.episciences.org';
        $payloadJson = json_encode($payloadArr, JSON_THROW_ON_ERROR);

        $notification = Notification::fromRow([
            'id'        => 'urn:uuid:test-unknown-journal',
            'fromId'    => 'https://hal.science/',
            'toId'      => 'https://unknown-journal-xyz.episciences.org',
            'type'      => '["Offer","coar-notify:ReviewAction"]',
            'status'    => '0',
            'original'  => $payloadJson,
            'direction' => Notification::DIRECTION_INBOUND,
        ]);

        $source   = $this->buildHalSource();
        $registry = new NotifySourceRegistry([$source->getOriginInbox() => $source]);
        $result   = $this->command->notificationsProcess($notification, $registry, $this->logger);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasWarningRecords());
    }

    public function testAddSubmissionLogsCriticalOnPaperInstantiationFailure(): void
    {
        $journal = $this->createMock(\Episciences_Review::class);
        $journal->method('getRvid')->willReturn(1);

        // Invalid data structure causing Paper instantiation to fail or throw
        $invalidData = ['identifier' => '', 'repoid' => 0];

        $result = $this->command->addSubmission($journal, $invalidData, null, $this->logger, true);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasCriticalRecords());
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testConstants(): void
    {
        $this->assertContains('https://www.w3.org/ns/activitystreams', ProcessInboxNotificationsCommand::COAR_NOTIFY_AT_CONTEXT);
        $this->assertContains('https://purl.org/coar/notify', ProcessInboxNotificationsCommand::COAR_NOTIFY_AT_CONTEXT);
        $this->assertSame(['Service'], ProcessInboxNotificationsCommand::INBOX_SERVICE_TYPE);
        $this->assertSame('ietf:cite-as', ProcessInboxNotificationsCommand::OBJECT_IDENTIFIER_URL);
        $this->assertSame('firstSubmission', ProcessInboxNotificationsCommand::FIRST_SUBMISSION);
        $this->assertSame('newVersion', ProcessInboxNotificationsCommand::NEW_VERSION);
        $this->assertSame('versionUpdate', ProcessInboxNotificationsCommand::VERSION_UPDATE);
        $this->assertSame('previousPaperObject', ProcessInboxNotificationsCommand::PAPER_CONTEXT);
    }

    // -------------------------------------------------------------------------
    // Test helpers
    // -------------------------------------------------------------------------

    private function buildHalSource(): NotifySourceConfig
    {
        return new NotifySourceConfig(
            repoId:       1,
            label:        'HAL',
            originId:     defined('NOTIFY_TARGET_HAL_URL') ? NOTIFY_TARGET_HAL_URL : 'https://hal.science/',
            originInbox:  defined('NOTIFY_TARGET_HAL_INBOX') ? NOTIFY_TARGET_HAL_INBOX : 'https://inbox-preprod.hal.science/',
            acceptedTypes: ['Offer', 'coar-notify:ReviewAction']
        );
    }

    private function payloadTest(string $id): string
    {
        $halUrl     = 'https://hal.science/' . $id;
        $inboxUrl   = defined('NOTIFY_TARGET_HAL_INBOX') ? NOTIFY_TARGET_HAL_INBOX : 'https://inbox-preprod.hal.science/';

        return json_encode([
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://purl.org/coar/notify',
            ],
            'actor' => [
                'id'   => '1099714',
                'name' => 'Josiah Carberry',
                'type' => 'Person',
            ],
            'id' => 'urn:uuid:0370c0fb-bb78-4a9b-87f5-bed307a509dd',
            'object' => [
                'id'           => $halUrl,
                'ietf:cite-as' => $halUrl,
                'type'         => 'sorg:AboutPage',
                'ietf:item'    => [
                    'id'        => $halUrl . '/pdf',
                    'type'      => ['Article', 'sorg:ScholarlyArticle'],
                    'mediaType' => 'application/pdf',
                ],
            ],
            'origin' => [
                'id'    => 'https://hal.science/',
                'inbox' => $inboxUrl,
                'type'  => 'Service',
            ],
            'target' => [
                'id'    => 'https://revue-test.episciences.org',
                'inbox' => 'https://www.episciences.org/',
                'type'  => 'Service',
            ],
            'type' => [
                'Offer',
                'coar-notify:ReviewAction',
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
