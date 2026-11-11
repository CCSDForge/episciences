<?php

declare(strict_types=1);

/**
 * Journal sites expose their feed under /rss (see RssController); /feed only exists on the portal.
 * Clients guessing /feed on a journal site (feed readers, crawlers) are permanently redirected
 * to the journal papers feed instead of hitting a 404 that floods the application log.
 */
class FeedController extends Zend_Controller_Action
{
    public const JOURNAL_FEED_PATH = 'rss/papers';

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
        // Deployments serving journals behind a "/<journal code>/" URL prefix define PREFIX_URL;
        // the redirect must keep it. The redirector turns an empty prefix into "/".
        $prefix = defined('PREFIX_URL') ? (string) constant('PREFIX_URL') : '/';

        // No exit: the ViewRenderer skips rendering on a redirect response.
        $redirector->setCode(301)->setExit(false)->gotoUrl($prefix . self::JOURNAL_FEED_PATH);
    }
}
