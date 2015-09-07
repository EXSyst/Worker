<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Source;
use EXSyst\Component\IO\Source\BufferedSource;
use EXSyst\Component\IO\Channel\ChannelInterface;
use EXSyst\Component\IO\Channel\SerializedChannel;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\Internal\StopMessage;
use EXSyst\Component\Worker\Internal\WorkerRunner;

class SharedWorker implements ChannelInterface
{
    private $channel;
    private $stopCookie;

    protected function __construct($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression = null)
    {
        $this->stopCookie = $bootstrapProfile->getStopCookie();
        try {
            $this->channel = self::connect($socketAddress, $bootstrapProfile);
        } catch (Exception\RuntimeException $e) {
            static::startWithExpression($socketAddress, $bootstrapProfile, $implementationExpression, true);
            // Try every 0.2s for 10s
            for ($i = 0; $i < 50; ++$i) {
                try {
                    // If we get here, the timeout should be useless, but, just in case ...
                    $this->channel = self::connect($socketAddress, $bootstrapProfile, 1);
                    break;
                } catch (Exception\RuntimeException $e2) {
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
        $socket = WorkerRunner::createClientSocket($socketAddress, $errno, $errstr, $timeout);
        if ($socket === false) {
            throw new Exception\RuntimeException("Can't create client socket : ".$errstr, $errno);
        }
        $connection = Source::fromStream($socket, true, null, false);

        return $bootstrapProfile->getChannelFactory()->createChannel(new BufferedSource($connection), $connection);
    }

    public static function withClass($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationClassName)
    {
        return new static($socketAddress, $bootstrapProfile, $bootstrapProfile->generateExpression($implementationClassName));
    }

    public static function withExpression($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression)
    {
        return new static($socketAddress, $bootstrapProfile, $implementationExpression);
    }

    public static function startWithClass($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationClassName)
    {
        static::startWithExpression($socketAddress, $bootstrapProfile, $bootstrapProfile->generateExpression($implementationClassName));
    }

    public static function startWithExpression($socketAddress, WorkerBootstrapProfile $bootstrapProfile, $implementationExpression, $auto = false)
    {
        if (!self::isLocalAddress($socketAddress)) {
            if ($auto) {
                throw new Exception\RuntimeException("Can't create client socket, and can't start the worker, because its socket address is not local");
            } else {
                throw new Exception\LogicException("Can't start the worker, because its socket address is not local");
            }
        }

        $bootstrapProfile->getOrFindPhpExecutablePathAndArguments($php, $phpArgs);
        $bootstrapProfile->compileScriptWithExpression($implementationExpression, $socketAddress, $scriptPath, $deleteScript);

        try {
            $line = array_merge([$php], $phpArgs, [$scriptPath]);
            system(implode(' ', array_map('escapeshellarg', $line)).' >/dev/null 2>&1 &');
        } catch (\Exception $e) {
            if ($deleteScript) {
                unlink($scriptPath);
            }
            throw $e;
        }
    }

    public static function stopWorker($socketAddress, WorkerBootstrapProfile $bootstrapProfile)
    {
        $stopCookie = $bootstrapProfile->getStopCookie();
        if ($stopCookie === null) {
            throw new Exception\LogicException('Cannot stop a shared worker without a stop cookie');
        }
        try {
            $channel = self::connect($socketAddress, $bootstrapProfile);
        } catch (Exception\RuntimeException $e) {
            return false;
        }
        self::sendStopMessage($channel, $stopCookie);
        return true;
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

    private static function sendStopMessage(ChannelInterface $channel, $stopCookie)
    {
        $channel->sendMessage(($channel instanceof SerializedChannel) ? new StopMessage($stopCookie) : ['_stop_' => $stopCookie]);
    }

    public function stop()
    {
        self::sendStopMessage($this->channel, $this->stopCookie);

        return $this;
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
        return $this->channel->receiveMessage();
    }
}
