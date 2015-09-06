<?php

namespace EXSyst\Component\Worker;

use EXSyst\Component\IO\Channel\ChannelInterface;

interface RawWorkerImplementationInterface
{
    public function run(ChannelInterface $masterChannel);
}
