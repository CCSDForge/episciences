<?php

class Episciences_HTMLPurifier extends HTMLPurifier
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


    public function __construct(array $options = [])
    {

        $commonOptions = [
            'Core.Encoding' => self:: $CORE_ENCODING,
            'Cache.SerializerPath' =>'/tmp/HTMLPurifier/DefinitionCache'
        ];

        $defaultOptions = [
            'HTML.AllowedElements' => self::$HTML_ALLOWED_ELEMENTS,
            'CSS.AllowedProperties' => self::$CSS_ALLOWED_PROPERTIES,
            'Attr.AllowedClasses' => self::$ATTR_ALLOWED_CLASSES,
            'Attr.AllowedFrameTargets' => self::$ATTR_ALLOWED_FRAME_TARGETS,
            'HTML.AllowedAttributes' => self::$HTML_ALLOWED_ATTRIBUTES,
            'URI.AllowedSchemes' => self::$URI_ALLOWED_SCHEMES
        ];

        if (empty($options)) {
            $options = array_merge($commonOptions, $defaultOptions);
        } else {
            $options = array_merge($commonOptions, $options);
        }

        // HTMLPurifier utilise un cache interne pour les structures qu'il analyse et vide le cache dans des fichiers sur le disque.
        // La chose étrange est le chemin par défaut (vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache) si vous n'en configurez pas un.
        $cacheDirectory = $options['Cache.SerializerPath']; // Pour stocker les définitions sérialisées.

        if (!file_exists($cacheDirectory) && !mkdir($cacheDirectory, 0700, true) && !is_dir($cacheDirectory)) {
            trigger_error(sprintf('HTML purifier directory "%s" can not be created', $cacheDirectory), E_USER_ERROR);
        }

        $config = HTMLPurifier_Config::createDefault();
        $availableOptionKeys = array_keys($config->def->info);

        foreach ($options as $key => $value) {

            if (in_array($key, $availableOptionKeys, true)) {
                $config->set($key, $value);
            }

        }

        parent::__construct($config);
    }

    /**
     * Filters an HTML snippet/document to be XSS-free and standards-compliant.
     * @param string $html
     * @return string
     */
    public function purifyHtml(string $html = ''): string
    {

        if (empty($html)) {
            return $html;
        }

        return $this->purify($html, $this->config);

    }
}