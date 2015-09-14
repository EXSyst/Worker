<?php

namespace EXSyst\Component\Worker;

interface SharedWorkerImplementationInterface extends EventedWorkerImplementationInterface
{
    public function onQuery($privileged);

    public function onStop();
}
