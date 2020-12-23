<?php

class Ccsd_Tex_Language_Chinese extends Ccsd_Tex_Language
{

    /** @var $name */
    protected $name = 'Chinese';
    protected $locale = 'zh';
    /** @var string[]  */
    protected $header = [
        'zh+ja+ko' => '\\usepackage[space]{xeCJK}',
        // Some character not found, let xeCJK operate alone!
        'zh-2' => '\\setCJKmainfont[BoldFont=WenQuanYi Zen Hei, ItalicFont=AR PL KaitiM GB]{AR PL SungtiL GB}',
        'zh-3' => '\\setCJKsansfont{Noto Sans CJK SC}',
        'zh-4' => '\\setCJKmonofont{cwTeXFangSong}'
    ];
    /** @var string  */
    protected $unicodeScript = 'Han';

}