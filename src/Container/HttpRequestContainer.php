<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Export;
use Aventus\Laraventus\Models\AventusModel;
use Aventus\Laraventus\Requests\AventusRequest;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPType;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Parser\Parser;
use Aventus\Transpiler\Parser\PHPClassPropriete;
use Aventus\Transpiler\Tools\Type;
use Aventus\Transpiler\Writer\FileWriterHelper;

class HttpRequestContainer extends BaseClassContainer
{
    public static function is(PHPSymbol $symbol, string $fileName): BaseContainer|null
    {
        $result = null;

        if ($symbol instanceof PHPClass && $symbol->extends(AventusRequest::class)) {
            if (Type::exportToTypesript($symbol, ProjectConfig::$config->exportHttpRequestByDefault)) {
                $result = new HttpRequestContainer($symbol);
            }
        }

        return $result;
    }

    public function __construct(
        PHPClass $class
    ) {
        parent::__construct($class);
    }

    protected function canConvert(): bool
    {
        return false;
    }

    protected function customReplacer(?PHPType $symbol, string $fullname, ?string $result): ?string
    {
        return $this->applyReplacer(ProjectConfig::$config->replacer->httpRequest, $fullname, $result);
    }
}
