<?php


class Episciences_View_Helper_PurifyHtml extends Zend_View_Helper_Abstract
{

    public static $CORE_ENCODING = 'UTF-8';
    public static $HTML_ALLOWED_ELEMENTS = [
        'a',
        'b',
        'blockquote',
        'br',
        'code',
        'em',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'img',
        'li',
        'ol',
        'p',
        'pre',
        's',
        'span',
        'strong',
        'sub',
        'sup',
        'table',
        'tbody',
        'td',
        'th',
        'thead',
        'tr',
        'u',
        'ul',
    ];
    public static $HTML_ALLOWED_ATTRIBUTES = [
        'a.href',
        'a.target',
        'code.class',
        'img.class',
        'img.src',
        'ol.start',
        'p.class',
        'p.style',
        'span.style',
        'table.class',
        'td.align',
        'th.align',
    ];
    public static $CSS_ALLOWED_PROPERTIES = [
        'color',
        'background-color',
        'font-size',
        'font-weight',
        'padding-left',
        'text-align',
        'text-decoration',
    ];
    public static $ATTR_ALLOWED_CLASSES = [
        'alert',
        'alert-danger',
        'alert-info',
        'alert-success',
        'alert-warning',
        'blockquote',
        'blockquote-footer',
        'img-responsive',
        'table',
        'table-bordered',
        'table-condensed',
        'table-hover',
        'table-responsive',
        'table-striped',
    ];
    public static $ATTR_ALLOWED_FRAME_TARGETS = ['_blank'];
    public static $URI_ALLOWED_SCHEMES = [
        'http' => true,
        'https' => true,
    ];


    /**
     * PurifyHtml constructor.
     * @param string $html
     * @return string
     */
    public static function purifyHtml(string $html = ''): string
    {

        if (empty($html)) {
            return $html;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath',REVIEW_TMP_PATH); // default: vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache
        $config->set('Core.Encoding', self:: $CORE_ENCODING);
        $config->set('HTML.AllowedElements', self::$HTML_ALLOWED_ELEMENTS);
        $config->set('CSS.AllowedProperties', self::$CSS_ALLOWED_PROPERTIES);
        $config->set('Attr.AllowedClasses', self::$ATTR_ALLOWED_CLASSES);
        $config->set('Attr.AllowedFrameTargets', self::$ATTR_ALLOWED_FRAME_TARGETS);
        $config->set('HTML.AllowedAttributes', self::$HTML_ALLOWED_ATTRIBUTES);
        $config->set('URI.AllowedSchemes', self::$URI_ALLOWED_SCHEMES);

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html, $config);

    }

}