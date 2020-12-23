<?php

class Ccsd_Tex_Language_Corean extends Ccsd_Tex_Language
{

    /** @var $name */
    protected $locale = 'ko';
    /** @var string[]  */
    protected $header = [
        'zh+ja+ko' => '\\usepackage[space]{xeCJK}',
        'ja+ko' => '\\newCJKfontfamily\\japanesefont{IPAMincho}',
        'ko' => '\\newfontfamily\\hangulfont[Script=Hangul]{UnBatang}',
    ];

    protected $texlangName = 'korean';
    /** @var string  */
    protected $unicodeScript = 'Hangul';
    // \p{P} is ponctuation characters
    protected $replaceRegexp = '/(\p{Hangul}[\p{Hangul}\p{P}\s]+)/u';
    /** @var string  */
    protected $replaceBy = '\\begin{korean}$1\\end{korean}';
}