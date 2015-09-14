<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Channel\ChannelInterface;

interface RawWorkerImplementationInterface
{
    /**
     * @param ChannelInterface $masterChannel
     */
    public function run(ChannelInterface $masterChannel);
}
