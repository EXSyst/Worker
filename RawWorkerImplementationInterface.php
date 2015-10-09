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

interface RawWorkerImplementationInterface
{
    /**
     * @param ChannelInterface $masterChannel
     */
    public function run(ChannelInterface $masterChannel);
}
