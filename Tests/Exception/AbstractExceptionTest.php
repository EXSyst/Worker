<?php

namespace EXSyst\Component\Worker\Tests\Exception;

/**
 * @author Ener-Getick <egetick@gmail.com>
 */
abstract class AbstractExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \EXSyst\Component\IO\Exception\ExceptionInterface
     */
    protected $exception;

    public function setUp()
    {
        throw new \LogicException('You must define setUp().');
    }

    public function testInterface()
    {
        $this->assertInstanceOf('EXSyst\Component\Worker\Exception\ExceptionInterface', $this->exception);
    }
}
