<?php

declare(strict_types=1);

namespace Episciences\User;

use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\TokenPurpose;
use Episciences_Mail_Send;
use Episciences_Mail_Tags;
use Episciences_Mail_TemplatesManager;
use Episciences_User;
use Zend_Exception;

/**
 * Sends an account activation link (a new token each time) to the e-mail address of the account.
 */
final class AccountActivationMailer
{
    /** @var callable(array<string, string>): string */
    private $urlBuilder;

    /**
     * @param callable(array<string, string>): string $urlBuilder application path of a route (the view url() helper)
     */
    public function __construct(private readonly AccountTokenRepository $tokens, callable $urlBuilder)
    {
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @return bool false when the e-mail could not be sent
     * @throws \Zend_Db_Exception when the token cannot be saved
     */
    public function send(Episciences_User $user): bool
    {
        $token = $this->tokens->issue((int)$user->getUid(), (string)$user->getEmail(), TokenPurpose::ACCOUNT_ACTIVATION);

        $tokenUrl = APPLICATION_URL . ($this->urlBuilder)([
                'controller' => 'user',
                'action' => 'activate',
                'token' => $token,
            ]);

        $tags = [
            // TAG_REVIEW_CODE deliberately omitted: it is not in OVERRIDABLE_TAGS,
            // so sendMailFromReview() already sets it from the review's mail display
            // code (Episciences_Mail's constructor runs before this array is merged).
            Episciences_Mail_Tags::TAG_REVIEW_NAME => RVNAME,
            Episciences_Mail_Tags::TAG_TOKEN_VALIDATION_LINK => $tokenUrl,
        ];

        try {
            return Episciences_Mail_Send::sendMailFromReview($user, Episciences_Mail_TemplatesManager::TYPE_USER_REGISTRATION, $tags);
        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }
}
