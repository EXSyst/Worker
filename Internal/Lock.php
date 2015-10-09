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

final class Lock
{
    /**
     * @var resource|null
     */
    private static $fd;
    /**
     * @var int
     */
    private static $counter;

    /**
     * @var bool
     */
    private $held;

    private function __construct()
    {
        if (self::$counter++ == 0) {
            self::$fd = fopen(__FILE__, 'rb');
            flock(self::$fd, LOCK_EX);
        }
        $this->held = true;
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * @return self
     */
    public static function acquire()
    {
        return new self();
    }

    public function release()
    {
        if ($this->held) {
            $this->held = false;
            if (--self::$counter == 0) {
                flock(self::$fd, LOCK_UN);
                fclose(self::$fd);
                self::$fd = null;
            }
        }
    }
}
