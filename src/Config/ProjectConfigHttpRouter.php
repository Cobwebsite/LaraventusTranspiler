<?php

namespace Aventus\Transpiler\Config;

class ProjectConfigHttpRouter
{

    public bool $createRouter = false;
    public string $routerName = "GeneratedRouter";
    public string $uri = "";
    public ?string $host = null;
    public string $parent = "Aventus.HttpRouter";
    public ?string $parentFile = null;
    public ?string $namespace = "Routes";
}
