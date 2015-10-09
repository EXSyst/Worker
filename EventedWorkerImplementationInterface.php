<?php

/*
 * This file is part of the Worker package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Channel\ChannelInterface;
use React\EventLoop\LoopInterface;

interface EventedWorkerImplementationInterface
{
    /**
     * @param LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop);

    public function initialize();

    /**
     * @param ChannelInterface $channel
     * @param string           $peerName
     */
    public function onConnect(ChannelInterface $channel, $peerName);

    /**
     * @param mixed            $message
     * @param ChannelInterface $channel
     * @param string           $peerName
     */
    public function onMessage($message, ChannelInterface $channel, $peerName);

    /**
     * @param ChannelInterface $channel
     */
    public function onDisconnect(ChannelInterface $channel);

    public function terminate();
}
