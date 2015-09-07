<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Source;
use EXSyst\Component\IO\Sink;

use EXSyst\Component\IO\Channel\ChannelInterface;

use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;

use EXSyst\Component\Worker\Exception;

class Worker implements ChannelInterface
{
    private $process;
    private $channel;

    protected function __construct(WorkerBootstrapProfile $bootstrapProfile, $implementationExpression)
    {
        $bootstrapProfile->getOrFindPhpExecutablePathAndArguments($php, $phpArgs);
        $bootstrapProfile->compileScriptWithExpression($implementationExpression, null, $scriptPath, $deleteScript);

        try {
            $line = array_merge([ $php ], $phpArgs, [ $scriptPath ]);
            $this->process = proc_open(implode(' ', array_map('escapeshellarg', $line)), [
                0 => [ 'pipe', 'r' ],
                1 => [ 'pipe', 'w' ],
                2 => STDERR
            ], $pipes);
            $inputSink = Sink::fromStream($pipes[0], true);
            $outputSource = Source::fromStream($pipes[1], true);
            $this->channel = $bootstrapProfile->getChannelFactory()->createChannel($outputSource, $inputSink);
        } catch (\Exception $e) {
            if (isset($pipes[1]))
                fclose($pipes[1]);
            if (isset($pipes[0]))
                fclose($pipes[0]);
            if (isset($this->process)) {
                proc_terminate($this->process);
                proc_close($this->process);
            }
            if ($deleteScript) {
                unlink($scriptPath);
            }
            throw $e;
        }
    }

    public function __destruct()
    {
        unset($this->channel);
        proc_close($this->process);
    }

    public static function withClass(WorkerBootstrapProfile $bootstrapProfile, $implementationClassName)
    {
        return new static($bootstrapProfile, $bootstrapProfile->generateExpression($implementationClassName));
    }

    public static function withExpression(WorkerBootstrapProfile $bootstrapProfile, $implementationExpression)
    {
        return new static($bootstrapProfile, $implementationExpression);
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
