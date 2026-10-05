<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Episciences_Review;
use Episciences_ReviewsManager;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Immutable, self-validating value object describing a `journal:create` request.
 *
 * The constructor enforces every invariant (code shape, field lengths, status/uid ranges) so
 * that any JournalSpec instance is guaranteed safe to hand to JournalCreator. fromInput() is
 * the intended entry point: it merges CLI options with an interactive wizard
 * (SymfonyStyle::ask()/choice()/confirm()), applying --no-interaction's "required option or
 * fail" rule for the fields that have no sane default (code, name, admin uid, template).
 *
 * Existence checks (does this code already exist, does the template journal exist, does the
 * admin uid exist) are deliberately NOT done here: they depend on --complete and are the
 * responsibility of JournalCreator's guards, run right before any write.
 */
final class JournalSpec
{
    /**
     * Codes that would collide with the module dispatch in defineJournalConstants() (PORTAL,
     * OAI) or with the shared fallback data directory (data/default).
     *
     * @var string[]
     */
    public const RESERVED_CODES = ['portal', 'oai', 'default'];

    private const CODE_PATTERN = '/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/';
    private const MAX_NAME_LENGTH = 2000;
    private const MAX_SUBTITLE_LENGTH = 255;

    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $subtitle,
        public readonly string $templateRvcode,
        public readonly int $adminUid,
        public readonly int $piwikId,
        public readonly string $status,
        public readonly bool $isNewFrontSwitched,
        public readonly bool $resetDoi,
    ) {
        self::assertValidCode($code);

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException(sprintf('Journal name must be 1-%d characters.', self::MAX_NAME_LENGTH));
        }

        if (mb_strlen($subtitle) > self::MAX_SUBTITLE_LENGTH) {
            throw new InvalidArgumentException(sprintf('Journal subtitle must be at most %d characters.', self::MAX_SUBTITLE_LENGTH));
        }

        if ($templateRvcode === '') {
            throw new InvalidArgumentException('A template journal code is required.');
        }

        if ($adminUid <= 0) {
            throw new InvalidArgumentException('Admin UID must be a positive integer.');
        }

        if ($piwikId < 0) {
            throw new InvalidArgumentException('Matomo (Piwik) site id must be zero or a positive integer.');
        }

        if (!in_array($status, [Episciences_Review::DISABLED, Episciences_Review::ENABLED], true)) {
            throw new InvalidArgumentException('Status must be 0 (disabled) or 1 (enabled).');
        }
    }

    public static function assertValidCode(string $code): void
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidArgumentException(
                "Invalid journal code '$code': expected 3-50 lowercase letters, digits or hyphens, " .
                'starting and ending with a letter or digit.'
            );
        }

        if (in_array($code, self::RESERVED_CODES, true)) {
            throw new InvalidArgumentException("'$code' is a reserved code and cannot be used for a journal.");
        }
    }

    public static function fromInput(InputInterface $input, SymfonyStyle $io): self
    {
        $interactive = $input->isInteractive();

        return new self(
            self::resolveCode($input, $io, $interactive),
            self::resolveName($input, $io, $interactive),
            self::resolveSubtitle($input, $io, $interactive),
            self::resolveTemplate($input, $io, $interactive),
            self::resolveAdminUid($input, $io, $interactive),
            self::resolvePiwikId($input, $io, $interactive),
            self::resolveStatus($input, $io, $interactive),
            self::resolveNewFront($input, $io, $interactive),
            (bool)$input->getOption('reset-doi'),
        );
    }

    private static function resolveCode(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $code = trim((string)($input->getOption('code') ?? ''));

        if ($code !== '') {
            return $code;
        }

        if (!$interactive) {
            throw new InvalidArgumentException('Missing required option: --code');
        }

        return (string)$io->ask('Journal code (rvcode)', null, static function (?string $answer): string {
            $answer = trim((string)$answer);
            self::assertValidCode($answer);
            return $answer;
        });
    }

    private static function resolveName(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $name = trim((string)($input->getOption('name') ?? ''));

        if ($name !== '') {
            return $name;
        }

        if (!$interactive) {
            throw new InvalidArgumentException('Missing required option: --name');
        }

        return (string)$io->ask('Journal name', null, static function (?string $answer): string {
            $answer = trim((string)$answer);
            if ($answer === '') {
                throw new InvalidArgumentException('The journal name cannot be empty.');
            }
            return $answer;
        });
    }

    private static function resolveSubtitle(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $subtitle = $input->getOption('subtitle');

        if ($subtitle !== null) {
            return (string)$subtitle;
        }

        if ($interactive) {
            return trim((string)$io->ask('Subtitle (optional)', ''));
        }

        return '';
    }

    private static function resolveTemplate(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $template = trim((string)($input->getOption('template-rvcode') ?? ''));

        if ($template !== '') {
            return $template;
        }

        if (!$interactive) {
            throw new InvalidArgumentException('Missing required option: --template-rvcode');
        }

        $journals = Episciences_ReviewsManager::getList();
        $rows = [];
        $codes = [];
        foreach ($journals as $review) {
            $rows[] = [$review->getCode(), $review->getName()];
            $codes[] = $review->getCode();
        }
        sort($codes);

        $io->table(['Code', 'Name'], $rows);

        return (string)$io->choice('Template journal to clone from (rvcode)', $codes);
    }

    private static function resolveAdminUid(InputInterface $input, SymfonyStyle $io, bool $interactive): int
    {
        $adminUid = $input->getOption('admin-uid');

        if ($adminUid !== null) {
            return (int)$adminUid;
        }

        if (!$interactive) {
            throw new InvalidArgumentException('Missing required option: --admin-uid');
        }

        return (int)$io->ask('Administrator UID (existing user)', null, static function (?string $answer): int {
            $answer = trim((string)$answer);
            if (!ctype_digit($answer) || (int)$answer <= 0) {
                throw new InvalidArgumentException('Admin UID must be a positive integer.');
            }
            return (int)$answer;
        });
    }

    private static function resolvePiwikId(InputInterface $input, SymfonyStyle $io, bool $interactive): int
    {
        $piwikId = $input->getOption('piwikid');

        if ($piwikId !== null) {
            return (int)$piwikId;
        }

        if (!$interactive) {
            return 0;
        }

        return (int)$io->ask('Matomo (Piwik) site id', '0', static function (?string $answer): int {
            $answer = trim((string)$answer);
            if (!ctype_digit($answer)) {
                throw new InvalidArgumentException('Matomo site id must be zero or a positive integer.');
            }
            return (int)$answer;
        });
    }

    private static function resolveStatus(InputInterface $input, SymfonyStyle $io, bool $interactive): string
    {
        $status = $input->getOption('status');

        if ($status !== null) {
            return (string)$status;
        }

        if ($interactive) {
            $enable = $io->confirm(
                'Enable the journal immediately? (a fresh journal is usually kept disabled until it is ready)',
                false
            );
            return $enable ? Episciences_Review::ENABLED : Episciences_Review::DISABLED;
        }

        return Episciences_Review::DISABLED;
    }

    private static function resolveNewFront(InputInterface $input, SymfonyStyle $io, bool $interactive): bool
    {
        $newFront = $input->getOption('new-front');

        if ($newFront !== null) {
            return strtolower((string)$newFront) === 'yes';
        }

        if ($interactive) {
            return $io->confirm('Serve this journal through the new (Next.js) front-end?', false);
        }

        return false;
    }
}
