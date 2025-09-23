<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Export;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPType;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Writer\FileWriterHelper;

class NormalClassContainer extends BaseClassContainer
{
    public static function is(PHPSymbol $symbol, string $fileName): BaseContainer|null
    {
        $result = null;
        
        if ($symbol->hasAttribute(Export::class)) {
            $result = new NormalClassContainer($symbol);
        }
        return $result;
    }


    protected function customReplacer(?PHPType $type, string $fullname, ?string $result): ?string
    {
        return $this->applyReplacer(ProjectConfig::$config->replacer->normalClass, $fullname, $result);
    }

}
