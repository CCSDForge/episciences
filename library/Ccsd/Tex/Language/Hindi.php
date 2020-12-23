<?php

class Ccsd_Tex_Language_Hindi extends Ccsd_Tex_Language
{

    /** @var $name */
    protected $locale = 'hi';
    /** @var string[]  */
    protected $header = [
        'sa+hi' => '\\newfontfamily\\devanagarifont[Script=Devanagari]{Noto Sans Devanagari}'
    ];
    /** @var string  */
    protected $texlangName = 'hindi';
    protected $unicodeScript = 'Devanagari';
    protected $replaceRegexp = '/(\p{Devanagari}[\p{Devanagari}\p{P}\s]+)/u';
    /** @var string  */
    protected $replaceBy = '\\begin{Hindi}$1\\end{Hindi}';
}