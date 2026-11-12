<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\InvitationTemplates;
use Episciences\Reviewer\RequestType;
use Episciences_Mail_TemplatesManager as Templates;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Reviewer\InvitationTemplates
 */
final class InvitationTemplatesTest extends TestCase
{
    private const RVID = 3;

    /** @var array<string, array{subject: array<string, string>, body: array<string, string>}> */
    private array $store;

    /** @var list<string> */
    private array $requestedKeys = [];

    protected function setUp(): void
    {
        $this->store = [
            Templates::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER => $this->template('Report 1', 'Report body 1 %%INVITATION_ACCOUNT_HINT%%'),
            Templates::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER => $this->template('Report 2', 'Report body 2'),
            Templates::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER => $this->template('Report 3', 'Report body 3'),
            Templates::TYPE_PAPER_REVIEWER_INVITATION_QUICK_OPINION => $this->template('Quick', 'Quick [%%INVITATION_ACCOUNT_HINT%%]', 'Rapide', 'Rapide [%%INVITATION_ACCOUNT_HINT%%]'),
            Templates::TYPE_PAPER_REVIEWER_INVITATION_GENERIC => $this->template('Request: %%REVIEW_REQUEST_TYPE%%', 'Generic %%REVIEW_REQUEST_TYPE%% [%%INVITATION_ACCOUNT_HINT%%]'),
            Templates::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_KNOWN_REVIEWER => $this->fragment('hint reviewer', 'aide relecteur'),
            Templates::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_KNOWN_USER => $this->fragment(' hint user ', 'aide compte'),
            Templates::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_NEW_USER => $this->fragment('hint new', 'aide nouveau'),
            Templates::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_LIGHT_ANSWER => $this->fragment('hint light', 'aide sans compte'),
        ];
    }

    /**
     * @return array{subject: array<string, string>, body: array<string, string>}
     */
    private function template(string $subject, string $body, ?string $frSubject = null, ?string $frBody = null): array
    {
        $template = ['subject' => ['en' => $subject], 'body' => ['en' => $body]];

        if ($frSubject !== null && $frBody !== null) {
            $template['subject']['fr'] = $frSubject;
            $template['body']['fr'] = $frBody;
        }

        return $template;
    }

    /**
     * @return array{subject: array<string, string>, body: array<string, string>}
     */
    private function fragment(string $en, string $fr): array
    {
        return ['subject' => [], 'body' => ['en' => $en, 'fr' => $fr]];
    }

    private function templates(): InvitationTemplates
    {
        return new InvitationTemplates(function (string $key): ?array {
            $this->requestedKeys[] = $key;
            return $this->store[$key] ?? null;
        });
    }

    private function type(string $code, bool $lightAnswer = false, int $id = 1): RequestType
    {
        return new RequestType($id, self::RVID, $code, ['en' => 'Phase <1> & co', 'fr' => 'Phase 1'], $lightAnswer);
    }

    public function testReviewReportKeepsTheThreeTemplatesOfTheAccountStates(): void
    {
        $templates = $this->templates()->forType($this->type(RequestType::CODE_FULL_REPORT));

        self::assertSame(InvitationTemplates::ACCOUNT_STATES, array_keys($templates));
        self::assertSame('Report 2', $templates[InvitationTemplates::ACCOUNT_KNOWN_USER]['subject']['en']);
        self::assertSame('Report body 3', $templates[InvitationTemplates::ACCOUNT_NEW_USER]['body']['en']);
        // the hint tag may also be used in the historical templates
        self::assertSame('Report body 1 hint reviewer', $templates[InvitationTemplates::ACCOUNT_KNOWN_REVIEWER]['body']['en']);
    }

    public function testOtherTypeUsesItsTemplateWithTheHintOfEachAccountStateInEachLanguage(): void
    {
        $templates = $this->templates()->forType($this->type(RequestType::CODE_QUICK_OPINION));

        self::assertSame('Quick [hint reviewer]', $templates[InvitationTemplates::ACCOUNT_KNOWN_REVIEWER]['body']['en']);
        self::assertSame('Rapide [aide relecteur]', $templates[InvitationTemplates::ACCOUNT_KNOWN_REVIEWER]['body']['fr']);
        self::assertSame('Quick [hint user]', $templates[InvitationTemplates::ACCOUNT_KNOWN_USER]['body']['en']);
        self::assertSame('Rapide [aide nouveau]', $templates[InvitationTemplates::ACCOUNT_NEW_USER]['body']['fr']);
        self::assertSame('Rapide', $templates[InvitationTemplates::ACCOUNT_NEW_USER]['subject']['fr']);
    }

    public function testLightAnswerTypeUsesTheNoAccountHintWhateverTheAccount(): void
    {
        $templates = $this->templates()->forType($this->type(RequestType::CODE_QUICK_OPINION, true));

        foreach (InvitationTemplates::ACCOUNT_STATES as $state) {
            self::assertSame('Quick [hint light]', $templates[$state]['body']['en']);
        }
    }

    public function testTypeWithoutTemplateUsesTheGenericOneWithItsLabel(): void
    {
        $templates = $this->templates()->forType($this->type('phase_two'));
        $knownUser = $templates[InvitationTemplates::ACCOUNT_KNOWN_USER];

        self::assertContains(Templates::INVITATION_KEY_PREFIX . 'phase_two', $this->requestedKeys);
        // plain text subject, HTML body: the label is escaped in the body only
        self::assertSame('Request: Phase <1> & co', $knownUser['subject']['en']);
        self::assertSame('Generic Phase &lt;1&gt; &amp; co [hint user]', $knownUser['body']['en']);
    }

    public function testMissingHintLeavesNoTag(): void
    {
        unset($this->store[Templates::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_NEW_USER]);

        $templates = $this->templates()->forType($this->type(RequestType::CODE_QUICK_OPINION));

        self::assertSame('Quick []', $templates[InvitationTemplates::ACCOUNT_NEW_USER]['body']['en']);
    }

    public function testHintInAnotherLanguageIsUsedWhenTheTemplateLanguageHasNone(): void
    {
        $this->store[Templates::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_KNOWN_USER] = ['subject' => [], 'body' => ['fr' => 'aide compte']];

        $templates = $this->templates()->forType($this->type(RequestType::CODE_QUICK_OPINION));

        self::assertSame('Quick [aide compte]', $templates[InvitationTemplates::ACCOUNT_KNOWN_USER]['body']['en']);
    }

    public function testMissingTemplateLeavesTheAccountStateOut(): void
    {
        unset($this->store[Templates::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER]);

        $templates = $this->templates()->forType($this->type(RequestType::CODE_FULL_REPORT));

        self::assertSame([InvitationTemplates::ACCOUNT_KNOWN_REVIEWER, InvitationTemplates::ACCOUNT_KNOWN_USER], array_keys($templates));
    }

    public function testTemplatesAreLoadedOnceAndIndexedByTypeId(): void
    {
        $templates = $this->templates()->forTypes([
            $this->type(RequestType::CODE_QUICK_OPINION, false, 7),
            $this->type(RequestType::CODE_FULL_REPORT, false, 5),
        ]);

        self::assertSame([7, 5], array_keys($templates));
        self::assertSame(array_unique($this->requestedKeys), $this->requestedKeys);
    }
}
