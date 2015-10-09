<?php

namespace EXSyst\Component\Worker\Tests\Exception;

use EXSyst\Component\Worker\Exception;

/**
 * @author Ener-Getick <egetick@gmail.com>
 */
class OutOfRangeExceptionTest extends AbstractExceptionTest
{
    public function setUp()
    {
        $this->exception = new Exception\OutOfRangeException();
    }

    public function testInheritance()
    {
        $this->assertInstanceOf('OutOfRangeException', $this->exception);
    }
}
