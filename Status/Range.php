<?php

/*
 * This file is part of the Worker package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Component\Worker\Status;

use EXSyst\Component\IO\Reader\CDataReader;

class Range
{
    /**
     * @var float|null
     */
    private $min;
    /**
     * @var float|null
     */
    private $max;
    /**
     * @var bool
     */
    private $inverted;

    /**
     * @param float|null $min
     * @param float|null $max
     * @param bool       $inverted
     */
    public function __construct($min, $max, $inverted = false)
    {
        $this->min = ($min === null) ? null : floatval($min);
        $this->max = ($max === null) ? null : floatval($max);
        $this->inverted = !!$inverted;
    }

    /**
     * @return float|null
     */
    public function getMin()
    {
        return $this->min;
    }

    /**
     * @return float|null
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * @return bool
     */
    public function getInverted()
    {
        return $this->inverted;
    }

    /**
     * @return bool
     */
    public function isInverted()
    {
        return $this->inverted;
    }

    /**
     * @param float $value
     *
     * @return bool
     */
    public function contains($value)
    {
        return ($this->min !== null && $value < $this->min || $this->max !== null && $value > $this->max) === $this->inverted;
    }

    /**
     * @param string $str
     *
     * @return static
     */
    public static function fromString($str)
    {
        $src = CDataReader::fromString($str);
        $inverted = $src->eat('@');
        $num = $src->eatCSpan(':');
        if (empty($num) || $num == '~') {
            $num = null;
        } else {
            $num = floatval($num);
        }
        if ($src->eat(':')) {
            $max = $src->eatToFullConsumption();
            if (empty($max)) {
                $max = null;
            } else {
                $max = floatval($max);
            }
            $min = $num;
        } else {
            $min = 0;
            $max = $num;
        }

        return new static($min, $max, $inverted);
    }

    /**
     * @return string This object's string representation.
     */
    public function __toString()
    {
        return ($this->inverted ? '@' : '').(($this->min == 0 && $this->min !== null) ? '' : ((($this->min === null) ? '~' : $this->min).':')).(($this->max !== null) ? $this->max : '');
    }
}
