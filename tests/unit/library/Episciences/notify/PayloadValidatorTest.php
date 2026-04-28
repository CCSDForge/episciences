<?php

declare(strict_types=1);

namespace unit\library\Episciences\notify;

use Episciences\Notify\PayloadValidator;
use Episciences\Notify\ValidationResult;
use PHPUnit\Framework\TestCase;

class PayloadValidatorTest extends TestCase
{
    private const EXPECTED_TYPE         = ['Offer', 'coar-notify:ReviewAction'];
    private const EXPECTED_ORIGIN_INBOX = 'https://inbox-preprod.hal.science/';
    private const EXPECTED_DOMAIN       = 'episciences.org';

    private PayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PayloadValidator(
            self::EXPECTED_TYPE,
            self::EXPECTED_ORIGIN_INBOX,
            self::EXPECTED_DOMAIN
        );
    }

    // -------------------------------------------------------------------------
    // Valid payloads
    // -------------------------------------------------------------------------

    public function testValidPayloadReturnsSuccess(): void
    {
        $result = $this->validator->validate($this->buildValidPayload());

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->isValid());
        self::assertNull($result->getErrorMessage());
    }

    public function testAtContextWithPreferredCoarNotifyUriPasses(): void
    {
        $payload                = $this->buildValidPayload();
        $payload['@context'][1] = 'https://coar-notify.net';

        self::assertTrue($this->validator->validate($payload)->isValid());
    }

    // -------------------------------------------------------------------------
    // @context validation
    // -------------------------------------------------------------------------

    public function testMissingAtContextReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['@context']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('@context', $result->getErrorMessage());
    }

    public function testAtContextNotArrayReturnsFailure(): void
    {
        $payload             = $this->buildValidPayload();
        $payload['@context'] = 'https://www.w3.org/ns/activitystreams';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('@context', $result->getErrorMessage());
    }

    public function testAtContextWithoutActivityStreamsReturnsFailure(): void
    {
        $payload             = $this->buildValidPayload();
        $payload['@context'] = ['https://purl.org/coar/notify'];

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('@context', $result->getErrorMessage());
        self::assertStringContainsString('https://www.w3.org/ns/activitystreams', $result->getErrorMessage());
    }

    public function testAtContextWithoutCoarNotifyUriReturnsFailure(): void
    {
        $payload             = $this->buildValidPayload();
        $payload['@context'] = ['https://www.w3.org/ns/activitystreams', 'https://example.org/other'];

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('@context', $result->getErrorMessage());
    }

    // -------------------------------------------------------------------------
    // id validation
    // -------------------------------------------------------------------------

    public function testMissingIdReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['id']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'id'", $result->getErrorMessage());
    }

    public function testIdAsHttpUriPasses(): void
    {
        $payload        = $this->buildValidPayload();
        $payload['id']  = 'https://example.org/activities/1';

        self::assertTrue($this->validator->validate($payload)->isValid());
    }

    public function testIdAsUrnUuidPasses(): void
    {
        $payload        = $this->buildValidPayload();
        $payload['id']  = 'urn:uuid:0370c0fb-bb78-4a9b-87f5-bed307a509dd';

        self::assertTrue($this->validator->validate($payload)->isValid());
    }

    public function testIdNotAUriReturnsFailure(): void
    {
        $payload        = $this->buildValidPayload();
        $payload['id']  = 'not-a-uri';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'id'", $result->getErrorMessage());
    }

    // -------------------------------------------------------------------------
    // Origin validation
    // -------------------------------------------------------------------------

    public function testMissingOriginIdReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['origin']['id']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'origin.id'", $result->getErrorMessage());
    }

    public function testInvalidOriginIdReturnsFailure(): void
    {
        $payload                 = $this->buildValidPayload();
        $payload['origin']['id'] = 'not-a-url';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'origin.id'", $result->getErrorMessage());
    }

    public function testMissingOriginTypeReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['origin']['type']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'origin.type'", $result->getErrorMessage());
    }

    public function testWrongOriginInboxReturnFailure(): void
    {
        $payload                    = $this->buildValidPayload();
        $payload['origin']['inbox'] = 'https://different-inbox.example.org/';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'origin'", $result->getErrorMessage());
        self::assertStringContainsString(self::EXPECTED_ORIGIN_INBOX, $result->getErrorMessage());
    }

    public function testMissingOriginInboxReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['origin']['inbox']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'origin'", $result->getErrorMessage());
    }

    public function testMissingOriginKeyReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['origin']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
    }

    // -------------------------------------------------------------------------
    // Type validation
    // -------------------------------------------------------------------------

    public function testWrongTypeReturnsFailure(): void
    {
        $payload         = $this->buildValidPayload();
        $payload['type'] = ['Announce', 'coar-notify:EndorsementAction'];

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'type'", $result->getErrorMessage());
        self::assertStringContainsString(implode(', ', self::EXPECTED_TYPE), $result->getErrorMessage());
    }

    public function testPartialTypeMatchReturnsFailure(): void
    {
        $payload         = $this->buildValidPayload();
        $payload['type'] = ['Offer']; // only one of the two expected types

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'type'", $result->getErrorMessage());
    }

    public function testEmptyTypeReturnsFailure(): void
    {
        $payload         = $this->buildValidPayload();
        $payload['type'] = [];

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
    }

    public function testMissingTypeKeyReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['type']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
    }

    // -------------------------------------------------------------------------
    // Object validation
    // -------------------------------------------------------------------------

    public function testMissingObjectIdReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['object']['id']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.id'", $result->getErrorMessage());
    }

    public function testMissingObjectTypeReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['object']['type']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.type'", $result->getErrorMessage());
    }

    public function testMissingObjectKeyReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['object']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
    }

    public function testObjectIdNotHttpUriReturnsFailure(): void
    {
        $payload                  = $this->buildValidPayload();
        $payload['object']['id']  = 'not-a-uri';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.id'", $result->getErrorMessage());
    }

    public function testMissingIetfItemReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['object']['ietf:item']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.ietf:item'", $result->getErrorMessage());
    }

    public function testIetfItemNotArrayReturnsFailure(): void
    {
        $payload                        = $this->buildValidPayload();
        $payload['object']['ietf:item'] = 'not-an-array';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.ietf:item'", $result->getErrorMessage());
    }

    public function testIetfItemMissingIdReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['object']['ietf:item']['id']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.ietf:item.id'", $result->getErrorMessage());
    }

    public function testIetfItemIdNotHttpUriReturnsFailure(): void
    {
        $payload                               = $this->buildValidPayload();
        $payload['object']['ietf:item']['id']  = 'not-a-uri';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.ietf:item.id'", $result->getErrorMessage());
    }

    public function testIetfItemMissingTypeReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['object']['ietf:item']['type']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.ietf:item.type'", $result->getErrorMessage());
    }

    public function testIetfItemMissingMediaTypeReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['object']['ietf:item']['mediaType']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'object.ietf:item.mediaType'", $result->getErrorMessage());
    }

    // -------------------------------------------------------------------------
    // Target validation
    // -------------------------------------------------------------------------

    public function testMissingTargetIdReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['target']['id']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('Not valid notify target', $result->getErrorMessage());
    }

    public function testInvalidTargetUrlReturnsFailure(): void
    {
        $payload                 = $this->buildValidPayload();
        $payload['target']['id'] = 'not-a-valid-url';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('Not valid notify target', $result->getErrorMessage());
    }

    public function testTargetUrlWithWrongDomainReturnsFailure(): void
    {
        $payload                 = $this->buildValidPayload();
        $payload['target']['id'] = 'https://revue-test.other-domain.org';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('Not valid notify target', $result->getErrorMessage());
    }

    public function testTargetUrlWithCorrectDomainPasses(): void
    {
        $payload                 = $this->buildValidPayload();
        $payload['target']['id'] = 'https://another-journal.episciences.org';

        self::assertTrue($this->validator->validate($payload)->isValid());
    }

    public function testMissingTargetTypeReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['target']['type']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'target.type'", $result->getErrorMessage());
    }

    public function testMissingTargetInboxReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['target']['inbox']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'target.inbox'", $result->getErrorMessage());
    }

    public function testInvalidTargetInboxReturnsFailure(): void
    {
        $payload                   = $this->buildValidPayload();
        $payload['target']['inbox'] = 'not-a-url';

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'target.inbox'", $result->getErrorMessage());
    }

    public function testMissingTargetKeyReturnsFailure(): void
    {
        $payload = $this->buildValidPayload();
        unset($payload['target']);

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
    }

    // -------------------------------------------------------------------------
    // Validation order: origin is checked before type and target
    // -------------------------------------------------------------------------

    public function testOriginIsValidatedBeforeType(): void
    {
        $payload                    = $this->buildValidPayload();
        $payload['origin']['inbox'] = 'https://wrong-inbox.example.org/';
        $payload['type']            = ['Wrong'];

        $result = $this->validator->validate($payload);

        self::assertFalse($result->isValid());
        self::assertStringContainsString("'origin'", $result->getErrorMessage());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildValidPayload(): array
    {
        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://purl.org/coar/notify',
            ],
            'id'     => 'urn:uuid:0370c0fb-bb78-4a9b-87f5-bed307a509dd',
            'actor'  => [
                'id'   => '1099714',
                'name' => 'Josiah Carberry',
                'type' => 'Person',
            ],
            'object' => [
                'id'           => 'https://hal.science/hal-02558198v1',
                'ietf:cite-as' => 'https://hal.science/hal-02558198v1',
                'type'         => 'sorg:AboutPage',
                'ietf:item'    => [
                    'id'        => 'https://hal.science/hal-02558198v1/pdf',
                    'type'      => ['Article', 'sorg:ScholarlyArticle'],
                    'mediaType' => 'application/pdf',
                ],
            ],
            'origin' => [
                'id'    => 'https://hal.science/',
                'inbox' => self::EXPECTED_ORIGIN_INBOX,
                'type'  => 'Service',
            ],
            'target' => [
                'id'    => 'https://revue-test.' . self::EXPECTED_DOMAIN,
                'inbox' => 'https://www.' . self::EXPECTED_DOMAIN . '/',
                'type'  => 'Service',
            ],
            'type' => self::EXPECTED_TYPE,
        ];
    }
}
