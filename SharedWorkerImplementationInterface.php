<?php

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
