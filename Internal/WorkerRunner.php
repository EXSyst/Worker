<?php

namespace EXSyst\Component\Worker\Internal;

use stdClass;
use EXSyst\Component\IO\Channel\ChannelFactoryInterface;
use EXSyst\Component\IO\Channel\SerializedChannelFactory;
use EXSyst\Component\IO\Exception as IOException;
use EXSyst\Component\IO\Selectable;
use EXSyst\Component\IO\Sink;
use EXSyst\Component\IO\Source;
use EXSyst\Component\IO\Source\BufferedSource;
use EXSyst\Component\Worker\EventedWorkerImplementationInterface;
use EXSyst\Component\Worker\Exception;
use EXSyst\Component\Worker\RawWorkerImplementationInterface;
use EXSyst\Component\Worker\SharedWorkerImplementationInterface;
use EXSyst\Component\Worker\Status\WorkerStatus;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

final class WorkerRunner
{
    /**
     * @var string|null
     */
    private static $adminCookie;
    /**
     * @var string|null
     */
    private static $killSwitchPath;
    /**
     * @var LoopInterface|null
     */
    private static $loop;
    /**
     * @var ChannelFactoryInterface|null
     */
    private static $channelFactory;
    /**
     * @var resource|null
     */
    private static $socketContext;
    /**
     * @var resource|null
     */
    private static $socket;
    /**
     * @var boolean
     */
    private static $listening;
    /**
     * @var string|null
     */
    private static $toDelete;

    private function __construct()
    {
    }

    public static function setAdminCookie($adminCookie)
    {
        self::$adminCookie = $adminCookie;
    }

    public static function getAdminCookie()
    {
        return self::$adminCookie;
    }

    public static function setKillSwitchPath($killSwitchPath)
    {
        self::$killSwitchPath = $killSwitchPath;
    }

    public static function getKillSwitchPath()
    {
        return self::$killSwitchPath;
    }

    public static function setLoop(LoopInterface $loop)
    {
        self::$loop = $loop;
    }

    public static function getLoop()
    {
        if (self::$loop === null) {
            self::$loop = Factory::create();
        }
        return self::$loop;
    }

    public static function setChannelFactory(ChannelFactoryInterface $channelFactory)
    {
        self::$channelFactory = $channelFactory;
    }

    public static function getChannelFactory()
    {
        if (self::$channelFactory === null) {
            self::$channelFactory = SerializedChannelFactory::getInstance();
        }
        return self::$channelFactory;
    }

    public static function setSocketContext($socketContext)
    {
        if ($socketContext !== null && !is_resource($socketContext)) {
            throw new Exception\InvalidArgumentException('Bad socket context');
        }
        self::$socketContext = $socketContext;
    }

    public static function getSocketContext()
    {
        return self::$socketContext;
    }

    public static function runDedicatedWorker($workerImpl)
    {
        $channel = self::getChannelFactory()->createChannel(Source::fromInput(), Sink::fromOutput());
        if ($workerImpl instanceof RawWorkerImplementationInterface) {
            $workerImpl->run($channel);
        } elseif ($workerImpl instanceof EventedWorkerImplementationInterface) {
            $loop = self::getLoop();
            Selectable::registerRead($loop, $channel, function () use ($loop, $channel, $workerImpl) {
                try {
                    $message = $channel->receiveMessage();
                } catch (IOException\UnderflowException $e) {
                    Selectable::unregisterRead($loop, $channel);
                    $workerImpl->onDisconnect($channel);
                    return;
                }
                $workerImpl->onMessage($message, $channel, null);
            });
            $workerImpl->setLoop($loop);
            $workerImpl->initialize();
            $workerImpl->onConnect($channel, null);
            $loop->run();
            $workerImpl->terminate();
        } else {
            throw new Exception\InvalidArgumentException('Bad worker implementation');
        }
    }

    public static function runSharedWorker(SharedWorkerImplementationInterface $workerImpl, $socketAddress)
    {
        $server = self::startListening($socketAddress);
        try {
            $loop = self::getLoop();
            $loop->addReadStream($server, function () use ($loop, $server, $workerImpl) {
                $socket = stream_socket_accept($server);
                $peerName = stream_socket_get_name($socket, true);
                $connection = Source::fromStream($socket, true, null, false);
                $channel = self::getChannelFactory()->createChannel(new BufferedSource($connection), $connection);
                Selectable::registerRead($loop, $channel, function () use ($loop, $channel, $peerName, $workerImpl) {
                    try {
                        $message = $channel->receiveMessage();
                    } catch (IOException\UnderflowException $e) {
                        Selectable::unregisterRead($loop, $channel);
                        $workerImpl->onDisconnect($channel);
                        return;
                    }
                    if (AdminEncoding::isStopMessage($message, self::$adminCookie, $privileged)) {
                        if ($privileged) {
                            self::stopListening();
                            $workerImpl->onStop();
                        }
                    } elseif (AdminEncoding::isQueryMessage($message, self::$adminCookie, $privileged)) {
                        $result = $workerImpl->onQuery($privileged);
                        if (!($result instanceof WorkerStatus)) {
                            $result = new WorkerStatus($result);
                        }
                        AdminEncoding::sendStatusMessage($channel, $result);
                    } else {
                        $workerImpl->onMessage($message, $channel, $peerName);
                    }
                });
                $workerImpl->onConnect($channel, $peerName);
            });
            $workerImpl->setLoop($loop);
            $workerImpl->initialize();
            $loop->run();
            $workerImpl->terminate();
        } finally {
            self::stopListening();
        }
    }

    private static function startListening($socketAddress)
    {
        $lock = Lock::acquire();
        if (self::$killSwitchPath !== null) {
            $kswitch = new KillSwitch(self::$killSwitchPath);
            if ($kswitch->getGlobal() || $kswitch->hasAddress($socketAddress)) {
                throw new Exception\RuntimeException("This worker has been prevented from starting using the kill switch");
            }
        }
        $socketFile = IdentificationHelper::getSocketFile($socketAddress);
        if ($socketFile !== null) {
            $socketDir = dirname($socketFile);
            if (!is_dir($socketDir)) {
                mkdir($socketDir, 0777, true);
            }
        }
        try {
            $server = SocketFactory::createServerSocket($socketAddress, self::$socketContext);
        } catch (Exception\BindOrListenException $e) {
            if (strpos($e->getMessage(), 'Address already in use') !== false && $socketFile !== null) {
                try {
                    fclose(SocketFactory::createClientSocket($socketAddress, 1, self::$socketContext));
                    // Really in use
                    throw $e;
                } catch (Exception\ConnectException $e2) {
                    // False positive due to a residual socket file
                    unlink($socketFile);
                    $server = SocketFactory::createServerSocket($socketAddress, self::$socketContext);
                }
            } else {
                throw $e;
            }
        }
        $lock->release();
        self::$socket = $server;
        self::$listening = true;
        self::$toDelete = $socketFile;

        return $server;
    }

    public static function stopListening()
    {
        if (self::$listening) {
            self::$listening = false;
            self::getLoop()->removeReadStream(self::$socket);
            fclose(self::$socket);
            self::$socket = null;
        }
        if (isset(self::$toDelete)) {
            unlink(self::$toDelete);
            self::$toDelete = null;
        }
    }
}
