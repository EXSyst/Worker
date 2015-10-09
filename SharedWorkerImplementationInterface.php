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

use EXSyst\Component\Worker\Status\WorkerStatus;

interface SharedWorkerImplementationInterface extends EventedWorkerImplementationInterface
{
    /**
     * @param bool $privileged
     *
     * @return WorkerStatus|string|null
     */
    public function onQuery($privileged);

    public function onStop();
}
