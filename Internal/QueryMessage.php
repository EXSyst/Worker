<?php

namespace EXSyst\Component\Worker\Internal;

final class QueryMessage
{
    /**
     * @var string
     */
    private $cookie;

    /**
     * @param string $cookie
     */
    public function __construct($cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * @return string
     */
    public function getCookie()
    {
        return $this->cookie;
    }
}
