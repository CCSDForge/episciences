<?php

declare(strict_types=1);

namespace Episciences\User\Import;

use Episciences\User\AccountActivationMailer;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\TokenPurpose;
use Episciences_Acl;
use Episciences_Mail_Send;
use Episciences_Mail_Tags;
use Episciences_Mail_Template;
use Episciences_Mail_TemplatesManager;
use Episciences_User;
use Zend_Exception;
use Zend_Registry;
use Zend_Translate;

/**
 * Mails of the user import, in the language of each account.
 */
class UserImportMailer
{
    /** A new link is not sent again when the import is run twice within this delay */
    public const int RESEND_DELAY = 86400;

    private const int SECONDS_PER_DAY = 86400;

    /** @var callable(array<string, string>): string */
    private $urlBuilder;

    /**
     * @param callable(array<string, string>): string $urlBuilder application path of a route
     */
    public function __construct(private readonly AccountTokenRepository $tokens, callable $urlBuilder)
    {
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Link to choose the password, which also activates an account not activated yet
     *
     * @param list<string> $roles roles held in the journal
     * @return MailOutcome
     * @throws \Zend_Db_Exception when the token cannot be saved
     */
    public function sendAccountSetup(Episciences_User $user, array $roles): MailOutcome
    {
        if ($this->tokens->issuedWithin((int)$user->getUid(), TokenPurpose::ACCOUNT_SETUP, self::RESEND_DELAY)) {
            return MailOutcome::AlreadySent;
        }

        $tags = $this->rolesTag($user, $roles) + [
            Episciences_Mail_Tags::TAG_LINK_VALIDITY => (string)self::linkValidityDays(),
        ];

        $isSent = (new AccountActivationMailer($this->tokens, $this->urlBuilder))
            ->sendWithPasswordChoice($user, Episciences_Mail_TemplatesManager::TYPE_USER_IMPORTED_ACCOUNT, $tags);

        return $isSent ? MailOutcome::Sent : MailOutcome::Failed;
    }

    /**
     * @param list<string> $roles roles held in the journal
     */
    public function sendAddedToJournal(Episciences_User $user, array $roles): MailOutcome
    {
        try {
            $isSent = Episciences_Mail_Send::sendMailFromReview(
                $user,
                Episciences_Mail_TemplatesManager::TYPE_USER_ADDED_TO_JOURNAL,
                $this->rolesTag($user, $roles) + [Episciences_Mail_Tags::TAG_REVIEW_NAME => RVNAME]
            );
        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return MailOutcome::Failed;
        }

        return $isSent ? MailOutcome::Sent : MailOutcome::Failed;
    }

    /**
     * Days of validity of the link to choose the password, rounded up
     */
    public static function linkValidityDays(): int
    {
        return max(1, (int)ceil(TokenPurpose::ACCOUNT_SETUP->ttlSeconds() / self::SECONDS_PER_DAY));
    }

    /**
     * Roles listed in a mail: member says nothing more when the person has another role
     *
     * @param list<string> $roles
     * @return list<string>
     */
    public static function displayedRoles(array $roles): array
    {
        $others = array_values(array_filter($roles, static fn(string $role): bool => $role !== Episciences_Acl::ROLE_MEMBER));

        return $others === [] ? $roles : $others;
    }

    /**
     * @param list<string> $roles
     * @return array<string, string>
     */
    private function rolesTag(Episciences_User $user, array $roles): array
    {
        // the language the template will be rendered in: the account language when the journal offers it,
        // otherwise the journal default (Episciences_Mail_Send::sendMailFromReview() does the same)
        $locale = (string)(new Episciences_Mail_Template())->setLocale($user->getLangueid(true))->getLocale();
        $translator = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;

        $labels = array_map(
            static fn(string $role): string => $translator instanceof Zend_Translate
                ? (string)$translator->translate($role, $locale)
                : $role,
            self::displayedRoles($roles)
        );

        return [Episciences_Mail_Tags::TAG_RECIPIENT_ROLES => implode(', ', $labels)];
    }
}
