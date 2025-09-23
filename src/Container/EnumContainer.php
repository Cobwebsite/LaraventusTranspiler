<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Export;
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

class EnumContainer extends BaseContainer
{
    public static function is(PHPSymbol $symbol, string $fileName): BaseContainer|null
    {
        $result = null;

        if ($symbol instanceof PHPEnum) {
            if (Type::exportToTypesript($symbol, ProjectConfig::$config->exportEnumByDefault)) {
                $result = new EnumContainer($symbol);
            }
        }

        return $result;
    }

    protected function writeAction(): string
    {
        if (!($this->symbol instanceof PHPEnum)) return "";
        $result = [];
        if (ProjectConfig::$config->useNamespace) {
            $this->addIndent();
        }
        $documentation = $this->getDocumentation($this->symbol);
        if (strlen($documentation) > 0) {
            $result[] = $documentation;
        }
        $this->addTxtOpen("export enum " . $this->symbol->name . " {", $result);
        $fields = [];
        foreach ($this->symbol->values as $key => $value) {
            if($value == "") {
                $this->addTxt($key, $fields);
            }
            else {
                $this->addTxt($key . " = " . $value, $fields);
            }
        }
        $result[] = implode(",\r\n", $fields);
        $this->addTxtClose("}", $result);
        if (ProjectConfig::$config->useNamespace) {
            $this->removeIndent();
        }

        return implode("\r\n", $result);
    }
}
