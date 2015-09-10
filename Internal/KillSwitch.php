<?php

namespace EXSyst\Component\Worker\Internal;

use stdClass;
use EXSyst\Component\Worker\Exception;

class KillSwitch
{
    /**
     * @var string
     */
    private $path;
    /**
     * @var stdClass
     */
    private $data;

    public function __construct($path)
    {
        $this->path = $path;
        $data = @json_decode(file_get_contents($path));
        if (!is_object($data)) {
            $data = new stdClass();
        }
        if (!isset($data->global)) {
            $data->global = false;
        }
        if (!isset($data->addresses) || !is_array($data->addresses)) {
            $data->addresses = [];
        }
        $this->data = $data;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function save()
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (file_put_contents($this->path, json_encode($this->data)) === false) {
            throw new Exception\RuntimeException("Can't save the kill switch file");
        }

        return $this;
    }

    public function setGlobal($global)
    {
        $this->data->global = $global;

        return $this;
    }

    public function getGlobal()
    {
        return $this->data->global;
    }

    public function setAddresses(array $addresses)
    {
        $this->data->addresses = $addresses;

        return $this;
    }

    public function addAddress($address)
    {
        if (!$this->hasAddress($address)) {
            $this->data->addresses[] = $address;
        }

        return $this;
    }

    public function removeAddress($address)
    {
        $key = array_search($address, $this->data->addresses, true);
        if ($key !== false) {
            array_splice($this->data->addresses, $key, 1);
        }

        return $this;
    }

    public function getAddresses()
    {
        return $this->data->addresses;
    }

    public function hasAddress($address)
    {
        return array_search($address, $this->data->addresses, true) !== false;
    }
}
