<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

/**
 * Rules of "send the invitation again" (#319, lot F): the editorial team sends the invitation e-mail of a request
 * again, with the same signed link, to a person who has not answered yet (lost e-mail, spam folder). The message is
 * rebuilt from the invitation template of the request type.
 */
final class InvitationResend
{
    public const NOT_ALLOWED = "Vous n'avez pas les droits suffisants pour effectuer cette action";
    public const NOT_PENDING = "Cette invitation ne peut plus être renvoyée : elle a reçu une réponse, a été annulée ou a expiré.";
    public const NOT_REVIEWABLE = 'Cet article ne peut plus être relu';

    /**
     * Why the invitation cannot be sent again, null when it can
     * @param bool $allowed the user manages the reviewing of the paper
     * @param bool $waiting the invitation is waiting for an answer: not answered, cancelled nor expired
     * @param bool $canBeReviewed the paper can still be reviewed
     */
    public static function refusal(bool $allowed, bool $waiting, bool $canBeReviewed): ?string
    {
        if (!$allowed) {
            return self::NOT_ALLOWED;
        }

        if (!$waiting) {
            return self::NOT_PENDING;
        }

        return $canBeReviewed ? null : self::NOT_REVIEWABLE;
    }

    /**
     * Account state of the invited person, which selects the invitation template (see InvitationTemplates)
     */
    public static function accountState(bool $tmpUser, bool $isReviewer): int
    {
        if ($tmpUser) {
            return InvitationTemplates::ACCOUNT_NEW_USER;
        }

        return $isReviewer ? InvitationTemplates::ACCOUNT_KNOWN_REVIEWER : InvitationTemplates::ACCOUNT_KNOWN_USER;
    }

    /**
     * Text of a template in the language of the invited person, else in the default language of the journal, else in
     * the first language available
     * @param array<string, string> $byLanguage
     */
    public static function inLanguage(array $byLanguage, string $locale, string $defaultLocale): string
    {
        return (string)($byLanguage[$locale] ?? $byLanguage[$defaultLocale] ?? reset($byLanguage) ?: '');
    }
}
