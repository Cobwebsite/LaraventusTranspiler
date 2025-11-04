<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Export;
use Aventus\Laraventus\Models\AventusModel;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPType;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Parser\Parser;
use Aventus\Transpiler\Parser\PHPClassPropriete;
use Aventus\Transpiler\Tools\Type;
use Aventus\Transpiler\Writer\FileWriterHelper;

class StorableContainer extends BaseClassContainer
{
    public static function is(PHPSymbol $symbol, string $fileName): BaseContainer|null
    {
        $result = null;

        if ($symbol instanceof PHPClass && $symbol->extends(AventusModel::class)) {
            if (Type::exportToTypesript($symbol, ProjectConfig::$config->exportStorableByDefault)) {
                $result = new StorableContainer($symbol);
            }
        }

        return $result;
    }


    protected function addImplements(callable $add)
    {
        if (!$this->isInterface) {
            if(ProjectConfig::$config->isAventus) {
                $add("Aventus.IData");
            }
            else {
                $add("IData");
                $this->addImport("@aventusjs/main/Aventus", "IData");
            }
        }
    }
    protected function addExtends(callable $add)
    {
        if ($this->isInterface) {
            if(ProjectConfig::$config->isAventus) {
                $add("Aventus.IData");
            }
            else {
                $add("IData");
                $this->addImport("@aventusjs/main/Aventus", "IData");
            }
        }
    }

    protected function defineFullname(array &$result)
    {
        if ($this->isConvertible) {
            $typeName = "\"" . str_replace("\\", ".", $this->symbol->type->fullname) . "\"";
            $this->addTxt("public static override get Fullname(): string { return " . $typeName . "; }", $result);
        }
    }

    protected function isValidProperty(PHPClassPropriete $type): bool
    {
        $fields = [
            "connection",
            "table",
            "primaryKey",
            "keyType",
            "incrementing",
            "with",
            "withCount",
            "preventsLazyLoading",
            "perPage",
            "exists", 
            "wasRecentlyCreated",
            "escapeWhenCastingToString",
            "timestamps", 
            "usesUniqueIds",
            "hidden",
            "visible",
            "fillable",
            "guarded",
            "attributes",
            "original",
            "changes",
            "casts",
            "classCastCache",
            "attributeCastCache",
            "dateFormat",
            "appends",
            "dispatchesEvents",
            "observables",
            "relations",
            "touches"
        ];
        if(in_array($type->name, $fields)) return false;
        return true;
    }

    protected function customReplacer(?PHPType $symbol, string $fullname, ?string $result): ?string
    {
        return $this->applyReplacer(ProjectConfig::$config->replacer->storable, $fullname, $result);
    }
}
