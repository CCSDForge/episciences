<?php

namespace unit\scripts;

require_once __DIR__ . '/../../../scripts/InboxNotifications.php';

use Episciences\Notify\NotifySourceConfig;
use InboxNotifications;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Script;

class InboxNotificationsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorInboxNotification(): void
    {
        self::assertInstanceOf(Script::class, new InboxNotifications());
    }

    // -------------------------------------------------------------------------
    // checkNotifyPayloads — delegates to PayloadValidator
    // -------------------------------------------------------------------------

    public function testCheckNotifyPayloadsReturnsTrueForValidPayload(): void
    {
        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue(
            (new InboxNotifications())->checkNotifyPayloads($validPayload, $this->buildHalSource()),
            'matched: origin property'
        );
    }

    public function testCheckNotifyPayloadsReturnsFalseForWrongOrigin(): void
    {
        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);

        $source = new NotifySourceConfig(
            repoId:       1,
            label:        'DIFFERENT',
            originId:     'https://different.url/',
            originInbox:  'https://different.inbox/',
        );

        self::assertFalse(
            (new InboxNotifications())->checkNotifyPayloads($validPayload, $source),
            'not matched: origin property'
        );
    }

    public function testCheckNotifyPayloadsReturnsFalseForWrongType(): void
    {
        $payload         = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);
        $payload['type'] = ['Announce', 'coar-notify:EndorsementAction'];

        self::assertFalse(
            (new InboxNotifications())->checkNotifyPayloads($payload, $this->buildHalSource()),
            'not matched: type property'
        );
    }

    // -------------------------------------------------------------------------
    // getRvCodeFromUrl — delegates to PreprintUrlParser
    // -------------------------------------------------------------------------

    public function testRvCodeFromUrlExtractsCodeFromValidPayload(): void
    {
        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('revue-test', (new InboxNotifications())->getRvCodeFromUrl($validPayload['target']['id']));
    }

    public function testRvCodeFromUrlReturnsEmptyStringForEmptyInput(): void
    {
        self::assertEquals('', (new InboxNotifications())->getRvCodeFromUrl(''));
    }

    public function testRvCodeFromUrlReturnsEmptyStringForNull(): void
    {
        self::assertEquals('', (new InboxNotifications())->getRvCodeFromUrl(null));
    }

    // -------------------------------------------------------------------------
    // dataFromUrl — delegates to PreprintUrlParser
    // -------------------------------------------------------------------------

    public function testDataFromUrlReturnsArray(): void
    {
        self::assertIsArray((new InboxNotifications())->dataFromUrl('https://hal.science/hal-03697346v3'));
        self::assertIsArray((new InboxNotifications())->dataFromUrl(''));
    }

    public function testDataFromUrlExtractsVersionAndIdentifier(): void
    {
        $result = (new InboxNotifications())->dataFromUrl('https://hal.science/hal-03697346v3');

        self::assertEquals(['version' => 3, 'identifier' => 'hal-03697346'], $result);
    }

    public function testDataFromUrlDefaultsToVersionOneWhenAbsent(): void
    {
        $result = (new InboxNotifications())->dataFromUrl('https://hal.science/hal-03697346');

        self::assertEquals(['version' => 1, 'identifier' => 'hal-03697346'], $result);
    }

    public function testDataFromUrlDefaultsToVersionOneWhenVersionMarkerHasNoDigit(): void
    {
        $result = (new InboxNotifications())->dataFromUrl('https://hal.science/hal-03697346v');

        self::assertEquals(['version' => 1, 'identifier' => 'hal-03697346'], $result);
    }

    // -------------------------------------------------------------------------
    // extractUid — protected, tested via reflection
    // -------------------------------------------------------------------------

    public function testExtractUidFromPlainInteger(): void
    {
        self::assertSame(1099714, $this->callExtractUid('1099714'));
    }

    public function testExtractUidFromMailtoScheme(): void
    {
        self::assertSame(1099714, $this->callExtractUid('mailto:1099714@ccsd.cnrs.fr'));
    }

    public function testExtractUidFromEmailString(): void
    {
        self::assertSame(1099714, $this->callExtractUid('1099714@hal.science'));
    }

    public function testExtractUidFromMailtoWithDifferentDomain(): void
    {
        self::assertSame(42, $this->callExtractUid('mailto:42@some-institution.edu'));
    }

    public function testExtractUidReturnsZeroForNonNumericInput(): void
    {
        self::assertSame(0, $this->callExtractUid('not-a-number'));
    }

    public function testExtractUidFromMailtoStripsSchemeBeforeEmail(): void
    {
        // Ensures mailto: is stripped first, then @ suffix
        self::assertSame(999, $this->callExtractUid('mailto:999@example.org'));
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testCoarNotifyAtContextConstant(): void
    {
        self::assertContains('https://www.w3.org/ns/activitystreams', InboxNotifications::COAR_NOTIFY_AT_CONTEXT);
        self::assertContains('https://purl.org/coar/notify', InboxNotifications::COAR_NOTIFY_AT_CONTEXT);
    }

    public function testInboxServiceTypeConstant(): void
    {
        self::assertSame(['Service'], InboxNotifications::INBOX_SERVICE_TYPE);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Calls the protected extractUid() method via reflection.
     */
    private function callExtractUid(string $actorId): int
    {
        $method = new ReflectionMethod(InboxNotifications::class, 'extractUid');
        $method->setAccessible(true);

        return $method->invoke(new InboxNotifications(), $actorId);
    }

    /**
     * Builds a NotifySourceConfig representing the HAL test source.
     */
    private function buildHalSource(): NotifySourceConfig
    {
        return new NotifySourceConfig(
            repoId:       1,
            label:        'HAL',
            originId:     NOTIFY_TARGET_HAL_URL,
            originInbox:  NOTIFY_TARGET_HAL_INBOX,
            acceptedTypes: ['Offer', 'coar-notify:ReviewAction'],
        );
    }

    private function payloadTest(string $id): string
    {
        $halUrl = 'https://hal.science/' . $id;
        return '{
            "@context": [
            "https://www.w3.org/ns/activitystreams",
            "https://purl.org/coar/notify"
        ],
  "actor": {
            "id": "1099714",
    "name": "Josiah Carberry",
    "type": "Person"
  },
  "id": "urn:uuid:0370c0fb-bb78-4a9b-87f5-bed307a509dd",
  "object": {
            "id": "' . $halUrl . '",
    "ietf:cite-as": "' . $halUrl . '",
    "type": "sorg:AboutPage",
    "ietf:item": {
                "id": "' . $halUrl . '/pdf",
      "type": [
                    "Article",
                    "sorg:ScholarlyArticle"
                ],
      "mediaType": "application/pdf"
    }
  },
  "origin": {
            "id": "https://hal.science/",
    "inbox": "' . NOTIFY_TARGET_HAL_INBOX . '",
    "type": "Service"
  },
  "target": {
            "id": "https://revue-test.episciences.org",
    "inbox": "https://www.episciences.org/",
    "type": "Service"
  },
  "type": [
            "Offer",
            "coar-notify:ReviewAction"
        ]
}';
    }
}
