<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Closure;
use Episciences_Mail_Tags;
use Episciences_Mail_Template;
use Episciences_Mail_TemplatesManager;
use Episciences_Tools;

/**
 * Invitation e-mail of a review request type, for each account state of the invited person.
 *
 * The review report type keeps the three historical templates (one per account state). Every other type has a single
 * template (paper_reviewer_invitation_<code>, else the generic one) where %%INVITATION_ACCOUNT_HINT%% is replaced by
 * the text matching the account state. All of them are mail templates: each journal may customise them.
 */
final class InvitationTemplates
{
    /** Account states of the invited person, as sent by the invitation form */
    public const ACCOUNT_KNOWN_REVIEWER = 1;
    public const ACCOUNT_KNOWN_USER = 2;
    public const ACCOUNT_NEW_USER = 3;
    public const ACCOUNT_STATES = [self::ACCOUNT_KNOWN_REVIEWER, self::ACCOUNT_KNOWN_USER, self::ACCOUNT_NEW_USER];

    private const REVIEW_REPORT_KEYS = [
        self::ACCOUNT_KNOWN_REVIEWER => Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_REVIEWER,
        self::ACCOUNT_KNOWN_USER => Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_KNOWN_USER,
        self::ACCOUNT_NEW_USER => Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_NEW_USER,
    ];

    private const HINT_KEYS = [
        self::ACCOUNT_KNOWN_REVIEWER => Episciences_Mail_TemplatesManager::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_KNOWN_REVIEWER,
        self::ACCOUNT_KNOWN_USER => Episciences_Mail_TemplatesManager::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_KNOWN_USER,
        self::ACCOUNT_NEW_USER => Episciences_Mail_TemplatesManager::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_NEW_USER,
    ];

    /** @var Closure(string): ?array{subject: array<string, string>, body: array<string, string>} */
    private Closure $loader;

    /** @var array<string, ?array{subject: array<string, string>, body: array<string, string>}> */
    private array $loaded = [];

    /**
     * @param Closure(string): ?array{subject: array<string, string>, body: array<string, string>} $loader
     *        subject and body per language of the template with this key (custom version of the journal first), null if none
     */
    public function __construct(Closure $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Templates of the current journal
     */
    public static function ofCurrentJournal(): self
    {
        return new self(static function (string $key): ?array {
            $template = new Episciences_Mail_Template();

            if (!$template->findByKey($key)) {
                return null;
            }

            $template->loadTranslations();

            return [
                'subject' => $template->loadSubject(Episciences_Tools::getLanguages()),
                'body' => (array)($template->getBodyTranslations() ?? []),
            ];
        });
    }

    /**
     * @return array<int, array{subject: array<string, string>, body: array<string, string>}> keyed by account state
     *         (an account state is left out when no template is found)
     */
    public function forType(RequestType $type): array
    {
        $templates = [];

        foreach (self::ACCOUNT_STATES as $state) {
            $template = $this->load($this->templateKey($type, $state));

            if ($template === null) {
                continue;
            }

            $hint = $this->load(
                $type->isLightAnswer()
                    ? Episciences_Mail_TemplatesManager::TYPE_REVIEWER_INVITATION_ACCOUNT_HINT_LIGHT_ANSWER
                    : self::HINT_KEYS[$state]
            );

            $templates[$state] = $this->fill($template, $hint['body'] ?? [], $type);
        }

        return $templates;
    }

    /**
     * Templates of several types
     * @param list<RequestType> $types
     * @return array<int, array<int, array{subject: array<string, string>, body: array<string, string>}>> keyed by type id
     */
    public function forTypes(array $types): array
    {
        $result = [];

        foreach ($types as $type) {
            $result[(int)$type->getId()] = $this->forType($type);
        }

        return $result;
    }

    private function templateKey(RequestType $type, int $state): string
    {
        if ($type->isFullReport()) {
            return self::REVIEW_REPORT_KEYS[$state];
        }

        $key = Episciences_Mail_TemplatesManager::INVITATION_KEY_PREFIX . $type->invitationKeySuffix();

        return $this->load($key) !== null ? $key : Episciences_Mail_TemplatesManager::TYPE_PAPER_REVIEWER_INVITATION_GENERIC;
    }

    /**
     * @param array{subject: array<string, string>, body: array<string, string>} $template
     * @param array<string, string> $hints hint text per language
     * @return array{subject: array<string, string>, body: array<string, string>}
     */
    private function fill(array $template, array $hints, RequestType $type): array
    {
        $subject = [];
        $body = [];

        foreach ($template['subject'] as $lang => $text) {
            // the subject is plain text
            $subject[$lang] = str_replace(Episciences_Mail_Tags::TAG_REVIEW_REQUEST_TYPE, $type->getLabel($lang), (string)$text);
        }

        foreach ($template['body'] as $lang => $text) {
            $hint = trim((string)($hints[$lang] ?? reset($hints) ?: ''));
            $body[$lang] = str_replace(
                [Episciences_Mail_Tags::TAG_INVITATION_ACCOUNT_HINT, Episciences_Mail_Tags::TAG_REVIEW_REQUEST_TYPE],
                // the body is HTML: the label is set by the journal
                [$hint, htmlspecialchars($type->getLabel($lang), ENT_QUOTES | ENT_HTML5, 'UTF-8')],
                (string)$text
            );
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * @return ?array{subject: array<string, string>, body: array<string, string>}
     */
    private function load(string $key): ?array
    {
        if (!array_key_exists($key, $this->loaded)) {
            $this->loaded[$key] = ($this->loader)($key);
        }

        return $this->loaded[$key];
    }
}
