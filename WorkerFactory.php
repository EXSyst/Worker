<?php

namespace EXSyst\Component\Worker;

use Traversable;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\Internal\KillSwitch;
use EXSyst\Component\Worker\Internal\Lock;

class WorkerFactory
{
    private $bootstrapProfile;

    public function __construct(WorkerBootstrapProfile $bootstrapProfile = null)
    {
        $this->bootstrapProfile = isset($bootstrapProfile) ? $bootstrapProfile : new WorkerBootstrapProfile();
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

    public function createWorkerPool($implementationClassName, $workerCount = null)
    {
        return WorkerPool::withClass($this->bootstrapProfile, $implementationClassName, $workerCount);
    }

    public function createWorkerPoolWithExpression($implementationExpression, $workerCount = null)
    {
        return WorkerPool::withExpression($this->bootstrapProfile, $implementationExpression, $workerCount);
    }

    public function connectToSharedWorker($socketAddress, $implementationClassName = null, $autoStart = true)
    {
        return SharedWorker::withClass($socketAddress, $this->bootstrapProfile, $implementationClassName, $autoStart);
    }

    public function connectToSharedWorkerWithExpression($socketAddress, $implementationExpression = null, $autoStart = true)
    {
        return SharedWorker::withExpression($socketAddress, $this->bootstrapProfile, $implementationExpression, $autoStart);
    }

    public function startSharedWorker($socketAddress, $implementationClassName)
    {
        SharedWorker::startWithClass($socketAddress, $this->bootstrapProfile, $implementationClassName);

        return $this;
    }

    public function startSharedWorkerWithExpression($socketAddress, $implementationExpression)
    {
        SharedWorker::startWithExpression($socketAddress, $this->bootstrapProfile, $implementationExpression);

        return $this;
    }

    public function getSharedWorkerProcessId($socketAddress)
    {
        return SharedWorker::getWorkerProcessId($socketAddress);
    }

    public function stopSharedWorker($socketAddress)
    {
        return SharedWorker::stopWorker($socketAddress, $this->bootstrapProfile);
    }

    public function querySharedWorker($socketAddress)
    {
        return SharedWorker::queryWorker($socketAddress, $this->bootstrapProfile);
    }

    private function transactKillSwitch(callable $operation)
    {
        $ksPath = $this->bootstrapProfile->getKillSwitchPath();
        if ($ksPath === null) {
            throw new Exception\LogicException('No kill switch has been configured');
        }
        $lock = Lock::acquire();
        $kswitch = new KillSwitch($ksPath);
        $retval = call_user_func($operation, $kswitch);
        $kswitch->save();
        $lock->release();

        return ($retval !== null) ? $retval : $this;
    }

    public function disableSharedWorker($socketAddress)
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) use ($socketAddress) {
            if (is_array($socketAddress) || $socketAddress instanceof Traversable) {
                foreach ($socketAddress as $address) {
                    $kswitch->addAddress($address);
                }
            } else {
                $kswitch->addAddress($socketAddress);
            }
        });
    }

    public function reEnableSharedWorker($socketAddress)
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) use ($socketAddress) {
            if (is_array($socketAddress) || $socketAddress instanceof Traversable) {
                foreach ($socketAddress as $address) {
                    $kswitch->removeAddress($address);
                }
            } else {
                $kswitch->removeAddress($socketAddress);
            }
        });
    }

    public function isSharedWorkerDisabled($socketAddress)
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) use ($socketAddress) {
            return $kswitch->hasAddress($socketAddress);
        });
    }

    public function getDisabledSharedWorkers()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            return $kswitch->getAddresses();
        });
    }

    public function disableSharedWorkersGlobally()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            $kswitch->setGlobal(true);
        });
    }

    public function reEnableSharedWorkersGlobally()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            $kswitch->setGlobal(false);
        });
    }

    public function areSharedWorkersDisabledGlobally()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            return $kswitch->getGlobal();
        });
    }

    public function reEnableAllSharedWorkers()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            $kswitch->setAddresses([])->setGlobal(false);
        });
    }
}
