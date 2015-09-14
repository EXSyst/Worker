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
            'name'  => $this->name,
            'value' => $this->value,
            'unit'  => $this->unit,
            'min'   => $this->min,
            'max'   => $this->max,
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

    public static function getSystemCounters()
    {
        return [
            self::getWallExecutionTimeCounter(),
            self::getMemoryUsageCounter(),
        ];
    }

    public static function getWallExecutionTimeCounter()
    {
        try {
            $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : null;
        } catch (\Exception $e) {
            $start = null;
        }

        return new self('sys_wall_execution_time', ($start === null) ? null : (microtime(true) - $start), 's', 0);
    }

    public static function getMemoryUsageCounter()
    {
        return new self('sys_memory_usage', memory_get_usage(), 'B', 0, self::getMemoryLimit());
    }

    private static function getMemoryLimit()
    {
        $limit = self::parseIniSize(ini_get('memory_limit'));
        if ($limit < 0) {
            return;
        } else {
            return $limit;
        }
    }

    private static function parseIniSize($size)
    {
        $iSize = intval($size);
        if ($iSize == 0) {
            return 0;
        }
        $size = trim($size);

        return $iSize * self::getSuffixMultiplier($size[strlen($size) - 1]);
    }

    private static function getSuffixMultiplier($suffix)
    {
        switch ($suffix) {
            case 'G':
            case 'g':
                return 1073741824;
            case 'M':
            case 'm':
                return 1048576;
            case 'K':
            case 'k':
                return 1024;
            default:
                return 1;
        }
    }
}
