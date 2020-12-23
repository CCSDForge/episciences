<?php

class Ccsd_Tex_Language_Russian extends Ccsd_Tex_Language
{

    /** @var $name */
    protected $locale = 'ru';
    /** @var string[]  */
    protected $header = [
        'ru' => '\usepackage{libertine}',
    ];
    protected $texlangName = 'russian';
    /** @var string  */
    protected $unicodeScript = 'Cyrillic';
    protected $replaceRegexp = '/(\p{Cyrillic}[\p{Cyrillic}\p{P} a-zA-Z0-9]+)/u';
    /** @var string  */
    protected $replaceBy = '\\\\begin{russian}$1\\\\end{russian}';
}