<?php

/*
 * This file is part of the Worker package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Component\Worker\Internal;

use EXSyst\Component\Worker\Exception;

final class SocketFactory
{
    private function __construct()
    {
    }

    /**
     * @param string        $socketAddress
     * @param resource|null $socketContext
     *
     * @throws Exception\BindOrListenException
     *
     * @return resource
     */
    private static function doCreateServerSocket($socketAddress, $socketContext = null)
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

    /**
     * @param string        $socketAddress
     * @param resource|null $socketContext
     *
     * @throws Exception\BindOrListenException
     *
     * @return resource
     */
    public static function createServerSocket($socketAddress, $socketContext = null)
    {
        try {
            return self::doCreateServerSocket($socketAddress, $socketContext);
        } catch (Exception\BindOrListenException $e) {
            if (($socketFile = IdentificationHelper::getSocketFile($socketAddress)) !== null) {
                try {
                    fclose(self::createClientSocket($socketAddress, 1, $socketContext));
                    // Really in use
                    throw $e;
                } catch (Exception\ConnectException $e2) {
                    // False positive due to a residual socket file
                    unlink($socketFile);

                    return self::doCreateServerSocket($socketAddress, $socketContext);
                }
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param string        $socketAddress
     * @param int|null      $timeout
     * @param resource|null $socketContext
     *
     * @throws Exception\ConnectException
     *
     * @return resource
     */
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
