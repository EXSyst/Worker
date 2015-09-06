<?php

namespace EXSyst\Component\Worker;

interface SharedWorkerImplementationInterface extends EventedWorkerImplementationInterface
{
    public function onStop();
}
