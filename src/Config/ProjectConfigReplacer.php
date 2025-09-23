<?php

namespace Aventus\Transpiler\Config;

class ProjectConfigReplacer
{
    public ProjectConfigReplacerPart $all;
    public ProjectConfigReplacerPart $genericError;
    public ProjectConfigReplacerPart $httpRouter;
    public ProjectConfigReplacerPart $normalClass;
    public ProjectConfigReplacerPart $storable;
    public ProjectConfigReplacerPart $withError;
    public ProjectConfigReplacerPart $httpRequest;
    public ProjectConfigReplacerPart $httpResource;

    public function __construct()
    {
        $this->all = new ProjectConfigReplacerPart();
        $this->genericError = new ProjectConfigReplacerPart();
        $this->httpRouter = new ProjectConfigReplacerPart();
        $this->normalClass = new ProjectConfigReplacerPart();
        $this->storable = new ProjectConfigReplacerPart();
        $this->withError = new ProjectConfigReplacerPart();
        $this->httpRequest = new ProjectConfigReplacerPart();
        $this->httpResource = new ProjectConfigReplacerPart();
    }
}
