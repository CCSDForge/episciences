<?php

class Ccsd_Tex_Language_Arabic extends Ccsd_Tex_Language
{

    /** @var $name */
    protected $locale = 'ar';
    /** @var string[]  */
    protected $header = [
        'ar' => '\newfontfamily\arabicfont[Script=Arabic]{Noto Naskh Arabic}',
    ];
    protected $texlangName = 'arabic';
    /** @var string  */
    protected $unicodeScript = 'Arabic';
    protected $replaceRegexp = '/(\p{Arabic}[\p{Arabic}\p{P}]+)/u';
    /** @var string  */
    protected $replaceBy = '\\\\begin{Arabic}$1\\\\end{Arabic}';
}