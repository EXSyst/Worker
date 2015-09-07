<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;

class WorkerFactory
{
    private $bootstrapProfile;

    public function __construct(WorkerBootstrapProfile $bootstrapProfile)
    {
        $this->bootstrapProfile = $bootstrapProfile;
    }

    public function getBootstrapProfile()
    {
        return $this->bootstrapProfile;
    }

    public function createWorker($implementationClassName)
    {
        return Worker::withClass($this->bootstrapProfile, $implementationClassName);
    }

    public function createWorkerWithExpression($implementationExpression)
    {
        return Worker::withExpression($this->bootstrapProfile, $implementationExpression);
    }

    public function createWorkerPool($implementationClassName, $workerCount)
    {
        return WorkerPool::withClass($this->bootstrapProfile, $implementationClassName, $workerCount);
    }

    public function createWorkerPoolWithExpression($implementationExpression, $workerCount)
    {
        return WorkerPool::withExpression($this->bootstrapProfile, $implementationExpression, $workerCount);
    }

    public function connectToSharedWorker($socketAddress, $implementationClassName = null)
    {
        return SharedWorker::withClass($socketAddress, $this->bootstrapProfile, $implementationClassName);
    }

    public function connectToSharedWorkerWithExpression($socketAddress, $implementationExpression = null)
    {
        return SharedWorker::withExpression($socketAddress, $this->bootstrapProfile, $implementationExpression);
    }

    public function startSharedWorker($socketAddress, $implementationClassName)
    {
        SharedWorker::startWithClass($socketAddress, $this->bootstrapProfile, $implementationClassName);
    }

    public function startSharedWorkerWithExpression($socketAddress, $implementationExpression)
    {
        SharedWorker::startWithExpression($socketAddress, $this->bootstrapProfile, $implementationExpression);
    }

    public function stopSharedWorker($socketAddress)
    {
        SharedWorker::stopWorker($socketAddress, $this->bootstrapProfile);
    }
}
