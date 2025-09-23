<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Export;
use Aventus\Laraventus\Helpers\AventusError;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPType;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Tools\Type;

class ErrorContainer extends BaseClassContainer
{
    public static function is(PHPSymbol $symbol, string $fileName): BaseContainer|null
    {
        $result = null;

        if ($symbol instanceof PHPClass && $symbol->extends(AventusError::class)) {
            if (Type::exportToTypesript($symbol, ProjectConfig::$config->exportErrorsByDefault)) {
                $result = new ErrorContainer($symbol);
            }
        }
        return $result;
    }


    protected function customReplacer(?PHPType $type, string $fullname, ?string $result): ?string
    {
        return $this->applyReplacer(ProjectConfig::$config->replacer->genericError, $fullname, $result);
    }
}
