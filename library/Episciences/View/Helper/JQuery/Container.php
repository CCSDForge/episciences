<?php

/**
 * @see ZendX_JQuery
 */
require_once 'ZendX/JQuery.php';

/**
 * jQuery View Helper. Transports all jQuery stack and render information across all views.
 * CCSD : modifié pour ajouter un numéro de version à la fin de l'url pour éviter les problèmes de cache
 */
class Episciences_View_Helper_JQuery_Container extends ZendX_JQuery_View_Helper_JQuery_Container
{


    /**
     * Render jQuery stylesheets
     *
     * @return string
     */
    protected function _renderStylesheets(): string
    {
        if (0 == ($this->getRenderMode() & ZendX_JQuery::RENDER_STYLESHEETS)) {
            return '';
        }

        foreach ($this->getStylesheets() as $stylesheet) {
            $stylesheets[] = $stylesheet;
        }

        if (empty($stylesheets)) {
            return '';
        }

        $stylesheets = array_reverse($stylesheets);
        $style = '';

        if ($this->view instanceof Zend_View_Abstract) {
            $closingBracket = ($this->view->doctype()->isXhtml()) ? ' />' : '>';
        } else {
            $closingBracket = ' />';
        }

        foreach ($stylesheets as $stylesheet) {
            $stylesheet = self::addApplicationVersionToUrl($stylesheet);
            $style .= '<link rel="stylesheet" href="' . $stylesheet . '" ' .
                'type="text/css" media="screen"' . $closingBracket . PHP_EOL;
        }
        return $style;
    }


    /**
     * Add Application version at the end of a URL
     * @param string $url
     * @return string $url with app version
     */
    protected static function addApplicationVersionToUrl(string $url): string
    {
        $separator = '?';

        // check if url has parameters
        $urlParsed = parse_url($url, PHP_URL_QUERY);
        if ((isset($urlParsed)) && ($urlParsed != null)) {
            $separator = '&';
        }

        return $url . $separator . APPLICATION_VERSION;

    }


    /**
     * Renders all javascript file related stuff of the jQuery enviroment.
     *
     * jQuery / jQuery UI are self-hosted via webpack (see assets/jquery.js, assets/jquery-ui.js) rather
     * than a single CDN/local URL, so unlike the base ZendX behaviour this can emit several <script>
     * tags (webpack's runtime.js chunk plus the entry itself) for what used to be one "library" tag.
     * This still renders as part of RENDER_LIBRARY so it's guaranteed to appear before any page's own
     * addJavascriptFile() sources — those are queued from view scripts, which run before the layout,
     * so without this ordering guarantee page scripts could load before jQuery does.
     *
     * @return string
     */
    protected function _renderScriptTags(): string
    {

        $scriptTags = '';
        $seenUrls = [];
        $emitScript = function (string $url) use (&$scriptTags, &$seenUrls) {
            if (isset($seenUrls[$url])) {
                return;
            }
            $seenUrls[$url] = true;
            $scriptTags .= '<script src="' . self::addApplicationVersionToUrl($url) . '"></script>' . PHP_EOL;
        };

        if (($this->getRenderMode() & ZendX_JQuery::RENDER_LIBRARY) > 0) {
            $webpackAssets = new Episciences_View_Helper_WebpackAssets();

            foreach ($webpackAssets->getEntryUrls('jquery') as $url) {
                $emitScript($url);
            }

            if ($this->uiIsEnabled()) {
                foreach ($webpackAssets->getEntryUrls('jquery-ui') as $url) {
                    $emitScript($url);
                }
            }

            if (ZendX_JQuery_View_Helper_JQuery::getNoConflictMode() == true) {
                $scriptTags .= '<script>var $j = jQuery.noConflict();</script>' . PHP_EOL;
            }
        }

        if (($this->getRenderMode() & ZendX_JQuery::RENDER_SOURCES) > 0) {
            // De-duplicated against the library block above: webpack entries queued here (e.g. 'app')
            // can share files with jquery/jquery-ui (namely runtime.js), which would otherwise load —
            // and re-execute — twice.
            foreach ($this->getJavascriptFiles() as $javascriptFile) {
                $emitScript($javascriptFile);
            }
        }

        return $scriptTags;
    }


}
