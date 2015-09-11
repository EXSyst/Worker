<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Source;
use EXSyst\Component\IO\Source\BufferedSource;
use EXSyst\Component\IO\Channel\ChannelInterface;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\Internal\AdminEncoding;
use EXSyst\Component\Worker\Internal\SocketFactory;
use EXSyst\Component\Worker\Internal\WorkerRunner;

class SharedWorker implements ChannelInterface
{
    private $channel;
    private $adminCookie;
    private $unreceivedMessages;

    protected function __construct($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression = null, $autoStart = true)
    {
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

    private static function connect($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $timeout = null)
    {
        $socket = SocketFactory::createClientSocket($socketAddress, $timeout);
        $connection = Source::fromStream($socket, true, null, false);

        return $bootstrapProfile->getChannelFactory()->createChannel(new BufferedSource($connection), $connection);
    }

    public static function withClass($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationClassName = null, $autoStart = true)
    {
        return new static($socketAddress, $bootstrapProfile, ($implementationClassName === null) ? null : $bootstrapProfile->generateExpression($implementationClassName), $autoStart);
    }

    public static function withExpression($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression = null, $autoStart = true)
    {
        return new static($socketAddress, $bootstrapProfile, $implementationExpression, $autoStart);
    }

    public static function startWithClass($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationClassName)
    {
        static::startWithExpression($socketAddress, $bootstrapProfile, $bootstrapProfile->generateExpression($implementationClassName));
    }

    public static function startWithExpression($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression, Exception\ConnectException $e = null)
    {
        if (!self::isLocalAddress($socketAddress)) {
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

    public static function isLocalAddress($socketAddress)
    {
        if (substr_compare($socketAddress, 'unix://', 0, 7) === 0) {
            return true;
        }
        $localAddresses = array_merge([
            '0.0.0.0',
            '127.0.0.1',
            '[::]',
            '[::1]',
        ], gethostbynamel(gethostname()));
        foreach ($localAddresses as $address) {
            if (strpos($socketAddress, $address) !== false) {
                return true;
            }
        }

        return false;
    }

    public function stop()
    {
        if ($this->adminCookie === null) {
            throw new Exception\LogicException('Cannot stop a shared worker without an admin cookie');
        }
        AdminEncoding::sendStopMessage($this->channel, $this->adminCookie);

        return $this;
    }

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

    public function receiveMessageDirectly()
    {
        return $this->channel->receiveMessage();
    }

    public function getUnreceivedMessageCount()
    {
        return count($this->unreceivedMessages);
    }

    public function peekUnreceivedMessages()
    {
        return $this->unreceivedMessages;
    }

    public function removeUnreceivedMessage($index)
    {
        if ($index < 0 || $index >= count($this->unreceivedMessages)) {
            throw new Exception\OutOfRangeException('The "un-received" message index must be between 0 (inclusive) and the length of the queue (exclusive)');
        }
        array_splice($this->unreceivedMessages, $index, 1);

        return $this;
    }

    public function unreceiveMessage($message)
    {
        $this->unreceivedMessages[] = $message;

        return $this;
    }
}
