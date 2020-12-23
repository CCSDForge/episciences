<?php

class Ccsd_Tex_Language_Greek extends Ccsd_Tex_Language
{

    /** @var $name */
    protected $locale = 'el';
    /** @var string[]  */
    protected $header = [
        // 'el-1' => '\newfontfamily{\greekfont}{CMU Serif}',
        //'el-2' => '\newfontfamily{\greekfontsf}{CMU Sans Serif}'
    ];
    protected $texlangName = 'greek';
    /** @var string  */
    protected $unicodeScript = 'Greek';
    protected $replaceRegexp = '';
    /** @var string  */
    protected $replaceBy = '';
}