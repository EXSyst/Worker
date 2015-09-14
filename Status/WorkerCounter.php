<?php

namespace EXSyst\Component\Worker\Status;

class WorkerCounter
{
    /**
     * @var string|null
     */
    private $name;
    /**
     * @var float|null
     */
    private $value;
    /**
     * @var string|null
     */
    private $unit;
    /**
     * @var float|null
     */
    private $min;
    /**
     * @var float|null
     */
    private $max;

    /**
     * @param string|null $name
     * @param float|null  $value
     * @param string|null $unit
     * @param float|null  $min
     * @param float|null  $max
     */
    public function __construct($name, $value, $unit = null, $min = null, $max = null)
    {
        $this->name = ($name === null) ? null : strval($name);
        $this->value = ($value === null) ? null : floatval($value);
        $this->unit = ($unit === null) ? null : strval($unit);
        $this->min = ($min === null) ? null : floatval($min);
        $this->max = ($max === null) ? null : floatval($max);
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return float|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string|null
     */
    public function getUnit()
    {
        return $this->unit;
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
     * @return array
     */
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

    /**
     * @param array|object $arrayOrObject
     *
     * @return static
     */
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

    /**
     * @return array
     */
    public static function getSystemCounters()
    {
        return [
            self::getWallExecutionTimeCounter(),
            self::getMemoryUsageCounter(),
        ];
    }

    /**
     * @return self
     */
    public static function getWallExecutionTimeCounter()
    {
        try {
            $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : null;
        } catch (\Exception $e) {
            $start = null;
        }

        return new self('sys_wall_execution_time', ($start === null) ? null : (microtime(true) - $start), 's', 0);
    }

    /**
     * @return self
     */
    public static function getMemoryUsageCounter()
    {
        return new self('sys_memory_usage', memory_get_usage(), 'B', 0, self::getMemoryLimit());
    }

    /**
     * @return int|null
     */
    private static function getMemoryLimit()
    {
        $limit = self::parseIniSize(ini_get('memory_limit'));
        if ($limit < 0) {
            return;
        } else {
            return $limit;
        }
    }

    /**
     * @param string $size
     *
     * @return int
     */
    private static function parseIniSize($size)
    {
        $iSize = intval($size);
        if ($iSize == 0) {
            return 0;
        }
        $size = trim($size);

        return $iSize * self::getSuffixMultiplier($size[strlen($size) - 1]);
    }

    /**
     * @param string $suffix
     *
     * @return int
     */
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
