<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Export;
use Aventus\Laraventus\Controllers\ModelController;
use Aventus\Laraventus\Models\AventusModel;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPType;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Parser\Parser;
use Aventus\Transpiler\Parser\PHPClassPropriete;
use Aventus\Transpiler\Parser\PHPEnum;
use Aventus\Transpiler\Tools\Type;
use Aventus\Transpiler\Writer\FileWriterHelper;

class HttpControllerContainer extends BaseContainer
{
    public static function is(PHPSymbol $symbol, string $fileName): BaseContainer|null
    {
        $result = null;

        if ($symbol instanceof PHPClass && $symbol->isController) {
            $result = new HttpControllerContainer($symbol);
        }

        return $result;
    }

    /** @var HttpRouteContainer[] $routes */
    private array $routes = [];
    public ?string $prefix = null;
    /** @var array<string, string|string[]> */
    public array $additionalFcts = [];

    public ?string $typeRequest;
    public ?string $typeResource;

    public function __construct(
        PHPClass $class
    ) {
        parent::__construct($class);

        $this->additionalFcts = $class->additionalFcts;
        foreach ($class->methods as $method) {
            $route = new HttpRouteContainer($method, $this);
            if ($route->canBeAdded) {
                $this->routes[] = $route;
            }
        }
    }


    protected function writeAction(): string
    {


        $result = [];
        if (ProjectConfig::$config->useNamespace && strlen($this->namespace) > 0) {
            $this->addIndent();
        }

        $documentation = $this->getDocumentation($this->symbol);
        if (strlen($documentation) > 0) {
            $result[] = $documentation;
        }
        $this->addTxtOpen($this->getAccessibilityExport($this->symbol) . $this->getAbstract() . "class " . $this->getName() . " " . $this->getExtension() . "{", $result);
        if ($this->prefix != null) {
            $this->addTxt("public override getPrefix(): string { return \"" . $this->prefix . "\"; }", $result);
        }

        if ($this->symbol instanceof PHPClass && $this->symbol->extends(ModelController::class) && $this->symbol->parent != null) {
            $type = $this->symbol->parent->type;
            if (count($type->generics) > 1) {
                $name = $this->getTypeName($type->generics[1]);
                $this->addTxt("/** @inheritdoc */", $result);
                $this->addTxt("public override getRequest(): new () => " . $name . " { return " . $name . "; }", $result);
            }
            if (count($type->generics) > 2) {
                $name = $this->getTypeName($type->generics[2]);
                $this->addTxt("/** @inheritdoc */", $result);
                $this->addTxt("public override getResource(): new () => " . $name . " { return " . $name . "; }", $result);
            }
            if (count($type->generics) > 3) {
                $name = $this->getTypeName($type->generics[3]);
                $this->addTxt("/** @inheritdoc */", $result);
                $this->addTxt("public override getResourceDetails(): new () => " . $name . " { return " . $name . "; }", $result);
            }
        }



        $routerConfig = ProjectConfig::$config->httpRouter;
        if ($routerConfig->createRouter) {
            $this->addTxtOpen("public constructor(router?: Aventus.HttpRouter) {", $result);
            $this->addTxt("super(router ?? new " . $routerConfig->routerName . "());", $result);
            $this->addTxtClose("}", $result);
            $outputPath = ProjectConfig::$config->outputPath . DIRECTORY_SEPARATOR . $routerConfig->routerName . ".lib.avt";
            $file = ProjectConfig::absoluteUrl($outputPath);
            $this->addImport($file, $routerConfig->routerName);
        }


        $result[] = $this->getContent();
        $this->addTxtClose("}", $result);
        if (ProjectConfig::$config->useNamespace && strlen($this->namespace) > 0) {
            $this->removeIndent();
        }

        return implode("\r\n", $result);
    }

    private function getAbstract(): string
    {
        return $this->symbol->isAbstract ? "abstract " : "";
    }

    private function getExtension(): string
    {
        $extend = "Aventus.HttpRoute";
        if ($this->symbol instanceof PHPClass && $this->symbol->parent != null) {
            $extend = $this->getTypeName($this->symbol->parent->type);
        }

        $txt = "extends " . $extend . " ";

        return $txt;
    }

    private function getContent(): string
    {
        $result = [];
        /** @var array<string, string|string[]> */
        $functionNeeded = [];

        foreach ($this->routes as $route) {
            $result[] = $route->write();

            foreach ($route->functionNeeded as $key => $fct) {
                if (!array_key_exists($key, $functionNeeded)) {
                    $functionNeeded[$key] = $fct;
                }
            }
        }
        foreach ($this->additionalFcts as $key => $fct) {
            if (!array_key_exists($key, $functionNeeded)) {
                $functionNeeded[$key] = $fct;
            }
        }
        foreach ($functionNeeded as $key => $fct) {
            if (is_array($fct)) {
                $temp = [];
                foreach ($fct as $f) {
                    $this->addTxt($f, $temp);
                }
                $result[] = implode("\r\n", $temp);
            } else {
                $this->addTxt($fct, $result);
            }
        }

        return implode("\r\n\r\n", $result);
    }

    protected function customReplacer(?PHPType $symbol, string $fullname, ?string $result): ?string
    {
        return $this->applyReplacer(ProjectConfig::$config->replacer->httpRouter, $fullname, $result);
    }
}
