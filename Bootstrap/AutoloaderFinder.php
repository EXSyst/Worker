<?php

namespace EXSyst\Component\Worker\Bootstrap;

final class AutoloaderFinder
{
    /**
     * @var false|string|null
     */
    private static $autoloader = false;

    private function __construct()
    {
    }

    /**
     * @return string|null
     */
    public static function findAutoloader()
    {
        if (self::$autoloader === false) {
            do {
                $loader = self::push(
                    self::check(
                        self::pop(self::pop(
                            self::pop(__DIR__, 'Bootstrap'),
                            'worker'), 'exsyst'),
                        'vendor'),
                    'autoload.php');
                if ($loader !== null && file_exists($loader)) {
                    self::$autoloader = $loader;
                    break;
                }
                $loader = self::push(self::push(
                    self::pop(__DIR__, 'Bootstrap'),
                    'vendor'), 'autoload.php');
                if ($loader !== null && file_exists($loader)) {
                    self::$autoloader = $loader;
                    break;
                }
                self::$autoloader = null;
            } while (false);
        }

        return self::$autoloader;
    }

    /**
     * @param string|null $directory
     * @param string      $partToAdd
     *
     * @return string|null
     */
    private static function push($directory, $partToAdd)
    {
        if ($directory !== null) {
            return $directory.DIRECTORY_SEPARATOR.$partToAdd;
        }
    }

    /**
     * @param string|null $directory
     * @param string      $partToCheck
     *
     * @return string|null
     */
    private static function pop($directory, $partToCheck)
    {
        if ($directory !== null && basename($directory) == $partToCheck) {
            return dirname($directory);
        }
    }

    /**
     * @param string|null $directory
     * @param string      $partToCheck
     *
     * @return string|null
     */
    private static function check($directory, $partToCheck)
    {
        if ($directory !== null && basename($directory) == $partToCheck) {
            return $directory;
        }
    }
}
