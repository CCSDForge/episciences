<?php

declare(strict_types=1);

/**
 * Journal sites expose their feed under /rss (see RssController); /feed only exists on the portal.
 * Clients guessing /feed on a journal site (feed readers, crawlers) are permanently redirected
 * to the journal papers feed instead of hitting a 404 that floods the application log.
 */
class FeedController extends Zend_Controller_Action
{
    public const JOURNAL_FEED_URL = '/rss/papers';

    public function indexAction(): void
    {
        $this->redirectToJournalFeed();
    }

    public function rssAction(): void
    {
        $this->redirectToJournalFeed();
    }

    public function atomAction(): void
    {
        $this->redirectToJournalFeed();
    }

    private function redirectToJournalFeed(): void
    {
        /** @var Zend_Controller_Action_Helper_Redirector $redirector */
        $redirector = $this->_helper->getHelper('Redirector');
        // No exit: the ViewRenderer skips rendering on a redirect response.
        $redirector->setCode(301)->setExit(false)->gotoUrl(self::JOURNAL_FEED_URL);
    }
}
