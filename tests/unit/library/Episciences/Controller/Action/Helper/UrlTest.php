<?php

declare(strict_types=1);

namespace unit\library\Episciences\Controller\Action\Helper;

use Episciences_Controller_Action_Helper_Url;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Front;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;

/**
 * $this->_helper->url($action, $controller) must keep the journal prefix of the *-epi-manager branches
 */
final class UrlTest extends TestCase
{
    private Episciences_Controller_Action_Helper_Url $helper;

    private ?Zend_Controller_Request_Abstract $previousRequest = null;

    protected function setUp(): void
    {
        $request = new Zend_Controller_Request_Http();
        $request->setControllerName('review')->setActionName('settings');

        $front = Zend_Controller_Front::getInstance();
        $this->previousRequest = $front->getRequest();
        $front->setRequest($request);

        $this->helper = new Episciences_Controller_Action_Helper_Url('/epijinfo/');
    }

    protected function tearDown(): void
    {
        // the front controller is shared with the other tests: restore its request only
        $property = new ReflectionProperty(Zend_Controller_Front::class, '_request');
        $property->setValue(Zend_Controller_Front::getInstance(), $this->previousRequest);
    }

    public function testControllerAndActionArePrefixed(): void
    {
        self::assertSame('/epijinfo/review/licenses', $this->helper->direct('licenses', 'review'));
    }

    public function testCurrentControllerIsUsedByDefault(): void
    {
        self::assertSame('/epijinfo/review/licenses', $this->helper->simple('licenses'));
    }

    public function testParametersAreAppendedAfterTheAction(): void
    {
        self::assertSame('/epijinfo/paper/view/id/12', $this->helper->simple('view', 'paper', null, ['id' => 12]));
    }

    public function testReplacesTheZendHelperUnderTheSameName(): void
    {
        Zend_Controller_Action_HelperBroker::addHelper($this->helper);

        try {
            self::assertSame($this->helper, Zend_Controller_Action_HelperBroker::getStaticHelper('url'));
        } finally {
            Zend_Controller_Action_HelperBroker::removeHelper('url');
        }
    }

    public function testPortalUrlIsUnchanged(): void
    {
        self::assertSame('/review/licenses', (new Episciences_Controller_Action_Helper_Url('/'))->simple('licenses', 'review'));
    }
}
