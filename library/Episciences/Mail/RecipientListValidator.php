<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Episciences_Acl;
use Episciences_Tools;
use Zend_Validate_EmailAddress;

/**
 * Checks the recipients posted by the mail form (hidden_to, hidden_cc, hidden_bcc JSON fields).
 *
 * - the JSON structure is validated, as well as every address and the number of recipients;
 * - a recipient is only kept as a known user (uid) when the account belongs to the journal and has this address;
 *   an address typed by hand is resolved to the matching account of the journal;
 * - when the mail is about a paper, an author and a reviewer of the paper cannot both be in To or Cc
 *   (it would disclose the identity of one to the other).
 */
final class RecipientListValidator
{
    public const FIELDS = ['to', 'cc', 'bcc'];
    public const MAX_PER_FIELD = 100;
    public const MAX_TOTAL = 100;
    /** Above this number of recipients, the form warns that the mail goes to many people at once */
    public const WARNING_ABOVE = 50;

    public const ERROR_MISSING = 'Veuillez saisir au moins un destinataire';
    public const ERROR_INVALID_LIST = 'La liste des destinataires est invalide.';
    public const ERROR_INVALID_EMAIL = 'Adresse e-mail invalide : %s';
    public const ERROR_TOO_MANY = 'Trop de destinataires (%d au maximum).';
    public const ERROR_ANONYMITY = 'Un relecteur et un auteur de cet article ne peuvent pas être destinataires ensemble en À ou Cc : placez le ou les relecteurs en Cci.';

    private const AUTHOR_ROLES = [Episciences_Acl::ROLE_AUTHOR, Episciences_Acl::ROLE_CO_AUTHOR];

    public function __construct(
        private readonly RecipientDirectory $directory,
        private readonly int $rvid
    ) {
    }

    /**
     * @param array<string, mixed> $post
     * @param bool $strict the main recipient (To) is required
     * @param ?int $paperId paper the mail is about, if any
     * @return array{recipients: array{to: list<array{key: string, value: string, uid: ?int}>, cc: list<array{key: string, value: string, uid: ?int}>, bcc: list<array{key: string, value: string, uid: ?int}>}, errors: list<array{message: string, argument: string|int|null}>, isDetectedErrors: bool}
     */
    public function validate(array $post, bool $strict = true, ?int $paperId = null): array
    {
        $recipients = ['to' => [], 'cc' => [], 'bcc' => []];
        $errors = [];

        foreach (self::FIELDS as $field) {
            $decoded = self::decode($post['hidden_' . $field] ?? null);

            if ($decoded === null) {
                $errors[] = self::error(self::ERROR_INVALID_LIST);
                continue;
            }

            $recipients[$field] = $decoded;
        }

        $errors = array_merge($errors, $this->checkCounts($recipients), $this->checkAddresses($recipients));

        if ($errors === []) {
            $recipients = $this->resolveUsers($recipients);

            if ($paperId !== null && $this->isAnonymityBroken($recipients, $paperId)) {
                $errors[] = self::error(self::ERROR_ANONYMITY);
            }
        }

        $isMissing = $strict
            ? $recipients['to'] === []
            : ($recipients['to'] === [] && $recipients['cc'] === [] && $recipients['bcc'] === []);

        if ($isMissing) {
            array_unshift($errors, self::error(self::ERROR_MISSING));
        }

        return [
            'recipients' => $recipients,
            'errors' => $errors,
            'isDetectedErrors' => $errors !== [],
        ];
    }

    /**
     * Email address of a posted recipient ("Name <email>", "<email>" or "email", possibly HTML-encoded)
     */
    public static function emailOf(string $value): string
    {
        return mb_strtolower(trim((string)Episciences_Tools::postMailValidation($value)['email']));
    }

    /**
     * @return ?list<array{key: string, value: string, uid: ?int}> null when the JSON is not a list of recipients
     */
    private static function decode(mixed $json): ?array
    {
        if ($json === null || $json === '') {
            return [];
        }

        if (!is_string($json)) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            return null;
        }

        $recipients = [];

        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['value']) || !is_string($item['value']) || trim($item['value']) === '') {
                return null;
            }

            $uid = $item['uid'] ?? null;

            $recipients[] = [
                'key' => is_scalar($item['key'] ?? null) ? (string)$item['key'] : '',
                'value' => $item['value'],
                'uid' => is_numeric($uid) && (int)$uid > 0 ? (int)$uid : null,
            ];
        }

        return $recipients;
    }

    /**
     * @param array<string, list<array{key: string, value: string, uid: ?int}>> $recipients
     * @return list<array{message: string, argument: string|int|null}>
     */
    private function checkCounts(array $recipients): array
    {
        $total = 0;

        foreach ($recipients as $list) {
            if (count($list) > self::MAX_PER_FIELD) {
                return [self::error(self::ERROR_TOO_MANY, self::MAX_PER_FIELD)];
            }

            $total += count($list);
        }

        return $total > self::MAX_TOTAL ? [self::error(self::ERROR_TOO_MANY, self::MAX_TOTAL)] : [];
    }

    /**
     * @param array<string, list<array{key: string, value: string, uid: ?int}>> $recipients
     * @return list<array{message: string, argument: string|int|null}>
     */
    private function checkAddresses(array $recipients): array
    {
        $validator = new Zend_Validate_EmailAddress();
        $errors = [];

        foreach ($recipients as $list) {
            foreach ($list as $recipient) {
                $email = self::emailOf($recipient['value']);

                if (!$validator->isValid($email)) {
                    $errors[] = self::error(self::ERROR_INVALID_EMAIL, $email !== '' ? $email : $recipient['value']);
                }
            }
        }

        return $errors;
    }

    /**
     * @param array{to: list<array{key: string, value: string, uid: ?int}>, cc: list<array{key: string, value: string, uid: ?int}>, bcc: list<array{key: string, value: string, uid: ?int}>} $recipients
     * @return array{to: list<array{key: string, value: string, uid: ?int}>, cc: list<array{key: string, value: string, uid: ?int}>, bcc: list<array{key: string, value: string, uid: ?int}>}
     */
    private function resolveUsers(array $recipients): array
    {
        $claimedUids = [];
        $emails = [];

        foreach ($recipients as $list) {
            foreach ($list as $recipient) {
                $emails[] = self::emailOf($recipient['value']);

                if ($recipient['uid'] !== null) {
                    $claimedUids[] = $recipient['uid'];
                }
            }
        }

        $emailsByUid = $this->directory->reviewUserEmails($this->rvid, $claimedUids);
        $uidsByEmail = $this->directory->resolveEmails($this->rvid, $emails);

        foreach ($recipients as $field => $list) {
            foreach ($list as $index => $recipient) {
                $email = self::emailOf($recipient['value']);
                $isConfirmed = $recipient['uid'] !== null && ($emailsByUid[$recipient['uid']] ?? null) === $email;
                $recipients[$field][$index]['uid'] = $isConfirmed ? $recipient['uid'] : ($uidsByEmail[$email] ?? null);
            }
        }

        return $recipients;
    }

    /**
     * @param array{to: list<array{key: string, value: string, uid: ?int}>, cc: list<array{key: string, value: string, uid: ?int}>, bcc: list<array{key: string, value: string, uid: ?int}>} $recipients
     */
    private function isAnonymityBroken(array $recipients, int $paperId): bool
    {
        $paperRoles = $this->directory->paperRoles($this->rvid, $paperId, true);
        $hasAuthor = false;
        $hasReviewer = false;
        $visible = array_merge($recipients['to'], $recipients['cc']);

        foreach ($visible as $recipient) {
            $role = $recipient['uid'] !== null ? ($paperRoles[$recipient['uid']] ?? null) : null;
            $hasAuthor = $hasAuthor || in_array($role, self::AUTHOR_ROLES, true);
            $hasReviewer = $hasReviewer || $role === Episciences_Acl::ROLE_REVIEWER;
        }

        if (!$hasAuthor) {
            // No author: the reviewers may be visible to each other and to the editors
            return false;
        }

        if ($hasReviewer) {
            return true;
        }

        // Reviewers invited without an account are only known by their address
        $temporaryReviewers = $this->directory->temporaryReviewerEmails($this->rvid, $paperId);

        foreach ($visible as $recipient) {
            if (in_array(self::emailOf($recipient['value']), $temporaryReviewers, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{message: string, argument: string|int|null}
     */
    private static function error(string $message, string|int|null $argument = null): array
    {
        return ['message' => $message, 'argument' => $argument];
    }
}
