<?php

namespace EXSyst\Component\Worker\Status;

class WorkerCounter
{
    private $name;
    private $value;
    private $unit;
    private $min;
    private $max;

    public function __construct($name, $value, $unit = null, $min = null, $max = null)
    {
        $this->name = ($name === null) ? null : strval($name);
        $this->value = ($value === null) ? null : floatval($value);
        $this->unit = ($unit === null) ? null : strval($unit);
        $this->min = ($min === null) ? null : floatval($min);
        $this->max = ($max === null) ? null : floatval($max);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getUnit()
    {
        return $this->unit;
    }

    public function getMin()
    {
        return $this->min;
    }

    public function getMax()
    {
        return $this->max;
    }

    public function toArray()
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'unit' => $this->unit,
            'min' => $this->min,
            'max' => $this->max,
        ];
    }

    public static function fromArrayOrObject($arrayOrObject)
    {
        if (is_array($arrayOrObject)) {
            return new static(
                isset($arrayOrObject['name']) ? $arrayOrObject['name'] : null,
                isset($arrayOrObject['value']) ? $arrayOrObject['value'] : null,
                isset($arrayOrObject['unit']) ? $arrayOrObject['unit'] : null,
                isset($arrayOrObject['min']) ? $arrayOrObject['min'] : null,
                isset($arrayOrObject['max']) ? $arrayOrObject['max'] : null);
        } elseif (is_object($arrayOrObject)) {
            return new static(
                isset($arrayOrObject->name) ? $arrayOrObject->name : null,
                isset($arrayOrObject->value) ? $arrayOrObject->value : null,
                isset($arrayOrObject->unit) ? $arrayOrObject->unit : null,
                isset($arrayOrObject->min) ? $arrayOrObject->min : null,
                isset($arrayOrObject->max) ? $arrayOrObject->max : null);
        }
    }
}
