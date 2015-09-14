<?php

namespace EXSyst\Component\Worker\Internal;

use EXSyst\Component\Worker\Exception;
use stdClass;

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

    /**
     * @param string $path
     */
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

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @throws Exception\RuntimeException
     *
     * @return $this
     */
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

    /**
     * @param bool $global
     *
     * @return $this
     */
    public function setGlobal($global)
    {
        $this->data->global = $global;

        return $this;
    }

    /**
     * @return bool
     */
    public function getGlobal()
    {
        return $this->data->global;
    }

    /**
     * @param array $addresses
     *
     * @return $this
     */
    public function setAddresses(array $addresses)
    {
        $this->data->addresses = $addresses;

        return $this;
    }

    /**
     * @param string $address
     *
     * @return $this
     */
    public function addAddress($address)
    {
        if (!$this->hasAddress($address)) {
            $this->data->addresses[] = $address;
        }

        return $this;
    }

    /**
     * @param string $address
     *
     * @return $this
     */
    public function removeAddress($address)
    {
        $key = array_search($address, $this->data->addresses, true);
        if ($key !== false) {
            array_splice($this->data->addresses, $key, 1);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getAddresses()
    {
        return $this->data->addresses;
    }

    /**
     * @param string $address
     *
     * @return bool
     */
    public function hasAddress($address)
    {
        return array_search($address, $this->data->addresses, true) !== false;
    }
}
