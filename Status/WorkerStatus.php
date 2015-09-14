<?php

namespace EXSyst\Component\Worker\Status;

use EXSyst\Component\Worker\Exception;

class WorkerStatus
{
    /**
     * @var string|null
     */
    private $textStatus;
    /**
     * @var array
     */
    private $counters;

    /**
     * @param string|null $textStatus
     * @param array       $counters
     */
    public function __construct($textStatus = null, array $counters = [])
    {
        foreach ($counters as $counter) {
            if (!($counter instanceof WorkerCounter)) {
                throw new Exception\InvalidArgumentException('All the counters must be instances of the WorkerCounter class.');
            }
        }
        $this->textStatus = ($textStatus === null) ? null : strval($textStatus);
        $this->counters = $counters;
    }

    /**
     * @return string|null
     */
    public function getTextStatus()
    {
        return $this->textStatus;
    }

    /**
     * @return array
     */
    public function getCounters()
    {
        return $this->counters;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'textStatus' => $this->textStatus,
            'counters'   => array_map(function (WorkerCounter $counter) {
                return $counter->toArray();
            }),
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
                isset($arrayOrObject['textStatus']) ? $arrayOrObject['textStatus'] : null,
                isset($arrayOrObject['counters']) ? array_map(function ($counter) {
                    return WorkerCounter::fromArrayOrObject($counter);
                }, $arrayOrObject['counters']) : []);
        } elseif (is_object($arrayOrObject)) {
            return new static(
                isset($arrayOrObject->textStatus) ? $arrayOrObject->textStatus : null,
                isset($arrayOrObject->counters) ? array_map(function ($counter) {
                    return WorkerCounter::fromArrayOrObject($counter);
                }, $arrayOrObject->counters) : []);
        }
    }
}
