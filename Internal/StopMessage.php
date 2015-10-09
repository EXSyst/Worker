<?php

/*
 * This file is part of the Worker package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Component\Worker\Internal;

final class StopMessage
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
