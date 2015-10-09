<?php

namespace EXSyst\Component\Worker\Tests\Exception;

use EXSyst\Component\Worker\Exception;

/**
 * @author Ener-Getick <egetick@gmail.com>
 */
class LogicExceptionTest extends AbstractExceptionTest
{
    public function setUp()
    {
        $this->exception = new Exception\LogicException();
    }

    public function testInheritance()
    {
        $this->assertInstanceOf('LogicException', $this->exception);
    }
}
