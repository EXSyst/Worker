<?php

namespace EXSyst\Component\Worker\Tests\Exception;

use EXSyst\Component\Worker\Exception;

/**
 * @author Ener-Getick <egetick@gmail.com>
 */
class RuntimeException extends AbstractExceptionTest
{
    public function setUp()
    {
        $this->exception = new Exception\RuntimeException();
    }

    public function testInheritance()
    {
        $this->assertInstanceOf('RuntimeException', $this->exception);
    }
}
