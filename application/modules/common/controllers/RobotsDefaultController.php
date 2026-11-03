<?php
/**
 * Class RobotsDefaultController
 * robots.txt
 */
class RobotsDefaultController extends Zend_Controller_Action
{
    private const DISALLOWED_PATHS = [
        '/search',
        '/index.php/search',
        '*/tei',
        '*/bibtex',
        '*/dc',
        '*/datacite',
        '*/openaire',
        '*/crossref',
        '*/doaj',
        '*/zbjats',
        '*/json',
        '*/jsonv2',
        '/browse/latest',
        '/login',
        '/logout',
        '/submit',
        '/user',
        '/error'
    ];

    public function indexAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $lines = ['# Episciences robots.txt', 'User-agent: *'];

        if ($this->isIndexingAllowed()) {
            foreach (self::DISALLOWED_PATHS as $path) {
                $lines[] = 'Disallow: ' . $path;
            }

            // Robots we do not care about:
            $lines[] = 'User-agent: barkrowler';
            $lines[] = 'Disallow: /';

            $lines[] = '# Sitemap';
            // Use the configured application base rather than the request host.
            $lines[] = 'Sitemap: ' . rtrim(APPLICATION_URL, '/') . '/sitemap';
        } else {
            $lines[] = 'Disallow: /';
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'text/plain; charset=utf-8', true)
            ->setBody(implode(PHP_EOL, $lines) . PHP_EOL);
    }

    /**
     * Only production journals still served by this application are open to robots.
     */
    protected function isIndexingAllowed(): bool
    {
        return $this->isProductionEnvironment()
            && !(defined('RVID') && Episciences_ReviewsManager::isNewFrontSwitched((int)RVID));
    }

    protected function isProductionEnvironment(): bool
    {
        return APPLICATION_ENV === ENV_PROD;
    }

    /*
    public function sitemapAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: text/xml; charset: utf-8');
        $sitemap = SPACE . 'public/sitemap/sitemap.xml';
        if (is_file($sitemap)) {
            include $sitemap;
        }
    }
    */
}
