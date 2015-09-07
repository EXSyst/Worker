<?php

namespace EXSyst\Component\Worker\Bootstrap;

use Symfony\Component\Process\PhpExecutableFinder;

use EXSyst\Component\IO\Channel\ChannelFactoryInterface;
use EXSyst\Component\IO\Channel\SerializedChannelFactory;

use EXSyst\Component\Worker\Internal\WorkerRunner;

class WorkerBootstrapProfile
{
    /**
     * @var string
     */
    private $phpExecutablePath;
    /**
     * @var array|null
     */
    private $phpArguments;
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
     * @var string
     */
    private $loopExpression;
    /**
     * @var string
     */
    private $socketContextExpression;
    /**
     * @var string
     */
    private $stopCookie;
    /**
     * @var array
     */
    private $precompiledScripts;

    public function __construct($withAutoloader = true)
    {
        $this->phpExecutablePath = null;
        $this->phpArguments = null;
        $this->stage1Parts = [ ];
        $this->scriptsToRequire = $withAutoloader ? [ AutoloaderFinder::findAutoloader() ] : [ ];
        $this->stage2Parts = [ ];
        $this->variableName = 'workerImpl';
        $this->constructorArguments = [ ];
        $this->stage3Parts = [ ];
        $this->channelFactory = SerializedChannelFactory::getInstance();
        $this->loopExpression = null;
        $this->socketContextExpression = null;
        $this->stopCookie = null;
        $this->precompiledScripts = [ ];
    }

    public static function create()
    {
        return new self();
    }

    public function setPhpExecutablePath($phpExecutablePath)
    {
        $this->phpExecutablePath = $phpExecutablePath;
        return $this;
    }

    public function getPhpExecutablePath()
    {
        return $this->phpExecutablePath;
    }

    public function setPhpArguments(array $phpArguments = null)
    {
        $this->phpArguments = $phpArguments;
        return $this;
    }

    public function addPhpArgument($phpArgument)
    {
        if ($this->phpArguments === null)
            $this->phpArguments = [ ];
        $this->phpArguments[] = $phpArgument;
        return $this;
    }

    public function getPhpArguments()
    {
        return $this->phpArguments;
    }

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

    public function setStage1Parts(array $stage1Parts = [ ])
    {
        $this->stage1Parts = $stage1Parts;
        return $this;
    }

    public function addStage1Part($stage1Part)
    {
        $this->stage1Parts[] = $stage1Part;
        return $this;
    }

    public function addStage1GlobalVariableWithExpression($name, $expr)
    {
        return $this->addStage1Part('$' . $name . ' = ' . $expr . ';');
    }

    public function addStage1GlobalVariableWithValue($name, $value)
    {
        return $this->addStage1GlobalVariableWithExpression($name, self::exportPHPValue($value));
    }

    public function getStage1Parts()
    {
        return $this->stage1Parts;
    }

    public function setScriptsToRequire(array $scriptsToRequire = [ ])
    {
        $this->scriptsToRequire = $scriptsToRequire;
        return $this;
    }

    public function addScriptToRequire($scriptToRequire)
    {
        $this->scriptsToRequire[] = $scriptToRequire;
        return $this;
    }

    public function getScriptsToRequire()
    {
        return $this->scriptsToRequire;
    }

    public function setStage2Parts(array $stage2Parts = [ ])
    {
        $this->stage2Parts = $stage2Parts;
        return $this;
    }

    public function addStage2Part($stage2Part)
    {
        $this->stage2Parts[] = $stage2Part;
        return $this;
    }

    public function addStage2GlobalVariableWithExpression($name, $expr)
    {
        return $this->addStage2Part('$' . $name . ' = ' . $expr . ';');
    }

    public function addStage2GlobalVariableWithValue($name, $value)
    {
        return $this->addStage2GlobalVariableWithExpression($name, self::exportPHPValue($value));
    }

    public function getStage2Parts()
    {
        return $this->stage2Parts;
    }

    public function setVariableName($variableName)
    {
        $this->variableName = $variableName;
        return $this;
    }

    public function getVariableName()
    {
        return $this->variableName;
    }

    public function setConstructorArguments(array $constructorArguments = [ ])
    {
        $this->constructorArguments = $constructorArguments;
        return $this;
    }

    public function addConstructorArgumentWithExpression($expression)
    {
        $this->constructorArguments[] = $expression;
        return $this;
    }

    public function addConstructorArgumentWithValue($name, $value)
    {
        return $this->addConstructorArgumentWithExpression(self::exportPHPValue($value));
    }

    public function getConstructorArguments()
    {
        return $this->constructorArguments;
    }

    public function setStage3Parts(array $stage3Parts = [ ])
    {
        $this->stage3Parts = $stage3Parts;
        return $this;
    }

    public function addStage3Part($stage3Part)
    {
        $this->stage3Parts[] = $stage3Part;
        return $this;
    }

    public function addStage3GlobalVariableWithExpression($name, $expr)
    {
        return $this->addStage3Part('$' . $name . ' = ' . $expr . ';');
    }

    public function addStage3GlobalVariableWithValue($name, $value)
    {
        return $this->addStage3GlobalVariableWithExpression($name, self::exportPHPValue($value));
    }

    public function getStage3Parts()
    {
        return $this->stage3Parts;
    }

    public function setChannelFactory(ChannelFactoryInterface $channelFactory)
    {
        $this->channelFactory = $channelFactory;
        return $this;
    }

    public function getChannelFactory()
    {
        return $this->channelFactory;
    }

    public function setLoopExpression($loopExpression)
    {
        $this->loopExpression = $loopExpression;
        return $this;
    }

    public function getLoopExpression()
    {
        return $this->loopExpression;
    }

    public function setSocketContextExpression($socketContextExpression)
    {
        $this->socketContextExpression = $socketContextExpression;
        return $this;
    }

    public function getSocketContextExpression()
    {
        return $this->socketContextExpression;
    }

    public function setStopCookie($stopCookie)
    {
        $this->stopCookie = $stopCookie;
        return $this;
    }

    public function getStopCookie()
    {
        return $this->stopCookie;
    }

    public function setPrecompiledScripts(array $precompiledScripts)
    {
        $this->precompiledScripts = $precompiledScripts;
        return $this;
    }

    public function addPrecompiledScript($className, $scriptPath, $socketAddress = null)
    {
        return $this->addPrecompiledScriptWithExpression($this->generateExpression($className), $scriptPath, $socketAddress);
    }

    public function addPrecompiledScriptWithExpression($expression, $scriptPath, $socketAddress = null)
    {
        if ($socketAddress !== null) {
            $expression .= '/*' . $socketAddress . '*/';
        }
        $this->precompiledScripts[$expression] = $scriptPath;
        return $this;
    }

    public function getPrecompiledScripts()
    {
        return $this->precompiledScripts;
    }

    public function getPrecompiledScript($className, $socketAddress = null)
    {
        return $this->getPrecompiledScriptWithExpression($this->generateExpression($className), $socketAddress);
    }

    public function getPrecompiledScriptWithExpression($expression, $socketAddress = null)
    {
        if ($socketAddress !== null) {
            $expression .= '/*' . $socketAddress . '*/';
        }
        return isset($this->precompiledScripts[$expression]) ? $this->precompiledScripts[$expression] : null;
    }

    public function compileScript($className, $socketAddress, &$scriptPath, &$mustDeleteScriptOnError)
    {
        return $this->compileScriptWithExpression($this->generateExpression($className), $socketAddress, $scriptPath, $mustDeleteScriptOnError);
    }

    public function compileScriptWithExpression($expression, $socketAddress, &$scriptPath, &$mustDeleteScriptOnError)
    {
        $scriptPath = $this->getPrecompiledScriptWithExpression($implementationExpression, $socketAddress);
        if ($scriptPath === null) {
            $mustDeleteScriptOnError = true;
            $scriptPath = tempnam(sys_get_temp_dir(), 'xsW');
            file_put_contents($scriptPath, $this->generateScriptWithExpression($implementationExpression, $socketAddress));
        } else {
            $mustDeleteScriptOnError = false;
            if (!file_exists($scriptPath)) {
                file_put_contents($scriptPath, $this->generateScriptWithExpression($implementationExpression, $socketAddress));
            }
        }
        return $this;
    }

    public function generateExpression($className)
    {
        return 'new ' . $className . '(' . implode(', ', $this->constructorArguments) . ')';
    }

    public function generateScript($className, $socketAddress = null)
    {
        return $this->generateScriptWithExpression($this->generateExpression($className), $socketAddress);
    }

    public function generateScriptWithExpression($expression, $socketAddress = null)
    {
        return '<?php' . PHP_EOL .
            'set_time_limit(0);' . PHP_EOL .
            (isset($this->precompiledScripts[$expression]) ? ('unlink(__FILE__);' . PHP_EOL) : '') .
            implode(array_map(function ($part) {
                return $part . PHP_EOL;
            }, array_filter($this->stage1Parts))) .
            implode(array_map(function ($script) {
                return 'require_once ' . self::exportPHPValue($script) . ';' . PHP_EOL;
            }, array_filter($this->scriptsToRequire))) .
            implode(array_map(function ($part) {
                return $part . PHP_EOL;
            }, array_filter($this->stage2Parts))) .
            '$' . $this->variableName . ' = ' . $expression . ';' . PHP_EOL .
            implode(array_map(function ($part) {
                return $part . PHP_EOL;
            }, array_filter($this->stage3Parts))) .
            WorkerRunner::class . '::setChannelFactory(' . self::exportPHPValue($this->channelFactory) . ');' . PHP_EOL .
            (($this->loopExpression !== null) ? (WorkerRunner::class . '::setLoop(' . $this->loopExpression . ');' . PHP_EOL) : '') .
            (($this->socketContextExpression !== null) ? (WorkerRunner::class . '::setSocketContext(' . $this->socketContextExpression . ');' . PHP_EOL) : '') .
            (($this->stopCookie !== null) ? (WorkerRunner::class . '::setStopCookie(' . self::exportPHPValue($this->stopCookie) . ');' . PHP_EOL) : '') .
            (($socketAddress === null)
                ? (WorkerRunner::class . '::runDedicatedWorker($' . $this->variableName . ');')
                : (WorkerRunner::class . '::runSharedWorker($' . $this->variableName . ', ' . self::exportPHPValue($socketAddress) . ');'));
    }

    public static function exportPHPValue($value)
    {
        switch (gettype($value)) {
            case "boolean":
            case "integer":
            case "double":
            case "string":
            case "NULL":
                return var_export($value, true);
            default:
                return 'unserialize(' . var_export(serialize($value), true) . ')';
        }
    }
}
