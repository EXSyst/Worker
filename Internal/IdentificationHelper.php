<?php

namespace EXSyst\Component\Worker\Internal;

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
        if (self::isUnixAddress($socketAddress)) {
            return true;
        }
        $localAddresses = array_merge([
            '0.0.0.0',
            '127.0.0.1',
            '[::]',
            '[::1]',
        ], gethostbynamel(gethostname()));
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
        $unix = self::isUnixAddress($socketAddress);
        $schemeless = self::stripScheme($socketAddress);
        $lsofArgs = $unix ? ('-F p0 '.escapeshellarg($schemeless)) : ('-F pT0 -i tcp@'.escapeshellarg($schemeless));
        exec('lsof '.$lsofArgs, $output, $exitCode);
        if ($exitCode !== 0 || $output === null) {
            return;
        }
        if ($unix) {
            return self::findProcessIdFromLsofOutput($output);
        } else {
            return self::findListeningProcessIdFromLsofOutput($output);
        }
    }

    private static function findProcessIdFromLsofOutput($output)
    {
        foreach ($output as $line) {
            if (substr_compare($line, 'p', 0, 1) === 0) {
                return intval(substr($line, 1));
            }
        }
    }

    private static function findListeningProcessIdFromLsofOutput($output)
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