<?php

namespace EXSyst\Component\Worker\Status;

use EXSyst\Component\IO\Reader\CDataReader;

class Range
{
    private $min;
    private $max;
    private $inverted;

    public function __construct($min, $max, $inverted = false)
    {
        $this->min = ($min === null) ? null : floatval($min);
        $this->max = ($max === null) ? null : floatval($max);
        $this->inverted = !!$inverted;
    }

    public function getMin()
    {
        return $this->min;
    }

    public function getMax()
    {
        return $this->max;
    }

    public function getInverted()
    {
        return $this->inverted;
    }

    public function isInverted()
    {
        return $this->inverted;
    }

    public function contains($value)
    {
        return ($this->min !== null && $value < $this->min || $this->max !== null && $value > $this->max) === $this->inverted;
    }

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

    public function __toString()
    {
        return ($this->inverted ? '@' : '').(($this->min == 0 && $this->min !== null) ? '' : ((($this->min === null) ? '~' : $this->min).':')).(($this->max !== null) ? $this->max : '');
    }
}
