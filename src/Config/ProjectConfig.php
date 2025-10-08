<?php

namespace Aventus\Transpiler\Config;

use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use Exception;
use PhpParser\Node\Stmt\Const_;

class ProjectConfig
{
    public static ProjectConfig $config;
    public static string $configPathDir;
    public static function init($path)
    {
        $file = "aventus.php.avt";
        $isAventus = true;
        if (!file_exists($path . $file)) {
            $file = "aventus.php.json";
            $isAventus = false;
            if (!file_exists($path . $file)) {
                error($path . "aventus.php.(avt|json) not found");
                return;
            }
        }

        self::$configPathDir = $path;

        $content = file_get_contents($path . $file);
        self::$config = Type::fromJson(ProjectConfig::class, $content);

        self::$config->isAventus = $isAventus;
        self::$config->prepare();
    }

    public function __construct()
    {
        $this->replacer = new ProjectConfigReplacer();
        $this->httpRouter = new ProjectConfigHttpRouter();
    }

    public bool $useNamespace = true;
    public bool $useFormData = false;
    /** Define if it will compile to aventus or to ts */
    public bool $isAventus = true;
    public bool $exportAsTs = false;

    public ProjectConfigReplacer $replacer;
    public string $output  = "";
    public string $outputPath = "";


    public bool $exportEnumByDefault = true;
    public bool $exportStorableByDefault = true;
    public bool $exportHttpRouteByDefault = true;
    public bool $exportHttpRequestByDefault = true;
    public bool $exportHttpResourceByDefault = true;
    public bool $exportErrorsByDefault = true;

    public ProjectConfigHttpRouter $httpRouter;

    public function prepare()
    {
        $this->outputPath = self::absoluteUrl($this->output);
        if ($this->exportAsTs) {
            $this->isAventus = false;
        }
        if (!$this->isAventus) {
            $this->useNamespace = false;
        }

        $this->replacer->prepare();
    }

    public static function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, '.')) {
            return realpath_safe(self::$configPathDir . DIRECTORY_SEPARATOR . $url) ?: $url;
        }

        return $url;
    }
}
