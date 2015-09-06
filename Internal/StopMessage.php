<?php

namespace EXSyst\Component\Worker\Internal;

final class StopMessage
{
    private $cookie;

    public function __construct($cookie)
    {
        $this->cookie = $cookie;
    }

    public function getCookie()
    {
        return $this->cookie;
    }
}
