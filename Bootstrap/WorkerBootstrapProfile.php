<?php

namespace EXSyst\Component\Worker\Bootstrap;

use EXSyst\Component\IO\Channel\ChannelFactoryInterface;
use EXSyst\Component\IO\Channel\SerializedChannelFactory;
use EXSyst\Component\Worker\Exception;
use EXSyst\Component\Worker\Internal\WorkerRunner;
use Symfony\Component\Process\PhpExecutableFinder;

class WorkerBootstrapProfile
{
    /**
     * @var string|null
     */
    private $phpExecutablePath;
    /**
     * @var array|null
     */
    private $phpArguments;
    /**
     * @var string|null
     */
    private $preferredIdentity;
    /**
     * @var array
     */
    private $stage1Parts;
    /**
     * @var array
     */
    private $scriptsToRequire;
    /**
     * @var array
     */
    private $stage2Parts;
    /**
     * @var string
     */
    private $variableName;
    /**
     * @var array
     */
    private $constructorArguments;
    /**
     * @var array
     */
    private $stage3Parts;
    /**
     * @var ChannelFactoryInterface
     */
    private $channelFactory;
    /**
     * @var string|null
     */
    private $loopExpression;
    /**
     * @var string|null
     */
    private $socketContextExpression;
    /**
     * @var string|null
     */
    private $adminCookie;
    /**
     * @var string|null
     */
    private $killSwitchPath;
    /**
     * @var array
     */
    private $precompiledScripts;

    /**
     * @param bool $withAutoloader
     */
    public function __construct($withAutoloader = true)
    {
        $this->phpExecutablePath = null;
        $this->phpArguments = null;
        $this->preferredIdentity = null;
        $this->stage1Parts = [];
        $this->scriptsToRequire = $withAutoloader ? [AutoloaderFinder::findAutoloader()] : [];
        $this->stage2Parts = [];
        $this->variableName = 'workerImpl';
        $this->constructorArguments = [];
        $this->stage3Parts = [];
        $this->channelFactory = SerializedChannelFactory::getInstance();
        $this->loopExpression = null;
        $this->socketContextExpression = null;
        $this->adminCookie = null;
        $this->killSwitchPath = null;
        $this->precompiledScripts = [];
    }

    /**
     * @param bool $withAutoloader
     *
     * @return self
     */
    public static function create($withAutoloader = true)
    {
        return new self($withAutoloader);
    }

    /**
     * @param string|null $phpExecutablePath
     *
     * @return $this
     */
    public function setPhpExecutablePath($phpExecutablePath)
    {
        $this->phpExecutablePath = $phpExecutablePath;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPhpExecutablePath()
    {
        return $this->phpExecutablePath;
    }

    /**
     * @param array|null $phpArguments
     *
     * @return $this
     */
    public function setPhpArguments(array $phpArguments = null)
    {
        $this->phpArguments = $phpArguments;

        return $this;
    }

    /**
     * @param string $phpArgument
     *
     * @return $this
     */
    public function addPhpArgument($phpArgument)
    {
        if ($this->phpArguments === null) {
            $this->phpArguments = [];
        }
        $this->phpArguments[] = $phpArgument;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getPhpArguments()
    {
        return $this->phpArguments;
    }

    /**
     * @param string|null $phpExecutablePath
     * @param array|null  $phpArguments
     *
     * @return $this
     */
    public function getOrFindPhpExecutablePathAndArguments(&$phpExecutablePath, &$phpArguments)
    {
        $phpExecutablePath = $this->phpExecutablePath;
        $phpArguments = $this->phpArguments;
        if ($phpExecutablePath === null || $phpArguments === null) {
            $executableFinder = new PhpExecutableFinder();
            if ($phpExecutablePath === null) {
                $phpExecutablePath = $executableFinder->find(false);
                if ($phpExecutablePath === false) {
                    throw new Exception\RuntimeException('Unable to find the PHP executable.');
                }
            }
            if ($phpArguments === null) {
                $phpArguments = $executableFinder->findArguments();
            }
        }

        return $this;
    }

    /**
     * @param string|null $preferredIdentity
     *
     * @return $this
     */
    public function setPreferredIdentity($preferredIdentity)
    {
        $this->preferredIdentity = $preferredIdentity;

        return $this;
    }

    /**
     * @return string
     */
    public function getPreferredIdentity()
    {
        return $this->preferredIdentity;
    }

    /**
     * @param array $stage1Parts
     *
     * @return $this
     */
    public function setStage1Parts(array $stage1Parts = [])
    {
        $this->stage1Parts = $stage1Parts;

        return $this;
    }

    /**
     * @param string $stage1Part
     *
     * @return $this
     */
    public function addStage1Part($stage1Part)
    {
        $this->stage1Parts[] = $stage1Part;

        return $this;
    }

    /**
     * @param string $name
     * @param string $expr
     *
     * @return $this
     */
    public function addStage1GlobalVariableWithExpression($name, $expr)
    {
        return $this->addStage1Part('$'.$name.' = '.$expr.';');
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function addStage1GlobalVariableWithValue($name, $value)
    {
        return $this->addStage1GlobalVariableWithExpression($name, self::exportPhpValue($value));
    }

    /**
     * @return array
     */
    public function getStage1Parts()
    {
        return $this->stage1Parts;
    }

    /**
     * @param array $scriptsToRequire
     *
     * @return $this
     */
    public function setScriptsToRequire(array $scriptsToRequire = [])
    {
        $this->scriptsToRequire = $scriptsToRequire;

        return $this;
    }

    /**
     * @param string $scriptToRequire
     *
     * @return $this
     */
    public function addScriptToRequire($scriptToRequire)
    {
        $this->scriptsToRequire[] = $scriptToRequire;

        return $this;
    }

    /**
     * @return array
     */
    public function getScriptsToRequire()
    {
        return $this->scriptsToRequire;
    }

    /**
     * @param array $stage2Parts
     *
     * @return $this
     */
    public function setStage2Parts(array $stage2Parts = [])
    {
        $this->stage2Parts = $stage2Parts;

        return $this;
    }

    /**
     * @param string $stage2Part
     *
     * @return $this
     */
    public function addStage2Part($stage2Part)
    {
        $this->stage2Parts[] = $stage2Part;

        return $this;
    }

    /**
     * @param string $name
     * @param string $expr
     *
     * @return $this
     */
    public function addStage2GlobalVariableWithExpression($name, $expr)
    {
        return $this->addStage2Part('$'.$name.' = '.$expr.';');
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function addStage2GlobalVariableWithValue($name, $value)
    {
        return $this->addStage2GlobalVariableWithExpression($name, self::exportPhpValue($value));
    }

    /**
     * @return array
     */
    public function getStage2Parts()
    {
        return $this->stage2Parts;
    }

    /**
     * @param string $variableName
     *
     * @return $this
     */
    public function setVariableName($variableName)
    {
        $this->variableName = $variableName;

        return $this;
    }

    /**
     * @return string
     */
    public function getVariableName()
    {
        return $this->variableName;
    }

    /**
     * @param array $constructorArguments
     *
     * @return $this
     */
    public function setConstructorArguments(array $constructorArguments = [])
    {
        $this->constructorArguments = $constructorArguments;

        return $this;
    }

    /**
     * @param string $expression
     *
     * @return $this
     */
    public function addConstructorArgumentWithExpression($expression)
    {
        $this->constructorArguments[] = $expression;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function addConstructorArgumentWithValue($value)
    {
        return $this->addConstructorArgumentWithExpression(self::exportPhpValue($value));
    }

    /**
     * @return array
     */
    public function getConstructorArguments()
    {
        return $this->constructorArguments;
    }

    /**
     * @param array $stage3Parts
     *
     * @return $this
     */
    public function setStage3Parts(array $stage3Parts = [])
    {
        $this->stage3Parts = $stage3Parts;

        return $this;
    }

    /**
     * @param string $stage3Part
     *
     * @return $this
     */
    public function addStage3Part($stage3Part)
    {
        $this->stage3Parts[] = $stage3Part;

        return $this;
    }

    /**
     * @param string $name
     * @param string $expr
     *
     * @return $this
     */
    public function addStage3GlobalVariableWithExpression($name, $expr)
    {
        return $this->addStage3Part('$'.$name.' = '.$expr.';');
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function addStage3GlobalVariableWithValue($name, $value)
    {
        return $this->addStage3GlobalVariableWithExpression($name, self::exportPhpValue($value));
    }

    /**
     * @return array
     */
    public function getStage3Parts()
    {
        return $this->stage3Parts;
    }

    /**
     * @param ChannelFactoryInterface $channelFactory
     *
     * @return $this
     */
    public function setChannelFactory(ChannelFactoryInterface $channelFactory)
    {
        $this->channelFactory = $channelFactory;

        return $this;
    }

    /**
     * @return ChannelFactoryInterface
     */
    public function getChannelFactory()
    {
        return $this->channelFactory;
    }

    /**
     * @param string|null
     *
     * @return $this
     */
    public function setLoopExpression($loopExpression)
    {
        $this->loopExpression = $loopExpression;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLoopExpression()
    {
        return $this->loopExpression;
    }

    /**
     * @param string|null
     *
     * @return $this
     */
    public function setSocketContextExpression($socketContextExpression)
    {
        $this->socketContextExpression = $socketContextExpression;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSocketContextExpression()
    {
        return $this->socketContextExpression;
    }

    /**
     * @param string|null
     *
     * @return $this
     */
    public function setAdminCookie($adminCookie)
    {
        $this->adminCookie = $adminCookie;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAdminCookie()
    {
        return $this->adminCookie;
    }

    /**
     * @param string|null
     *
     * @return $this
     */
    public function setKillSwitchPath($killSwitchPath)
    {
        $this->killSwitchPath = $killSwitchPath;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getKillSwitchPath()
    {
        return $this->killSwitchPath;
    }

    /**
     * @param array $precompiledScripts
     *
     * @return $this
     */
    public function setPrecompiledScripts(array $precompiledScripts)
    {
        $this->precompiledScripts = $precompiledScripts;

        return $this;
    }

    /**
     * @param string      $className
     * @param string      $scriptPath
     * @param string|null $socketAddress
     *
     * @return $this
     */
    public function addPrecompiledScript($className, $scriptPath, $socketAddress = null)
    {
        return $this->addPrecompiledScriptWithExpression($this->generateExpression($className), $scriptPath, $socketAddress);
    }

    /**
     * @param string      $expression
     * @param string      $scriptPath
     * @param string|null $socketAddress
     *
     * @return $this
     */
    public function addPrecompiledScriptWithExpression($expression, $scriptPath, $socketAddress = null)
    {
        $expression = self::combineExpressionWithSocketAddress($expression, $socketAddress);
        $this->precompiledScripts[$expression] = $scriptPath;

        return $this;
    }

    /**
     * @return array
     */
    public function getPrecompiledScripts()
    {
        return $this->precompiledScripts;
    }

    /**
     * @param string      $className
     * @param string|null $socketAddress
     *
     * @return string|null
     */
    public function getPrecompiledScript($className, $socketAddress = null)
    {
        return $this->getPrecompiledScriptWithExpression($this->generateExpression($className), $socketAddress);
    }

    /**
     * @param string      $expression
     * @param string|null $socketAddress
     *
     * @return string|null
     */
    public function getPrecompiledScriptWithExpression($expression, $socketAddress = null)
    {
        $expression = self::combineExpressionWithSocketAddress($expression, $socketAddress);

        return isset($this->precompiledScripts[$expression]) ? $this->precompiledScripts[$expression] : null;
    }

    /**
     * @param string      $className
     * @param string|null $socketAddress
     * @param string      $scriptPath
     * @param bool        $mustDeleteScriptOnError
     *
     * @return $this
     */
    public function compileScript($className, $socketAddress, &$scriptPath, &$mustDeleteScriptOnError)
    {
        return $this->compileScriptWithExpression($this->generateExpression($className), $socketAddress, $scriptPath, $mustDeleteScriptOnError);
    }

    /**
     * @param string      $expression
     * @param string|null $socketAddress
     * @param string      $scriptPath
     * @param bool        $mustDeleteScriptOnError
     *
     * @return $this
     */
    public function compileScriptWithExpression($expression, $socketAddress, &$scriptPath, &$mustDeleteScriptOnError)
    {
        $scriptPath = $this->getPrecompiledScriptWithExpression($expression, $socketAddress);
        if ($scriptPath === null) {
            $mustDeleteScriptOnError = true;
            $scriptPath = tempnam(sys_get_temp_dir(), 'xsW');
            file_put_contents($scriptPath, $this->generateScriptWithExpression($expression, $socketAddress));
        } else {
            $mustDeleteScriptOnError = false;
            if (!file_exists($scriptPath)) {
                $scriptDir = dirname($scriptPath);
                if (!is_dir($scriptDir)) {
                    mkdir($scriptDir, 0777, true);
                }
                file_put_contents($scriptPath, $this->generateScriptWithExpression($expression, $socketAddress));
            }
        }

        return $this;
    }

    /**
     * @param string $className
     *
     * @return string
     */
    public function generateExpression($className)
    {
        return 'new '.$className.'('.implode(', ', $this->constructorArguments).')';
    }

    /**
     * @param string      $expression
     * @param string|null $socketAddress
     *
     * @return string
     */
    public static function combineExpressionWithSocketAddress($expression, $socketAddress = null)
    {
        if ($socketAddress !== null) {
            $expression .= '/*'.$socketAddress.'*/';
        }

        return $expression;
    }

    /**
     * @param string      $className
     * @param string|null $socketAddress
     *
     * @return string
     */
    public function generateScript($className, $socketAddress = null)
    {
        return $this->generateScriptWithExpression($this->generateExpression($className), $socketAddress);
    }

    /**
     * @param string      $expression
     * @param string|null $socketAddress
     *
     * @throws Exception\LogicException
     *
     * @return string
     */
    public function generateScriptWithExpression($expression, $socketAddress = null)
    {
        return '<?php'.PHP_EOL.
            'set_time_limit(0);'.PHP_EOL.
            (isset($this->precompiledScripts[$this->combineExpressionWithSocketAddress($expression, $socketAddress)]) ? '' : ('unlink(__FILE__);'.PHP_EOL)).
            $this->generatePartForPreferredIdentity().
            implode(array_map(function ($part) {
                return $part.PHP_EOL;
            }, array_filter($this->stage1Parts))).
            implode(array_map(function ($script) {
                return 'require_once '.self::exportPhpValue($script).';'.PHP_EOL;
            }, array_filter($this->scriptsToRequire))).
            implode(array_map(function ($part) {
                return $part.PHP_EOL;
            }, array_filter($this->stage2Parts))).
            '$'.$this->variableName.' = '.$expression.';'.PHP_EOL.
            implode(array_map(function ($part) {
                return $part.PHP_EOL;
            }, array_filter($this->stage3Parts))).
            WorkerRunner::class.'::setChannelFactory('.self::exportPhpValue($this->channelFactory).');'.PHP_EOL.
            (($this->loopExpression !== null) ? (WorkerRunner::class.'::setLoop('.$this->loopExpression.');'.PHP_EOL) : '').
            (($this->socketContextExpression !== null) ? (WorkerRunner::class.'::setSocketContext('.$this->socketContextExpression.');'.PHP_EOL) : '').
            (($this->adminCookie !== null) ? (WorkerRunner::class.'::setAdminCookie('.self::exportPhpValue($this->adminCookie).');'.PHP_EOL) : '').
            (($this->killSwitchPath !== null) ? (WorkerRunner::class.'::setKillSwitchPath('.self::exportPhpValue($this->killSwitchPath).');'.PHP_EOL) : '').
            (($socketAddress === null)
                ? (WorkerRunner::class.'::runDedicatedWorker($'.$this->variableName.');')
                : (WorkerRunner::class.'::runSharedWorker($'.$this->variableName.', '.self::exportPhpValue($socketAddress).');'));
    }

    /**
     * @throws Exception\LogicException
     *
     * @return string
     */
    private function generatePartForPreferredIdentity()
    {
        if ($this->preferredIdentity === null) {
            return '';
        }
        if (!function_exists('posix_getpwnam')) {
            throw new Exception\LogicException('The POSIX extension must be installed to be able to set a preferred identity');
        }
        $pw = posix_getpwnam($this->preferredIdentity);
        if ($pw === false) {
            throw new Exception\LogicException('The preferred identity is one of a non-existent user');
        }

        return 'posix_setgid('.self::exportPhpValue($pw['gid']).');'.PHP_EOL.
            'posix_initgroups('.self::exportPhpValue($pw['name']).', '.self::exportPhpValue($pw['gid']).');'.PHP_EOL.
            'posix_setuid('.self::exportPhpValue($pw['uid']).');'.PHP_EOL;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public static function exportPhpValue($value)
    {
        switch (gettype($value)) {
            case 'boolean':
            case 'integer':
            case 'double':
            case 'string':
            case 'NULL':
                return var_export($value, true);
            default:
                return 'unserialize('.var_export(serialize($value), true).')';
        }
    }
}
