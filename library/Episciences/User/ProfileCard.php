<?php

declare(strict_types=1);

namespace Episciences\User;

use Ccsd_User_Models_User;
use Episciences_View_Helper_GetAvatar;

/**
 * View model of the shared user profile card (partial `user/user_profile.phtml`).
 *
 * Normalizes the two identity shapes the card is rendered from — a registered account
 * (Episciences_User::toArray()) and an account-less reviewer invited by e-mail
 * (Episciences_User_Tmp::toArray()) — and decides who may see the private details.
 */
final class ProfileCard
{
    /**
     * @param array<int, array<string, mixed>> $affiliations
     * @param array<int, string> $webSites
     * @param array<int, string> $socialMedias
     * @param array<int, string> $roles role ids in the current journal
     */
    private function __construct(
        public readonly ?int $uid,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $orcid,
        public readonly array $affiliations,
        public readonly array $webSites,
        public readonly array $socialMedias,
        public readonly ?string $biography,
        public readonly ?string $languageCode,
        public readonly ?string $registrationDate,
        public readonly array $roles
    ) {
    }

    /**
     * @param array<string, mixed> $identity Episciences_User::toArray() or Episciences_User_Tmp::toArray()
     */
    public static function fromArray(array $identity, int $rvId): self
    {
        $uid = isset($identity['uid']) ? (int)$identity['uid'] : 0;
        $roles = $identity['ROLES'][$rvId] ?? [];

        return new self(
            $uid > 0 ? $uid : null,
            self::resolveName($identity),
            self::nonEmptyString($identity['email'] ?? null),
            self::nonEmptyString($identity['orcid'] ?? null),
            self::listOfArrays($identity['affiliations'] ?? null),
            self::listOfStrings($identity['web_sites'] ?? null),
            self::listOfStrings($identity['social_medias'] ?? null),
            self::nonEmptyString($identity['biography'] ?? null),
            self::nonEmptyString($identity['langueid'] ?? $identity['lang'] ?? null),
            self::formatDate($identity['time_registered'] ?? null),
            is_array($roles) ? array_values(array_map('strval', $roles)) : []
        );
    }

    public function isRegistered(): bool
    {
        return $this->uid !== null;
    }

    /**
     * E-mail, language, registration date and biography: for the journal's managers and the
     * person themself. The public profile (/user/view) is reachable anonymously for any account.
     */
    public function canShowPrivateDetailsTo(int $viewerUid, bool $viewerIsManager): bool
    {
        return $viewerIsManager || ($this->uid !== null && $this->uid === $viewerUid);
    }

    /**
     * Avatar source: the uploaded photo, else generated initials. An account-less reviewer
     * has no uid, and `/user/photo` without a uid serves the *viewer's* own photo, so their
     * initials are inlined as a data URI instead.
     */
    public function avatarSrc(int $viewerUid, string $photoVersion = ''): string
    {
        if ($this->uid === null) {
            return 'data:image/svg+xml;base64,' . base64_encode(Episciences_View_Helper_GetAvatar::asSvg($this->name));
        }

        if ($this->hasPhoto()) {
            // the version only busts the viewer's own cached photo, right after they change it
            $version = $this->uid === $viewerUid && $photoVersion !== '' ? '?v=' . rawurlencode($photoVersion) : '';
            return sprintf('/user/photo/uid/%d/size/%s%s', $this->uid, Ccsd_User_Models_User::IMG_NAME_LARGE, $version);
        }

        return sprintf(
            '/user/photo/name/%s/uid/%d/size/%s',
            rawurlencode($this->name),
            $this->uid,
            Ccsd_User_Models_User::IMG_NAME_INITIALS
        );
    }

    private function hasPhoto(): bool
    {
        $user = new Ccsd_User_Models_User(['uid' => $this->uid]);
        return $user->getPhotoPathName(Ccsd_User_Models_User::IMG_NAME_LARGE) !== false;
    }

    /**
     * @param array<string, mixed> $identity
     */
    private static function resolveName(array $identity): string
    {
        foreach (['SCREEN_NAME', 'screen_name', 'fullname'] as $key) {
            $name = self::nonEmptyString($identity[$key] ?? null);
            if ($name !== null) {
                return $name;
            }
        }

        return trim(sprintf('%s %s', $identity['firstname'] ?? '', $identity['lastname'] ?? ''));
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    private static function listOfStrings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(
            array_map(static fn($item): string => is_scalar($item) ? trim((string)$item) : '', $value),
            static fn(string $item): bool => $item !== ''
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function listOfArrays(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private static function formatDate(mixed $value): ?string
    {
        $value = self::nonEmptyString($value);
        if ($value === null) {
            return null;
        }
        $timestamp = ctype_digit($value) ? (int)$value : strtotime($value);
        return $timestamp !== false && $timestamp > 0 ? date('Y-m-d', $timestamp) : null;
    }
}
