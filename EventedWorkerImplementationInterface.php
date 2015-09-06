<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Channel\ChannelInterface;

use React\EventLoop\LoopInterface;

interface EventedWorkerImplementationInterface
{
    public function setLoop(LoopInterface $loop);

    public function initialize();

    public function onConnect(ChannelInterface $channel, $peerName);

    public function onMessage($message, ChannelInterface $channel, $peerName);

    public function onDisconnect(ChannelInterface $channel);

    public function terminate();
}
