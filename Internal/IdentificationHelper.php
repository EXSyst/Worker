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
use Symfony\Component\Process\ExecutableFinder;

final class IdentificationHelper
{
    private function __construct()
    {
    }

    /**
     * @param string $socketAddress
     *
     * @return bool
     */
    public static function isUnixAddress($socketAddress)
    {
        return substr_compare($socketAddress, 'unix://', 0, 7) === 0;
    }

    /**
     * @param string $socketAddress
     *
     * @return bool
     */
    public static function isLocalAddress($socketAddress)
    {
        static $localAddresses = null;
        if (self::isUnixAddress($socketAddress)) {
            return true;
        }
        if ($localAddresses === null) {
            $localAddresses = array_merge([
                '0.0.0.0',
                '127.0.0.1',
                '[::]',
                '[::1]',
            ], gethostbynamel(gethostname()));
        }
        foreach ($localAddresses as $address) {
            if (strpos($socketAddress, $address) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $socketAddress
     *
     * @return bool
     */
    public static function isNetworkExposedAddress($socketAddress)
    {
        if (self::isUnixAddress($socketAddress)) {
            return false;
        }
        if (strpos($socketAddress, '127.0.0.1') !== false || strpos($socketAddress, '[::1]') !== false) {
            return false;
        }

        return true;
    }

    /**
     * @param string $socketAddress
     *
     * @return string|null
     */
    public static function getSocketFile($socketAddress)
    {
        return self::isUnixAddress($socketAddress) ? substr($socketAddress, 7) : null;
    }

    /**
     * @param string $socketAddress
     *
     * @return string
     */
    public static function stripScheme($socketAddress)
    {
        $pos = strpos($socketAddress, '://');

        return ($pos === false) ? $socketAddress : substr($socketAddress, $pos + 3);
    }

    /**
     * @param string $socketAddress
     *
     * @throws Exception\RuntimeException
     *
     * @return int|null
     */
    public static function getListeningProcessId($socketAddress)
    {
        if (!self::isLocalAddress($socketAddress)) {
            return;
        }
        $lsofPath = self::findLsof();
        $unix = self::isUnixAddress($socketAddress);
        $lsofArgs = self::buildLsofArgs($socketAddress, $unix);
        exec(escapeshellarg($lsofPath).' '.$lsofArgs.' 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 || $output === null) {
            return;
        }
        if ($unix) {
            return self::findProcessIdFromLsofOutput($output);
        } else {
            return self::findListeningProcessIdFromLsofOutput($output);
        }
    }

    /**
     * @param bool $includeStdIO
     *
     * @throws Exception\RuntimeException
     *
     * @return array
     */
    public static function getMyFileDescriptors($includeStdIO = true)
    {
        $lsofPath = self::findLsof();
        exec(escapeshellarg($lsofPath).' -F f -p '.getmypid(), $output, $exitCode);
        if ($exitCode !== 0 || $output === null) {
            return [];
        }
        $output = array_map('trim', $output);
        $output = array_values(array_filter($output, function ($line) {
            return preg_match('#^f\\d+$#', $line);
        }));
        $fds = array_map(function ($line) {
            return intval(substr($line, 1));
        }, $output);
        sort($fds);
        if (!$includeStdIO) {
            $fds = self::removeStdIO($fds);
        }

        return $fds;
    }

    /**
     * @throws Exception\RuntimeException
     *
     * @return string
     */
    private static function findLsof()
    {
        $finder = new ExecutableFinder();
        $lsofPath = $finder->find('lsof', null, ['/sbin', '/usr/sbin']);
        if ($lsofPath === null) {
            throw new Exception\RuntimeException('Unable to find the "lsof" executable.');
        }

        return $lsofPath;
    }

    /**
     * @param string $socketAddress
     * @param bool   $unix
     *
     * @return string
     */
    private static function buildLsofArgs($socketAddress, $unix)
    {
        $schemeless = self::stripScheme($socketAddress);

        return $unix ? ('-F p0 '.escapeshellarg($schemeless)) : ('-F pT0 -i tcp@'.escapeshellarg($schemeless));
    }

    /**
     * @param array $output
     *
     * @return int|null
     */
    private static function findProcessIdFromLsofOutput(array $output)
    {
        foreach ($output as $line) {
            if (substr_compare($line, 'p', 0, 1) === 0) {
                return intval(substr($line, 1));
            }
        }
    }

    /**
     * @param array $output
     *
     * @return int|null
     */
    private static function findListeningProcessIdFromLsofOutput(array $output)
    {
        $pid = null;
        foreach ($output as $line) {
            if (substr_compare($line, 'p', 0, 1) === 0) {
                $pid = intval(substr($line, 1));
            }
            $record = explode("\0", $line);
            if (in_array('TST=LISTEN', $record)) {
                return $pid;
            }
        }
    }

    /**
     * @param array $fds
     *
     * @return array
     */
    private static function removeStdIO(array $fds)
    {
        foreach ($fds as $i => $fd) {
            if ($fd > 2) {
                return array_slice($fds, $i);
            }
        }

        return $fds;
    }
}
