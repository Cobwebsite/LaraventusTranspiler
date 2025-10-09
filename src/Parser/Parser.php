<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Config\ComposerConfig;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Tools\Type;
use DirectoryIterator;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\Parser as RealParser;

class Parser
{
    public static ComposerConfig $config;
    private RealParser $parser;

    public function __construct(
        private string $directory
    ) {
        $factory = new ParserFactory();
        $this->parser = $factory->createForVersion(PhpVersion::fromString("8.3"));

        if (is_file($this->directory)) {
            $this->directory = dirname($this->directory);
        }

        if (!str_ends_with($this->directory, "/") || !str_ends_with($this->directory, "\\")) {
            $this->directory = $this->directory . DIRECTORY_SEPARATOR;
        }
    }


    public function parse()
    {
        $config = ComposerConfig::init($this->directory);
        ProjectConfig::init($this->directory);
        if ($config == null)
            return;
        if (ProjectConfig::$config == null)
            return;
        self::$config = $config;

        $this->_parseClasses($this->directory);
        $aventusVendor = $this->directory . "vendor" . DIRECTORY_SEPARATOR . "aventus" . DIRECTORY_SEPARATOR . "laraventus";
        if (realpath($aventusVendor) !== false) {
            $this->_parseClasses($aventusVendor);
        }

        ClassManager::getInstance()->completeSymbols();
        $this->_parseRoute($this->directory);
        ClassManager::getInstance()->symbolsToContainers();
    }

    private function _parseClasses($directory)
    {
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isDot())
                continue;

            if ($file->isFile()) {
                if ($file->getExtension() === 'php') {
                    ClassManager::getInstance()->quickLoadSymbol($file->getPathname());
                }
            } else {
                if (str_ends_with($file->getPathname(), "vendor"))
                    continue;
                if (str_ends_with($file->getPathname(), ".git"))
                    continue;
                $this->_parseClasses($file->getPathname());
            }
        }
    }

    private function _parseRoute($directory)
    {
        $appUri = $directory . '/bootstrap/app.php';
        if (realpath($appUri) === false) {
            // not a laravel app
            return;
        }
        $autoloadUri = $directory . '/vendor/autoload.php';
        if (realpath($autoloadUri) === false) {
            error($autoloadUri . ' doesn\'t exist');
            return;
        }
        require $directory . '/vendor/autoload.php';
        $app = require_once $directory . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $action = $route->getActionName();
            if (str_contains($action, "@")) {
                // c'est un controller
                $exploded = explode("@", $action);
                $className = $exploded[0];
                $fct = $exploded[1];
                $class = ClassManager::getInstance()->loadSymbolByFullname($className);

                if ($class instanceof PHPClass) {
                    $methodInfo = $class->getMethod($fct);
                    if (!isset($methodInfo) || $methodInfo == null) {
                        continue;
                    }
                    if (!Type::exportToTypesript($class, ProjectConfig::$config->exportHttpRouteByDefault)) {
                        continue;
                    }
                    if (!Type::exportToTypesript($methodInfo, ProjectConfig::$config->exportHttpRouteByDefault)) {
                        continue;
                    }
                    $class->isController = true;

                    $methodInfo->isExported = true;
                    $methods = [];
                    foreach ($route->methods() as $method) {
                        if ($method != "HEAD") {
                            $methods[] = $method;
                        }
                    }
                    $_parameters = $route->parameterNames();
                    $wheres = $route->wheres;
                    $parameters = [];
                    foreach ($_parameters as $_param) {
                        $type = "string | number";
                        if (array_key_exists($_param, $wheres)) {
                            $exp = $wheres[$_param];
                            if ($exp == "[0-9]+") {
                                $type = "number";
                            } else if (str_contains($exp, "|")) {
                                $type = '"' . implode('" | "', explode("|", $exp)) . '"';
                            }
                        }
                        $parameters[$_param] = $type;
                    }

                    $uri = $route->uri();
                    if ($class->getMethod("overrideUri") != null) {
                        $classDone = $app->make($class->getFullname());
                        $overrideUri = $classDone->overrideUri($uri);
                        $uri = $overrideUri[0];
                        $class->additionalFcts["getUri"] = ['/** @inheritdoc */', 'public override getUri(): string { return "' . $overrideUri[1] . '"; }'];
                    }
                    $methodInfo->ctrlInfo[] = new PHPClassMethodCtrlInfo(
                        $uri,
                        $methods,
                        $parameters
                    );
                }
            }
        }
    }
}
