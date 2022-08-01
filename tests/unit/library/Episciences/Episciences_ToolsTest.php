<?php

namespace unit\library\Episciences;

use Episciences_Tools;
use PHPUnit\Framework\TestCase;


class Episciences_ToolsTest extends TestCase
{
    public static function testIsInUppercase(){

        self::assertEquals(true, Episciences_Tools::isInUppercase('SCREEN_NAME'));
        self::assertEquals(false, Episciences_Tools::isInUppercase('SCREEN_name'));

    }

}

