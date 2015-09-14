<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Channel\ChannelInterface;
use EXSyst\Component\IO\Source;
use EXSyst\Component\IO\Source\BufferedSource;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\Internal\AdminEncoding;
use EXSyst\Component\Worker\Internal\IdentificationHelper;
use EXSyst\Component\Worker\Internal\SocketFactory;
use EXSyst\Component\Worker\Internal\WorkerRunner;

class SharedWorker implements ChannelInterface
{
    /**
     * @var string
     */
    private $socketAddress;
    /**
     * @var false|int|null
     */
    private $processId;
    /**
     * @var ChannelInterface
     */
    private $channel;
    /**
     * @var string|null
     */
    private $adminCookie;
    /**
     * @var array
     */
    private $unreceivedMessages;

    /**
     * @param string                 $socketAddress
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param string|null            $implementationExpression
     * @param bool                   $autoStart
     *
     * @throws Exception\ConnectException
     */
    protected function __construct($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression = null, $autoStart = true)
    {
        $this->socketAddress = $socketAddress;
        $this->processId = false;
        $this->adminCookie = $bootstrapProfile->getAdminCookie();
        $this->unreceivedMessages = [];
        try {
            $this->channel = self::connect($socketAddress, $bootstrapProfile);
        } catch (Exception\ConnectException $e) {
            if (!$autoStart) {
                throw $e;
            }
            static::startWithExpression($socketAddress, $bootstrapProfile, $implementationExpression, $e);
            // Try every 0.2s for 10s
            for ($i = 0; $i < 50; ++$i) {
                try {
                    // If we get here, the timeout should be useless, but, just in case ...
                    $this->channel = self::connect($socketAddress, $bootstrapProfile, 1);
                    break;
                } catch (Exception\ConnectException $e2) {
                    // The worker may still be initializing, so wait, and try again
                }
                usleep(200000);
            }
            if (!$this->channel) {
                // Final attempt
                $this->channel = self::connect($socketAddress, $bootstrapProfile);
            }
        }
    }

    /**
     * @param string                 $socketAddress
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param int|null               $timeout
     *
     * @throws Exception\ConnectException
     *
     * @return ChannelInterface
     */
    private static function connect($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $timeout = null)
    {
        $socket = SocketFactory::createClientSocket($socketAddress, $timeout);
        $connection = Source::fromStream($socket, true, null, false);

        return $bootstrapProfile->getChannelFactory()->createChannel(new BufferedSource($connection), $connection);
    }

    /**
     * @param string                 $socketAddress
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param string|null            $implementationClassName
     * @param bool                   $autoStart
     *
     * @throws Exception\ConnectException
     *
     * @return static
     */
    public static function withClass($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationClassName = null, $autoStart = true)
    {
        return new static($socketAddress, $bootstrapProfile, ($implementationClassName === null) ? null : $bootstrapProfile->generateExpression($implementationClassName), $autoStart);
    }

    /**
     * @param string                 $socketAddress
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param string|null            $implementationExpression
     * @param bool                   $autoStart
     *
     * @throws Exception\ConnectException
     *
     * @return static
     */
    public static function withExpression($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression = null, $autoStart = true)
    {
        return new static($socketAddress, $bootstrapProfile, $implementationExpression, $autoStart);
    }

    /**
     * @param string                 $socketAddress
     * @param WorkerBootstrapProfile $bootstrapProfile
     * @param string                 $implementationClassName
     *
     * @throws Exception\ConnectException
     * @throws Exception\LogicException
     */
    public static function startWithClass($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationClassName)
    {
        static::startWithExpression($socketAddress, $bootstrapProfile, $bootstrapProfile->generateExpression($implementationClassName));
    }

    /**
     * @param string                          $socketAddress
     * @param WorkerBootstrapProfile          $bootstrapProfile
     * @param string                          $implementationExpression
     * @param Exception\ConnectException|null $e
     *
     * @throws Exception\ConnectException
     * @throws Exception\LogicException
     */
    public static function startWithExpression($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression, Exception\ConnectException $e = null)
    {
        if (!IdentificationHelper::isLocalAddress($socketAddress)) {
            if ($e) {
                throw $e;
            } else {
                throw new Exception\LogicException("Can't start the worker, because its socket address is not local");
            }
        }

        $bootstrapProfile->getOrFindPhpExecutablePathAndArguments($php, $phpArgs);
        $bootstrapProfile->compileScriptWithExpression($implementationExpression, $socketAddress, $scriptPath, $deleteScript);

        try {
            $line = array_merge([$php], $phpArgs, [$scriptPath]);
            system(implode(' ', array_map('escapeshellarg', $line)).' </dev/null >/dev/null 2>&1 &');
        } catch (\Exception $e) {
            if ($deleteScript) {
                unlink($scriptPath);
            }
            throw $e;
        }
    }

    /**
     * @param string $socketAddress
     *
     * @throws Exception\RuntimeException
     *
     * @return int|null
     */
    public static function getWorkerProcessId($socketAddress)
    {
        return IdentificationHelper::getListeningProcessId($socketAddress);
    }

    /**
     * @param string                 $socketAddress
     * @param WorkerBootstrapProfile $bootstrapProfile
     *
     * @throws Exception\LogicException
     * @throws Exception\RuntimeException
     *
     * @return bool
     */
    public static function stopWorker($socketAddress, WorkerBootstrapProfile $bootstrapProfile)
    {
        $adminCookie = $bootstrapProfile->getAdminCookie();
        if ($adminCookie === null) {
            throw new Exception\LogicException('Cannot stop a shared worker without an admin cookie');
        }
        try {
            $channel = self::connect($socketAddress, $bootstrapProfile);
        } catch (Exception\RuntimeException $e) {
            return false;
        }
        AdminEncoding::sendStopMessage($channel, $adminCookie);

        return true;
    }

    /**
     * @param string                 $socketAddress
     * @param WorkerBootstrapProfile $bootstrapProfile
     *
     * @throws Exception\LogicException
     * @throws Exception\RuntimeException
     *
     * @return Status\WorkerStatus
     */
    public static function queryWorker($socketAddress, WorkerBootstrapProfile $bootstrapProfile)
    {
        $adminCookie = $bootstrapProfile->getAdminCookie();
        $channel = self::connect($socketAddress, $bootstrapProfile);
        AdminEncoding::sendQueryMessage($channel, $adminCookie);
        for (;;) {
            $message = $channel->receiveMessage();
            $status = AdminEncoding::getStatusMessage($message);
            if ($status !== null) {
                return $status;
            }
        }
    }

    public static function stopCurrent()
    {
        WorkerRunner::stopListening();
    }

    /**
     * @return string
     */
    public function getSocketAddress()
    {
        return $this->socketAddress;
    }

    /**
     * @throws Exception\RuntimeException
     *
     * @return int|null
     */
    public function getProcessId()
    {
        if ($this->processId === false) {
            $this->processId = IdentificationHelper::getListeningProcessId($this->socketAddress);
        }

        return $this->processId;
    }

    /**
     * @throws Exception\LogicException
     * @throws Exception\RuntimeException
     *
     * @return $this
     */
    public function stop()
    {
        if ($this->adminCookie === null) {
            throw new Exception\LogicException('Cannot stop a shared worker without an admin cookie');
        }
        AdminEncoding::sendStopMessage($this->channel, $this->adminCookie);

        return $this;
    }

    /**
     * @throws Exception\RuntimeException
     *
     * @return Status\WorkerStatus
     */
    public function query()
    {
        AdminEncoding::sendQueryMessage($this->channel, $this->adminCookie);
        foreach ($this->unreceivedMessages as $i => $message) {
            $status = AdminEncoding::getStatusMessage($message);
            if ($status !== null) {
                array_splice($this->unreceivedMessages, $i, 1);

                return $status;
            }
        }
        for (;;) {
            $message = $this->channel->receiveMessage();
            $status = AdminEncoding::getStatusMessage($message);
            if ($status !== null) {
                return $status;
            }
            $this->unreceivedMessages[] = $message;
        }
    }

    /** {@inheritdoc} */
    public function getStream()
    {
        return $this->channel->getStream();
    }

    /** {@inheritdoc} */
    public function sendMessage($message)
    {
        $this->channel->sendMessage($message);

        return $this;
    }

    /** {@inheritdoc} */
    public function receiveMessage()
    {
        if (count($this->unreceivedMessages)) {
            return array_shift($this->unreceivedMessages);
        }

        return $this->channel->receiveMessage();
    }

    /**
     * @throws Exception\RuntimeException
     *
     * @return mixed
     */
    public function receiveMessageDirectly()
    {
        return $this->channel->receiveMessage();
    }

    /**
     * @return int
     */
    public function getUnreceivedMessageCount()
    {
        return count($this->unreceivedMessages);
    }

    /**
     * @return array
     */
    public function peekUnreceivedMessages()
    {
        return $this->unreceivedMessages;
    }

    /**
     * @param int $index
     *
     * @throws Exception\OutOfRangeException
     *
     * @return $this
     */
    public function removeUnreceivedMessage($index)
    {
        if ($index < 0 || $index >= count($this->unreceivedMessages)) {
            throw new Exception\OutOfRangeException('The "un-received" message index must be between 0 (inclusive) and the length of the queue (exclusive)');
        }
        array_splice($this->unreceivedMessages, $index, 1);

        return $this;
    }

    /**
     * @param mixed $message
     *
     * @return $this
     */
    public function unreceiveMessage($message)
    {
        $this->unreceivedMessages[] = $message;

        return $this;
    }
}
