<?php
/**
 * Deteclanguage
 */

class Ccsd_Detectlanguage
{

	/**
	 * Initialize
	 *
	 */
	public function __construct() {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

	}
	
	/**
	* Detect text language.
	 *
	 * @param string @text The text for language detection
	 * @return array detected languages information
	 */
	public function detect($text) {
        if ( $text && file_put_contents($tmpfname = tempnam("/tmp", "ld"), strtolower($text)) ) {
            @exec('java -jar '.__DIR__.'/Detectlanguage/langdetect.jar --detectlang -d '.__DIR__.'/Detectlanguage/profiles/ '.escapeshellarg($tmpfname), $output);
            unlink($tmpfname);
            if ( count($output) && preg_match('/:\[([a-z]+):([0-9\.]+)(\]|,)/', $output[0], $match) ) {
                // on retourne tjrs qu'une seule langue
                return ['langid'=>$match[1], 'proba'=>$match[2]];
            } else {
                return null;
            }
        }
        return null;
  	}

}