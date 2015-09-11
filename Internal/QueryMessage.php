<?php

namespace EXSyst\Component\Worker\Internal;

final class QueryMessage
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
