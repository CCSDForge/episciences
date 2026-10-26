<?php

use Episciences\Trait\UrlBuilder;

/**
 * Replaces the Zend "Url" action helper on the *-epi-manager branches.
 *
 * Zend_Controller_Action_Helper_Url::simple(), used by $this->_helper->url($action, $controller),
 * builds the URL from the base URL without the router, so the journal prefix (PREFIX_URL) is lost.
 * Code merged from main / staging, where journals are served by sub-domain, keeps working unchanged.
 */
class Episciences_Controller_Action_Helper_Url extends Zend_Controller_Action_Helper_Url
{
    use UrlBuilder;

    private ?string $prefix;

    /**
     * @param string|null $prefixUrl '/<rvcode>/' for a journal, '/' for the portal (default: PREFIX_URL)
     */
    public function __construct(?string $prefixUrl = null)
    {
        $this->prefix = $prefixUrl;
    }

    public function simple($action, $controller = null, $module = null, ?array $params = null)
    {
        return self::prefixPath(parent::simple($action, $controller, $module, $params), $this->prefix);
    }
}
