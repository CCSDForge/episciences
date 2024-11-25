<?php
/**
 * Class RobotsDefaultController
 * robots.txt
 */
class RobotsDefaultController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: text/plain; charset: utf-8');
        echo '# Episciences robots.txt' . PHP_EOL;

        echo "User-Agent: *\n";
        if (APPLICATION_ENV == ENV_PROD) {
            $pathsToDisallow = [
                '/search',
                '*/tei',
                '*/bibtex',
                '*/dc',
                '*/datacite',
                '*/openaire',
                '*/crossref',
                '*/doaj',
                '*/zbjats',
                '*/json',
                '/browse/latest',
                '/login',
                '/submit',
                '/user',
                '/error'
            ];

            foreach ($pathsToDisallow as $path) {
                echo 'Disallow: ' . $path . PHP_EOL;
            }

            // Robots we do not care about:
            echo 'User-agent: barkrowler' . PHP_EOL;
            echo 'Disallow: /' . PHP_EOL;

            echo "# Sitemap\n";
            echo "Sitemap: " . $this->getRequest()->getScheme() . '://' . $_SERVER['HTTP_HOST'] . "/sitemap\n";
        } else {
            echo "Disallow: *\n";
        }

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

