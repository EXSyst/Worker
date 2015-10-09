<?php

namespace EXSyst\Component\Worker\Tests\Exception;

use EXSyst\Component\Worker\Exception;

/**
 * @author Ener-Getick <egetick@gmail.com>
 */
class BindOrListenExceptionTest extends AbstractExceptionTest
{
    public function setUp()
    {
        $this->exception = new Exception\BindOrListenException();
    }

    public function testInheritance()
    {
        $this->assertInstanceOf(Exception\RuntimeException::class, $this->exception);
    }
}
