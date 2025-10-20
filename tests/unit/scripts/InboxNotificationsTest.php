<?php
namespace unit\scripts;

require_once __DIR__ . '/../../../scripts/InboxNotifications.php';

use PHPUnit\Framework\TestCase;
use InboxNotifications;
use Script;



class InboxNotificationsTest extends TestCase
{



    public function testConstructorInboxNotification(): void
    {
        self::assertInstanceOf(Script::class, new InboxNotifications());
    }


    public function testCheckNotifyPayloads(): void
    {

        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);

        // Create notification with matching origin from the test payload
        $notification = (new InboxNotifications())
            ->setCoarNotifyId('https://hal.science/')
            ->setCoarNotifyType([
                'Offer',
                'coar-notify:ReviewAction'
            ])
            ->setCoarNotifyOrigin([
                'id' => 'https://hal.science/',
                'inbox' => 'https://inbox-preprod.hal.science/',
                'type' => InboxNotifications::INBOX_SERVICE_TYPE
            ]);

        self::assertTrue($notification->checkNotifyPayloads($validPayload), 'matched: origin property');

        self::assertFalse(
            $notification->setCoarNotifyOrigin([
                'id' => 'https://different.url/', // different URL should not match
                'inbox' => 'https://different.inbox/',
                'type' => InboxNotifications::INBOX_SERVICE_TYPE
            ])
                ->checkNotifyPayloads($validPayload),
            'not matched: origin property');


    }


    public function testRvCodeFromUrl(): void
    {
        $validPayload = json_decode($this->payloadTest('hal-02558198v1'), true, 512, JSON_THROW_ON_ERROR);


        self::assertEquals('revue-test', $this->getNotification()->getRvCodeFromUrl($validPayload['target']['id']));
        self::assertEquals('', $this->getNotification()->getRvCodeFromUrl(''));

    }


    public function testDataFromUrl(): void
    {

        $id = 'https://hal.science/hal-03697346v3';

        $idWithoutVersion = 'https://hal.science/hal-03697346';
        $idWithoutVersionValue = 'https://hal.science/hal-03697346v';


        self::assertIsArray($this->getNotification()->dataFromUrl($id));
        self::assertIsArray($this->getNotification()->dataFromUrl(''));

        self::assertEquals(['version' => 3, 'identifier' => 'hal-03697346' ], $this->getNotification()->dataFromUrl($id));
        self::assertEquals(['version' => 1, 'identifier' => 'hal-03697346' ], $this->getNotification()->dataFromUrl($idWithoutVersion));
        self::assertEquals(['version' => 1, 'identifier' => 'hal-03697346' ], $this->getNotification()->dataFromUrl($idWithoutVersionValue));

    }



    private function payloadTest(string $id): string
    {


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
            "id": "' . $id . '",
    "ietf:cite-as": "' . $id . '",
    "type": "sorg:AboutPage",
    "url": {
                "id": "' . $id . '/pdf",
      "media-type": "application/pdf",
      "type": [
                    "Article",
                    "sorg:ScholarlyArticle"
                ]
    }
  },
  "origin": {
            "id": "https://hal.science/",
    "inbox": "https://inbox-preprod.hal.science/",
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


    /**
     * @param string $app_env
     * @return InboxNotifications
     */
    private function getNotification(string $app_env = 'preprod'): InboxNotifications
    {

        return (new InboxNotifications())
            ->setCoarNotifyId(NOTIFY_TARGET_HAL_URL)
            ->setCoarNotifyType([
                'Offer',
                'coar-notify:ReviewAction'
            ])
            ->setCoarNotifyOrigin([
                'id' => NOTIFY_TARGET_HAL_URL, // defined in pwd.json
                'inbox' => NOTIFY_TARGET_HAL_INBOX,
                'type' => InboxNotifications::INBOX_SERVICE_TYPE
            ]);

    }
}
