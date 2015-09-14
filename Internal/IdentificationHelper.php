<?php

namespace EXSyst\Component\Worker\Internal;

use Symfony\Component\Process\ExecutableFinder;
use EXSyst\Component\Worker\Exception;

final class IdentificationHelper
{
    private function __construct()
    {
    }

    public static function isUnixAddress($socketAddress)
    {
        return substr_compare($socketAddress, 'unix://', 0, 7) === 0;
    }

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

    public static function getSocketFile($socketAddress)
    {
        return self::isUnixAddress($socketAddress) ? substr($socketAddress, 7) : null;
    }

    public static function stripScheme($socketAddress)
    {
        $pos = strpos($socketAddress, '://');

        return ($pos === false) ? $socketAddress : substr($socketAddress, $pos + 3);
    }

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

    private static function findLsof()
    {
        $finder = new ExecutableFinder();
        $lsofPath = $finder->find('lsof', null, ['/sbin', '/usr/sbin']);
        if ($lsofPath === null) {
            throw new Exception\RuntimeException('Unable to find the "lsof" executable.');
        }

        return $lsofPath;
    }

    private static function buildLsofArgs($socketAddress, $unix)
    {
        $schemeless = self::stripScheme($socketAddress);

        return $unix ? ('-F p0 '.escapeshellarg($schemeless)) : ('-F pT0 -i tcp@'.escapeshellarg($schemeless));
    }

    private static function findProcessIdFromLsofOutput(array $output)
    {
        foreach ($output as $line) {
            if (substr_compare($line, 'p', 0, 1) === 0) {
                return intval(substr($line, 1));
            }
        }
    }

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
}
