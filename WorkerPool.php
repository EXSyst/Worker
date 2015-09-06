<?php

namespace EXSyst\Component\Worker;

use ArrayAccess;
use Countable;
use IteratorAggregate;

use EXSyst\Component\IO\Selectable;

use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;

use EXSyst\Component\Worker\Exception;

class WorkerPool implements ArrayAccess, Countable, IteratorAggregate
{
    private $workers;

    protected function __construct(WorkerBootstrapProfile $bootstrapProfile, $implementationExpression, $workerCount)
    {
        $this->workers = [ ];
        while ($workerCount-- > 0) {
            $this->workers[] = Worker::withExpression($bootstrapProfile, $implementationExpression);
        }
    }

    public static function withClass(WorkerBootstrapProfile $bootstrapProfile, $implementationClassName, $workerCount)
    {
        return new static($bootstrapProfile, $bootstrapProfile->generateExpression($implementationClassName), $workerCount);
    }

    public static function withExpression(WorkerBootstrapProfile $bootstrapProfile, $implementationExpression, $workerCount)
    {
        return new static($bootstrapProfile, $implementationExpression, $workerCount);
    }

    public function offsetExists($offset)
    {
        return isset($this->workers[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->workers[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new Exception\LogicException("A WorkerPool is a read-only container");
    }

    public function offsetUnset($offset)
    {
        throw new Exception\LogicException("A WorkerPool is a read-only container");
    }

    public function count()
    {
        return count($this->workers);
    }

    public function getIterator()
    {
        foreach ($this->workers as $i => $worker) {
            yield $i => $worker;
        }
    }

    public function receiveMessage(&$worker)
    {
        $workers = $this->workers;
        Selectable::selectRead($workers);
        if (!count($workers)) {
            throw new Exception\RuntimeException("selectRead returned an empty array (this should not happen)");
        }
        $worker = reset($workers);
        return $worker->receiveMessage();
    }

    public function registerRead(LoopInterface $loop, callable $listener, $once = false)
    {
        if ($once) {
            $listener2 = function ($worker, $loop) use ($listener) {
                $this->unregisterRead($loop);
                call_user_func($listener, $worker, $loop);
            }
            foreach ($this->workers as $worker) {
                Selectable::registerRead($loop, $worker, $listener2);
            }
        } else {
            foreach ($this->workers as $worker) {
                Selectable::registerRead($loop, $worker, $listener);
            }
        }
    }

    public function unregisterRead(LoopInterface $loop)
    {
        foreach ($this->workers as $worker) {
            Selectable::unregisterRead($loop, $worker);
        }
    }
}
