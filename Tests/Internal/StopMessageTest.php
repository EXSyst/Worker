<?php

namespace EXSyst\Component\Worker\Tests\Internal;

use EXSyst\Component\Worker\Internal\StopMessage;

/**
 * @author Ener-Getick <egetick@gmail.com>
 */
class StopMessageTest extends \PHPUnit_Framework_TestCase
{
    public function testCookieGetter() {
        $message = new StopMessage('fooCookie');
        $this->assertEquals('fooCookie', $message->getCookie());
    }
}
