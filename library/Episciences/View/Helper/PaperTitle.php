<?php

declare(strict_types=1);

namespace Episciences\View\Helper;

use Ccsd_Tools;
use Episciences_Paper;
use Zend_View_Helper_Abstract;

/**
 * Title of a paper in a paper list: cut to a given length, then HTML-escaped.
 *
 * The title is cut before being escaped, so that no HTML entity is cut in the middle.
 */
class PaperTitle extends Zend_View_Helper_Abstract
{
    public const DEFAULT_LENGTH = 75;

    public function paperTitle(Episciences_Paper $paper, int $length = self::DEFAULT_LENGTH): string
    {
        return $this->view->escape(Ccsd_Tools::truncate($paper->getTitle(), $length));
    }
}
