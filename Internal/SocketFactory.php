<?php

namespace EXSyst\Component\Worker\Internal;

use EXSyst\Component\Worker\Exception;

final class SocketFactory
{
    private function __construct()
    {
    }

    public static function createServerSocket($socketAddress, $socketContext = null)
    {
        set_error_handler(null);
        if ($socketContext !== null) {
            $socket = @stream_socket_server($socketAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $socketContext);
        } else {
            $socket = @stream_socket_server($socketAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        }
        restore_error_handler();
        if ($socket === false) {
            throw new Exception\BindOrListenException($errstr, $errno);
        }

        return $socket;
    }

    public static function createClientSocket($socketAddress, $timeout = null, $socketContext = null)
    {
        if ($timeout === null) {
            $timeout = intval(ini_get('default_socket_timeout'));
        }
        set_error_handler(null);
        if ($socketContext !== null) {
            $socket = @stream_socket_client($socketAddress, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $socket = @stream_socket_client($socketAddress, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        }
        restore_error_handler();
        if ($socket === false) {
            throw new Exception\ConnectException($errstr, $errno);
        }

        return $socket;
    }
}
