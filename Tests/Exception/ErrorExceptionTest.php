<?php

/*
 * This file is part of the Worker package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Component\Worker\Tests\Exception;

use EXSyst\Component\Worker\Exception;

/**
 * @author Ener-Getick <egetick@gmail.com>
 */
class ErrorExceptionTest extends AbstractExceptionTest
{
    public function setUp()
    {
        $this->exception = new Exception\ErrorException();
    }

    public function testInheritance()
    {
        $this->assertInstanceOf('ErrorException', $this->exception);
    }
}
