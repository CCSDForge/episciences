<?php

declare(strict_types=1);

namespace unit\modules\support;

use Zend_Controller_Action_Helper_Json;

/**
 * JSON helper whose exit stops the action with ResponseSent instead of ending the PHP process.
 *
 * The JSON answer is set as the response body as usual.
 */
final class StoppingJsonHelper extends Zend_Controller_Action_Helper_Json
{
    /**
     * @param mixed $data
     * @param bool|array<string, mixed> $keepLayouts
     * @param bool $encodeData
     * @return string
     */
    public function sendJson($data, $keepLayouts = false, $encodeData = true)
    {
        $suppressExit = $this->suppressExit;
        $this->suppressExit = true;
        $json = parent::sendJson($data, $keepLayouts, $encodeData);
        $this->suppressExit = $suppressExit;

        if (!$suppressExit) {
            throw new ResponseSent('JSON sent');
        }

        return $json;
    }

    public function getName(): string
    {
        return 'Json';
    }
}
