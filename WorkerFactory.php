<?php

namespace EXSyst\Component\Worker;

use Traversable;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\Internal\KillSwitch;
use EXSyst\Component\Worker\Internal\Lock;

class WorkerFactory
{
    /**
     * @var WorkerBootstrapProfile
     */
    private $bootstrapProfile;

    /**
     * @param WorkerBootstrapProfile|null $bootstrapProfile
     */
    public function __construct(WorkerBootstrapProfile $bootstrapProfile = null)
    {
        $this->bootstrapProfile = isset($bootstrapProfile) ? $bootstrapProfile : new WorkerBootstrapProfile();
    }

    /**
     * @return WorkerBootstrapProfile
     */
    public function getBootstrapProfile()
    {
        return $this->bootstrapProfile;
    }

    /**
     * @param string $implementationClassName
     *
     * @return Worker
     */
    public function createWorker($implementationClassName)
    {
        return Worker::withClass($this->bootstrapProfile, $implementationClassName);
    }

    /**
     * @param string $implementationExpression
     *
     * @return Worker
     */
    public function createWorkerWithExpression($implementationExpression)
    {
        return Worker::withExpression($this->bootstrapProfile, $implementationExpression);
    }

    /**
     * @param string   $implementationClassName
     * @param int|null $workerCount
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     *
     * @return WorkerPool
     */
    public function createWorkerPool($implementationClassName, $workerCount = null)
    {
        return WorkerPool::withClass($this->bootstrapProfile, $implementationClassName, $workerCount);
    }

    /**
     * @param string   $implementationExpression
     * @param int|null $workerCount
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     *
     * @return WorkerPool
     */
    public function createWorkerPoolWithExpression($implementationExpression, $workerCount = null)
    {
        return WorkerPool::withExpression($this->bootstrapProfile, $implementationExpression, $workerCount);
    }

    /**
     * @param string      $socketAddress
     * @param string|null $implementationClassName
     * @param bool        $autoStart
     *
     * @throws Exception\ConnectException
     *
     * @return SharedWorker
     */
    public function connectToSharedWorker($socketAddress, $implementationClassName = null, $autoStart = true)
    {
        return SharedWorker::withClass($socketAddress, $this->bootstrapProfile, $implementationClassName, $autoStart);
    }

    /**
     * @param string      $socketAddress
     * @param string|null $implementationExpression
     * @param bool        $autoStart
     *
     * @throws Exception\ConnectException
     *
     * @return SharedWorker
     */
    public function connectToSharedWorkerWithExpression($socketAddress, $implementationExpression = null, $autoStart = true)
    {
        return SharedWorker::withExpression($socketAddress, $this->bootstrapProfile, $implementationExpression, $autoStart);
    }

    /**
     * @param string $socketAddress
     * @param string $implementationClassName
     *
     * @throws Exception\ConnectException
     * @throws Exception\LogicException
     *
     * @return $this
     */
    public function startSharedWorker($socketAddress, $implementationClassName)
    {
        SharedWorker::startWithClass($socketAddress, $this->bootstrapProfile, $implementationClassName);

        return $this;
    }

    /**
     * @param string $socketAddress
     * @param string $implementationExpression
     *
     * @throws Exception\ConnectException
     * @throws Exception\LogicException
     *
     * @return $this
     */
    public function startSharedWorkerWithExpression($socketAddress, $implementationExpression)
    {
        SharedWorker::startWithExpression($socketAddress, $this->bootstrapProfile, $implementationExpression);

        return $this;
    }

    /**
     * @param string $socketAddress
     *
     * @throws Exception\RuntimeException
     *
     * @return int|null
     */
    public function getSharedWorkerProcessId($socketAddress)
    {
        return SharedWorker::getWorkerProcessId($socketAddress);
    }

    /**
     * @param string $socketAddress
     *
     * @throws Exception\LogicException
     * @throws Exception\RuntimeException
     *
     * @return bool
     */
    public function stopSharedWorker($socketAddress)
    {
        return SharedWorker::stopWorker($socketAddress, $this->bootstrapProfile);
    }

    /**
     * @param string $socketAddress
     *
     * @throws Exception\LogicException
     * @throws Exception\RuntimeException
     *
     * @return Status\WorkerStatus
     */
    public function querySharedWorker($socketAddress)
    {
        return SharedWorker::queryWorker($socketAddress, $this->bootstrapProfile);
    }

    /**
     * @param callable $operation
     *
     * @throws Exception\LogicException
     *
     * @return mixed|$this
     */
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

    /**
     * @param string|array|Traversable $socketAddress
     *
     * @throws Exception\LogicException
     *
     * @return $this
     */
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

    /**
     * @param string|array|Traversable $socketAddress
     *
     * @throws Exception\LogicException
     *
     * @return $this
     */
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

    /**
     * @param string $socketAddress
     *
     * @throws Exception\LogicException
     *
     * @return bool
     */
    public function isSharedWorkerDisabled($socketAddress)
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) use ($socketAddress) {
            return $kswitch->hasAddress($socketAddress);
        });
    }

    /**
     * @throws Exception\LogicException
     *
     * @return array
     */
    public function getDisabledSharedWorkers()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            return $kswitch->getAddresses();
        });
    }

    /**
     * @throws Exception\LogicException
     *
     * @return $this
     */
    public function disableSharedWorkersGlobally()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            $kswitch->setGlobal(true);
        });
    }

    /**
     * @throws Exception\LogicException
     *
     * @return $this
     */
    public function reEnableSharedWorkersGlobally()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            $kswitch->setGlobal(false);
        });
    }

    /**
     * @throws Exception\LogicException
     *
     * @return bool
     */
    public function areSharedWorkersDisabledGlobally()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            return $kswitch->getGlobal();
        });
    }

    /**
     * @throws Exception\LogicException
     *
     * @return $this
     */
    public function reEnableAllSharedWorkers()
    {
        return $this->transactKillSwitch(function (KillSwitch $kswitch) {
            $kswitch->setAddresses([])->setGlobal(false);
        });
    }
}
