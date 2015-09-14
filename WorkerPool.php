<?php

namespace EXSyst\Component\Worker;

use ArrayAccess;
use Countable;
use IteratorAggregate;

use Symfony\Component\Process\ExecutableFinder;

use EXSyst\Component\IO\Selectable;

use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;

use EXSyst\Component\Worker\Exception;

class WorkerPool implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @var array
     */
    private $workers;

    /**
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param string $implementationExpression
     * @param int|null $workerCount
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    protected function __construct(WorkerBootstrapProfile $bootstrapProfile, $implementationExpression, $workerCount = null)
    {
        $workerCount = ($workerCount === null) ? self::getProcessorCount() : intval($workerCount);
        if ($workerCount <= 0) {
            throw new Exception\InvalidArgumentException("The worker count must be a strictly positive number !");
        }
        $this->workers = [];
        while ($workerCount-- > 0) {
            $this->workers[] = Worker::withExpression($bootstrapProfile, $implementationExpression);
        }
    }

    /**
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param string $implementationClassName
     * @param int|null $workerCount
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     *
     * @return static
     */
    public static function withClass(WorkerBootstrapProfile $bootstrapProfile, $implementationClassName, $workerCount = null)
    {
        return new static($bootstrapProfile, $bootstrapProfile->generateExpression($implementationClassName), $workerCount);
    }

    /**
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param string $implementationExpression
     * @param int|null $workerCount
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     *
     * @return static
     */
    public static function withExpression(WorkerBootstrapProfile $bootstrapProfile, $implementationExpression, $workerCount = null)
    {
        return new static($bootstrapProfile, $implementationExpression, $workerCount);
    }

    /**
     * @throws Exception\RuntimeException
     *
     * @return int
     */
    public static function getProcessorCount()
    {
        $getconfPath = self::findGetconf();
        return intval(trim(shell_exec(escapeshellarg($getconfPath).' _NPROCESSORS_ONLN')));
    }

    /**
     * @throws Exception\RuntimeException
     *
     * @return string
     */
    private static function findGetconf()
    {
        $finder = new ExecutableFinder();
        $getconfPath = $finder->find('getconf');
        if ($getconfPath === null) {
            throw new Exception\RuntimeException('Unable to find the "getconf" executable.');
        }

        return $getconfPath;
    }

    /**
     * @param int $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->workers[$offset]);
    }

    /**
     * @param int $offset
     *
     * @return Worker
     */
    public function offsetGet($offset)
    {
        return $this->workers[$offset];
    }

    /**
     * @param int $offset
     * @param mixed $value
     *
     * @throws Exception\LogicException Always
     */
    public function offsetSet($offset, $value)
    {
        throw new Exception\LogicException("A WorkerPool is a read-only container");
    }

    /**
     * @param int $offset
     *
     * @throws Exception\LogicException Always
     */
    public function offsetUnset($offset)
    {
        throw new Exception\LogicException("A WorkerPool is a read-only container");
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->workers);
    }

    /**
     * @return \Iterator
     */
    public function getIterator()
    {
        foreach ($this->workers as $i => $worker) {
            yield $i => $worker;
        }
    }

    /**
     * @param Worker $worker
     *
     * @throws Exception\RuntimeException
     *
     * @return mixed
     */
    public function receiveMessage(&$worker)
    {
        $workers = $this->workers;
        Selectable::selectRead($workers, null);
        if (!count($workers)) {
            throw new Exception\RuntimeException("selectRead returned an empty array (this should not happen)");
        }
        $worker = reset($workers);
        return $worker->receiveMessage();
    }

    /**
     * @param LoopInterface $loop
     * @param callable $listener
     * @param bool $once
     *
     * @return $this
     */
    public function registerRead(LoopInterface $loop, callable $listener, $once = false)
    {
        if ($once) {
            $listener2 = function ($worker, $loop) use ($listener) {
                $this->unregisterRead($loop);
                call_user_func($listener, $worker, $loop);
            };
            foreach ($this->workers as $worker) {
                Selectable::registerRead($loop, $worker, $listener2);
            }
        } else {
            foreach ($this->workers as $worker) {
                Selectable::registerRead($loop, $worker, $listener);
            }
        }

        return $this;
    }

    /**
     * @param LoopInterface $loop
     *
     * @return $this
     */
    public function unregisterRead(LoopInterface $loop)
    {
        foreach ($this->workers as $worker) {
            Selectable::unregisterRead($loop, $worker);
        }

        return $this;
    }
}
