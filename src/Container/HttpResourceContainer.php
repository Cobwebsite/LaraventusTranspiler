<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Resources\AventusResource;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPType;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Tools\Type;

class HttpResourceContainer extends BaseClassContainer
{
    public static function is(PHPSymbol $symbol, string $fileName): BaseContainer|null
    {
        $result = null;

        if ($symbol instanceof PHPClass && $symbol->extends(AventusResource::class)) {
            if (Type::exportToTypesript($symbol, ProjectConfig::$config->exportHttpResourceByDefault)) {
                $result = new HttpResourceContainer($symbol);
            }
        }

        return $result;
    }

    protected function customReplacer(?PHPType $symbol, string $fullname, ?string $result): ?string
    {
        return $this->applyReplacer(ProjectConfig::$config->replacer->httpResource, $fullname, $result);
    }
}
