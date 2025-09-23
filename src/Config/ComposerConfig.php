<?php

namespace Aventus\Transpiler\Config;

use Aventus\Laraventus\Tools\Console;
use Exception;

class ComposerConfig
{
    /**
     * key is namespace, value is folder from composer.json
     * @var array<string, string>
     */
    protected $namespaces = [];
    /**
     * @var string[]
     */
    protected $namespacesInternal = [];
    protected string $uri;
    public static function init($path)
    {
        if (!file_exists($path . "composer.json")) {
            error($path . "composer.json not found");
            return null;
        }
        $content = file_get_contents($path . "composer.json");
        try {
            $content = json_decode($content);
        } catch (Exception $e) {
            error($e->getMessage());
            return null;
        }

        $result = new ComposerConfig();
        $result->uri = $path;
        if (isset($content->autoload) && isset($content->autoload->{"psr-4"})) {
            $psr = $content->autoload->{"psr-4"};
            foreach ($psr as $key => $value) {
                $result->namespaces[$key] = $value;
                $result->namespacesInternal[] = $key;
            }
        }

        $vendorPath = $path . '/vendor/';
        if (!is_dir($vendorPath)) {
            error("Vendor path not found: $vendorPath");
        } else {
            $vendorDirs = scandir($vendorPath);
            foreach ($vendorDirs as $vendorName) {
                if ($vendorName === '.' || $vendorName === '..') {
                    continue;
                }

                $packagePath = $vendorPath . $vendorName . '/';

                if (!is_dir($packagePath)) {
                    continue;
                }

                $packages = scandir($packagePath);
                foreach ($packages as $packageName) {
                    if ($packageName === '.' || $packageName === '..') {
                        continue;
                    }

                    $fullPath = $packagePath . $packageName . '/';
                    $composerJsonPath = $fullPath . 'composer.json';

                    if (!file_exists($composerJsonPath)) {
                        continue;
                    }

                    $content = file_get_contents($composerJsonPath);
                    try {
                        $content = json_decode($content);
                    } catch (Exception $e) {
                        echo $e->getMessage();
                        return null;
                    }

                    if (isset($content->autoload) && isset($content->autoload->{"psr-4"})) {
                        $psr = $content->autoload->{"psr-4"};
                        foreach ($psr as $key => $value) {
                            if (is_string($value)) {
                                $result->namespaces[$key] = "vendor/" . $vendorName . "/" . $packageName . "/" . $value;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function __construct() {}


    public function namespaceToUri(string $name)
    {
        foreach ($this->namespaces as $start => $folder) {
            if (str_starts_with($name, $start)) {
                $value = str_replace($start, $folder, $name);

                $uri = realpath($this->uri . $value . ".php");
                if ($uri == false)
                    return null;
                return $uri;
            }
        }
        return null;
    }

    public function isInternal(string $fullname)
    {
        foreach ($this->namespacesInternal as $start) {
            if (str_starts_with($fullname, $start))
                return true;
        }
        return false;
    }
}
