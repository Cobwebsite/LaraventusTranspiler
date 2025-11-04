<?php

namespace Aventus\Transpiler\Writer;

use Aventus\Laraventus\Attributes\Rename;
use Aventus\Transpiler\Container\BaseContainer;
use Aventus\Transpiler\Container\NormalClassContainer;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Container\EnumContainer;
use Aventus\Transpiler\Container\ErrorContainer;
use Aventus\Transpiler\Container\HttpControllerContainer;
use Aventus\Transpiler\Container\HttpRequestContainer;
use Aventus\Transpiler\Container\HttpResourceContainer;
use Aventus\Transpiler\Container\StorableContainer;
use Aventus\Transpiler\Parser\Parser;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Tools\Path;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Const_;

class FileToWrite
{
    /**
     * @var array<string, FileToWrite>
     */
    private static array $allFiles = [];

    public static function getFileName(PHPSymbol $symbol): ?string
    {
        $fileName = $symbol->uri;
        $fileName = str_replace(ProjectConfig::$configPathDir, "", $fileName);
        $fileName = str_replace(".php", "", $fileName);
        $fileName = rtrim(ProjectConfig::$config->outputPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        $fileName = str_replace("\\", DIRECTORY_SEPARATOR, $fileName);
        $fileName = str_replace("/", DIRECTORY_SEPARATOR, $fileName);
        $fileName = realpath_safe($fileName);
        return $fileName;
    }

    public static function registerType(PHPSymbol $symbol)
    {
        $fileName = self::getFileName($symbol);
        if ($fileName == null) {
            return;
        }
        $containers = [
            EnumContainer::class,
            StorableContainer::class,
            HttpControllerContainer::class,
            HttpRequestContainer::class,
            HttpResourceContainer::class,
            ErrorContainer::class,
            NormalClassContainer::class,
        ];
        foreach ($containers as $container) {
            if (!Parser::$config->isInternal($symbol->getFullname())) {
                continue;
            }
            $result = $container::is($symbol, $fileName);
            if ($result !== null) {
                // Console::log("register " . $symbol->name . " as " . $result::class);
                self::addBaseContainer($result, $fileName);
                break;
            }
        }
    }
    public static function addBaseContainer(?BaseContainer $result, string $fileName)
    {
        if ($result != null) {
            if (!array_key_exists($fileName, self::$allFiles)) {
                self::$allFiles[$fileName] = new FileToWrite($fileName);
            }
            self::$allFiles[$fileName]->addContainer($result);
        }
    }


    public static function writeAll()
    {
        self::addOthersFilesBeforeWrite();
        foreach (self::$allFiles as $path => $file) {
            foreach ($file->types as $container) {
                $container->write();
            }
        }
        foreach (self::$allFiles as $path => $file) {
            $file->resolve();
            $file->write();
        }
        self::addOthersFiles();
    }
    private static function addOthersFilesBeforeWrite()
    {
        self::addRouterFile();
    }
    private static function addOthersFiles() {}

    private static function addRouterFile()
    {
        $fileWriter = new FileWriterHelper();

        $routerConfig = ProjectConfig::$config->httpRouter;
        if (!$routerConfig->createRouter) {
            return;
        }

        $outputPath = ProjectConfig::$config->outputPath . DIRECTORY_SEPARATOR . $routerConfig->routerName . ".lib.avt";
        if (!strlen($routerConfig->parentFile) > 0 && !strlen($routerConfig->parent) > 0) {
            $file = ProjectConfig::absoluteUrl($routerConfig->parentFile);
            $relativePath = Path::getRelativePath($outputPath, $file);
            $importFile = $relativePath + ".lib.avt";
            $fileWriter->addTxt("import { " . $routerConfig->parent . " } from \"" . $importFile . "\" ");
        }

        if (!strlen($routerConfig->namespace) > 0) {
            $fileWriter->addTxtOpen("namespace " + $routerConfig->namespace + " {");
        }


        $host = $routerConfig->host ?? "location.protocol + \"//\" + location.host";
        $host .= " + \"" . $routerConfig->uri . "\"";

        $fileWriter->addTxtOpen("export class " . $routerConfig->routerName . " extends " . $routerConfig->parent . " {");
        $fileWriter->addTxtOpen("protected override defineOptions(options: Aventus.HttpRouterOptions): Aventus.HttpRouterOptions {");
        $fileWriter->addTxt("options.url = " . $host . ";");
        $fileWriter->addTxt("return options;");
        $fileWriter->addTxtClose("}");
        $fileWriter->addTxtClose("}");

        if (!strlen($routerConfig->namespace) > 0) {
            $fileWriter->addTxtClose("}");
        }


        $dirName = dirname($outputPath);
        if ($dirName != null) {
            if (!is_dir($dirName)) {
                mkdir($dirName, 0777, true);
            }
        }
        file_put_contents($outputPath, $fileWriter->getContent());
    }


    /**
     * @var BaseContainer[]
     */
    private array $types = [];
    /**
     * @var array<string, BaseContainer[]>
     */
    private array $namespaces = [];

    /**
     * @var array<string, string[]>
     */
    private array $importByPath = [];

    public function __construct(
        private string $path
    ) {}


    public function addContainer(BaseContainer $container)
    {
        if ($container->canBeAdded && !in_array($container, $this->types)) {
            $this->types[] = $container;

            $namespace = "";
            if (ProjectConfig::$config->useNamespace) {
                $namespace = $container->namespace;
            }

            if (!array_key_exists($namespace, $this->namespaces)) {
                $this->namespaces[$namespace] = [];
            }
            $this->namespaces[$namespace][] = $container;
        }
    }

    public function resolve()
    {
        foreach ($this->types as $container) {
            $currentFileName = $this->getFileName($container->symbol);
            if ($currentFileName == null) {
                continue;
            }
            foreach ($container->unresolved as $symbol) {
                $fileNameToResolve = $this->getFileName($symbol);
                if ($fileNameToResolve != null && array_key_exists($fileNameToResolve, self::$allFiles)) {
                    $importFile = $fileNameToResolve;
                    if (ProjectConfig::$config->isAventus) {
                        $importFile .= self::$allFiles[$fileNameToResolve]->getExtension();
                    }
                    $relativePath = Path::getRelativePath($currentFileName, $importFile);
                    if (!array_key_exists($relativePath, $this->importByPath)) {
                        $this->importByPath[$relativePath] = [];
                    }
                    $name = $symbol->name;
                    $attr = $symbol->getAttribute(Rename::class);
                    if ($attr != null) {
                        $expr = $attr->args[0]->value;
                        if ($expr instanceof String_) {
                            $name = $expr->value;
                        }
                    }
                    if ($symbol instanceof PHPClass && $symbol->isInterface) {
                        $name = "type " . $name;
                    }

                    if (!in_array($name, $this->importByPath[$relativePath])) {
                        $this->importByPath[$relativePath][] = $name;
                    }
                }
            }

            foreach ($container->importedFiles as $path => $customImports) {
                $relativePath = file_exists($path) ? Path::getRelativePath($currentFileName, $path) : $path;
                if (!array_key_exists($relativePath, $this->importByPath)) {
                    $this->importByPath[$relativePath] = [];
                }

                foreach ($customImports as $customImport) {
                    if (!in_array($customImport, $this->importByPath[$relativePath])) {
                        $this->importByPath[$relativePath][] = $customImport;
                    }
                }
            }
        }
    }

    private function getExtension(): string
    {
        if (!ProjectConfig::$config->isAventus) return ".ts";
        $result = "";
        foreach ($this->types as $type) {
            // if ($type instanceof StorableContainer) {
            //     if ($result == "") {
            //         $result = ".data.avt";
            //     }
            // } else if ($type instanceof EnumContainer) {
            //     continue;
            // } else {
            //     $result = ".lib.avt";
            // }
        }
        if ($result == "") {
            $result = ".lib.avt";
        }
        return $result;
    }
    public function write()
    {
        /** @var string[] */
        $txt = [];
        foreach ($this->importByPath as $path => $imports) {
            $txt[] = "import { " . implode(", ", $imports) . " } from '" . $path . "';";
        }

        if (count($this->importByPath) > 0) {
            $txt[] = "";
        }

        foreach ($this->namespaces as $namespace => $containers) {
            if ($namespace != "") {
                $txt[] = "namespace " . $namespace . " {";
            }

            foreach ($containers as $container) {
                $txt[] = "";
                $txt[] = $container->content;
            }
            $txt[] = "";

            if ($namespace != "") {
                $txt[] = "}";
            }
        }

        if (count($txt) == 0) return;

        $dirName = dirname($this->path);
        if ($dirName != null) {
            if (!is_dir($dirName)) {
                mkdir($dirName, 0777, true);
            }
        }

        $realPath = $this->path . $this->getExtension();

        // Console::log("Writing " . $realPath);
        file_put_contents($realPath, implode("\r\n", $txt));
    }
}
