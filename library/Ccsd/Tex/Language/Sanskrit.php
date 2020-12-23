<?php

class Ccsd_Tex_Language_Sanskrit extends Ccsd_Tex_Language
{

    /** @var $name */
    protected $locale = 'sa';
    /** @var string[]  */
    protected $header = [
        'sa+hi' => '\\newfontfamily\\devanagarifont[Script=Devanagari]{Noto Sans Devanagari}'

    ];
    /** @var string  */
    protected $texlangName = 'sanskrit';
    protected $unicodeScript = 'Devanagari';
    // \p{P} is ponctuation characters
    protected $replaceRegexp = '/(\p{Devanagari}(\p{Devanagari}|[^\P{P}\\\{\}]|\s)+)/u';
    /** @var string  */
    protected $replaceBy = '\\\\begin{sanskrit}$1\\\\end{sanskrit}';
}