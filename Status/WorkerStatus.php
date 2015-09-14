<?php

namespace EXSyst\Component\Worker\Status;

use EXSyst\Component\Worker\Exception;

class WorkerStatus
{
    private $textStatus;
    private $counters;

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

    public function getTextStatus()
    {
        return $this->textStatus;
    }

    public function getCounters()
    {
        return $this->counters;
    }

    public function toArray()
    {
        return [
            'textStatus' => $this->textStatus,
            'counters' => array_map(function (WorkerCounter $counter) {
                return $counter->toArray();
            }),
        ];
    }

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
