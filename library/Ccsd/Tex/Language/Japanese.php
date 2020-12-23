<?php

class Ccsd_Tex_Language_Japanese extends Ccsd_Tex_Language
{

    protected $locale = 'ja';
    /** @var string[]  */
    protected $header = [
        'zh+ja+ko' => '\\usepackage[space]{xeCJK}',
        'ja+ko' => '\\newCJKfontfamily\\japanesefont{IPAMincho}',
    ];
    /** @var string  */
    protected $unicodeScript = '';
    protected $forceCantUseRegexp = true;
    // \p{P} is ponctuation characters
    protected $replaceRegexp = '/([\p{Hiragana}\p{Katakana}\p{Han}][\p{Hiragana}\p{Katakana}\p{Han}\p{P}]+)/u';
    /** @var string  */
    protected $replaceBy = '{\japanesefont $1}';
    /**
     * retourne un regexp pour determiner la langue
     * S'il n'est pas possible d'utiliser une regexp, alors retourne false
     * @return string|false
     */
    public function getLangRegexp() {
        return '/\\b[\\p{Hiragana}\\p{Katakana}\\p{Han}]+\\b/u';
    }

}