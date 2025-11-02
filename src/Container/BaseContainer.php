<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Rename;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPClassPropriete;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPType;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Config\ProjectConfigReplacerPart;
use Aventus\Transpiler\Parser\PHPClassMethod;
use Aventus\Transpiler\Writer\FileWriterHelper;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Validation\Rules\Enum;
use PhpParser\Node\Scalar\String_;

abstract class BaseContainer
{
    protected FileWriterHelper $fileWriter;

    public string $namespace = "";
    public string $content = "";

    public bool $isConvertible = false;
    public bool $canBeAdded = true;
    public bool $isInternal = false;

    /** @var array<string, string[]> $importedFiles */
    public array $importedFiles = [];

    /** @var PHPSymbol[] */
    public array $unresolved = [];


    public function __construct(
        public PHPSymbol $symbol
    ) {
        $this->fileWriter = new FileWriterHelper();


        $this->namespace = str_replace("\\", ".", $symbol->namespace);
    }

    public function write()
    {
        $this->content = $this->writeAction();
    }
    protected abstract function writeAction(): string;

    public function getIndentedText(string $txt): string
    {
        return $this->fileWriter->getIndentedText($txt);
    }

    public function addTxt(string|array $txt, array &$result): void
    {
        $this->fileWriter->addTxt($txt, $result);
    }

    public function addTxtOpen(string $txt, array &$result): void
    {
        $this->fileWriter->addTxtOpen($txt, $result);
    }

    public function addTxtClose(string $txt, array &$result): void
    {
        $this->fileWriter->addTxtClose($txt, $result);
    }

    public function addIndent(): void
    {
        $this->fileWriter->addIndent();
    }

    public function removeIndent(): void
    {
        $this->fileWriter->removeIndent();
    }

    protected function getDocumentation(PHPSymbol|PHPClassPropriete $symbol): string
    {
        $result = [];
        $commentTxt = $symbol->description;
        if ($commentTxt != null && $commentTxt != "") {
            $this->addTxt("/**", $result);
            $lignes = explode("\n", $commentTxt);
            foreach ($lignes as $ligne) {
                $this->addTxt(" * " . $ligne, $result);
            }
            $this->AddTxt(" */", $result);
        }
        return implode("\r\n", $result);
    }

    public static function getAccessibilityExport(PHPSymbol $symbol): string
    {
        if ($symbol->isProtected) {
            return "export ";
        }
        if ($symbol->isPublic) {
            return "export ";
        }
        return "";
    }
    public static function getAccessibility(PHPSymbol|PHPClassPropriete|PHPClassMethod $symbol): string
    {
        $result = "";

        if ($symbol->isPrivate) $result = "private ";
        else if ($symbol->isProtected) $result = "protected ";
        else $result = "public ";

        if ($symbol->isStatic) {
            $result .= "static ";
        }
        return $result;
    }

    public function importType(PHPType $type)
    {
        $isInternal = $type->isInternal;
        if ($isInternal && $type->symbol != null && $this->symbol != $type->symbol) {
            if (!in_array($type->symbol, $this->unresolved)) {
                $this->unresolved[] = $type->symbol;
            }
        }
    }
    public function importSymbol(PHPSymbol $symbol)
    {
        $isInternal = $symbol->type->isInternal;
        if ($isInternal && $symbol != null && $this->symbol != $symbol) {
            if (!in_array($symbol, $this->unresolved)) {
                $this->unresolved[] = $symbol;
            }
        }
    }
    public function addImport(string $file, string $toImport)
    {
        $file = ProjectConfig::absoluteUrl($file);
        if (!array_key_exists($file, $this->importedFiles)) {
            $this->importedFiles[$file] = [];
        }
        if (!in_array($toImport, $this->importedFiles[$file])) {
            $this->importedFiles[$file][] = $toImport;
        }
    }

    public function omitType(PHPType $type, array|string $omit): PHPType|null
    {
        if (!is_array($omit)) {
            $omit = [$omit];
        }
        if (count($type->unions) > 0) {
            $typeResult = [];
            foreach ($type->unions as $subType) {
                $typeR = $this->omitType($subType, $omit);
                if ($typeR != null) {
                    $typeResult[] = $typeR;
                }
            }
            if (count($typeResult) == 0) {
                return null;
            }
            if (count($typeResult) == 1) {
                return $typeResult[0];
            }
            $result = new PHPType();
            foreach ($typeResult as $sub) {
                $result->unions[] = $sub;
            }
            return $result;
        }

        foreach ($omit as $o) {
            if (is_a($type->fullname, $o, true)) {
                return null;
            }
        }
        return $type;
    }

    public function getName()
    {
        $type = $this->symbol->type;
        return $this->getTypeName($type, 0, true);
    }

    public function getTypeName(PHPType $type, int $depth = 0, bool $genericExtendsConstraint = false): string
    {
        try {
            if (count($type->unions) > 0) {
                $subTypesTxt = [];
                foreach ($type->unions as $subType) {
                    $subTypesTxt[] = $this->getTypeName($subType, $depth, $genericExtendsConstraint);
                }
                return implode(" | ", $subTypesTxt);
            }

            $name = $type->isInternal ? $type->name : str_replace("\\", ".", $type->fullname);
            $isNullable = false;
            if ($type->isNullable) {
                $isNullable = true;
            }

            $this->importType($type);



            $isFull = false;
            $name = $this->getVariantTypeName($type, $depth, $genericExtendsConstraint, $name, $isFull);

            if (!$isFull && count($type->generics) > 0) {
                $name = $this->determineGenericType($type, $name, $depth, $genericExtendsConstraint);
            }

            if ($isNullable && !str_ends_with($name, "?")) {
                $name .= "?";
            }
            return $name;
        } catch (Exception $e) {
            Console::dump($type);
            throw $e;
        }
    }

    public function getVariantTypeName(PHPType $type, int $depth, bool $genericExtendsConstraint, string $name, bool &$isFull): string
    {
        $isFull = false;
        $fullName = $type->fullname;
        $isNullable = $type->isNullable;
        $isArray = $type->isArray;
        $result = $name;
        if ($fullName == "int") $result = "number";
        else if ($fullName == "float") $result = "number";
        else if ($fullName == "bool") $result = "boolean";
        else if ($fullName == "string") $result = "string";
        else if ($fullName == Enum::class) $result = "Aventus.Enum";
        else if ($fullName == DateTime::class) $result = "Date";
        else if ($fullName == Carbon::class) $result = "Date";
        else if ($fullName == SupportCarbon::class) $result = "Date";
        else if ($fullName == "array") {
            $result = "any[]";
            $isFull = true;
        } else if ($fullName == "Illuminate\\Http\\UploadedFile") $result = "File";
        else if ($fullName == "Aventus\\Laraventus\\Models\\AventusModel") $result = "Aventus.Data";
        else if ($fullName == "Aventus\\Laraventus\\Requests\\AventusRequest") $result = "";
        else if ($fullName == "Aventus\\Laraventus\\Resources\\AventusModelResource") {
            $result = "Aventus.Data";
            $isFull = true;
        } else if ($fullName == "Aventus\\Laraventus\\Resources\\AventusResource") {
            $result = "Aventus.Data";
            $isFull = true;
        } else if ($fullName == "Aventus\\Laraventus\\Resources\\AventusAutoBindResource") {
            $result = "Aventus.Data";
            $isFull = true;
        } else if ($fullName == "Aventus\\Laraventus\\Models\\AventusFile") $result = "AventusPhp.AventusFile";
        else if ($fullName == "Aventus\\Laraventus\\Models\\AventusImage") $result = "AventusPhp.AventusImage";
        else if ($fullName == "Aventus\\Laraventus\\Helpers\\AventusError") $result = "AventusPhp.AventusError";
        else if ($fullName == "Aventus\\Laraventus\\Controllers\\ModelController") $result = "AventusPhp.ModelController";
        else if ($fullName == "Aventus\\Laraventus\\Exceptions\\LaraventusErrorEnum") $result = "AventusPhp.LaraventusErrorEnum";
        else if ($fullName == "Aventus\\Laraventus\\Requests\\IdsManyRequest") $result = "AventusPhp.IdsManyRequest";
        else if ($fullName == "Aventus\\Laraventus\\Requests\\ItemsManyRequest") $result = "AventusPhp.ItemsManyRequest";
        else if ($fullName == "JsonSerializable") $result = "";
        else if (
            $fullName == "list" ||
            $fullName == "Illuminate\\Database\\Eloquent\\Collection" ||
            $fullName == "Illuminate\Support\Collection"
        ) {
            $isArray = true;
            if (count($type->generics) == 0) {
                $result = "any";
            } else {
                $result = $this->getTypeName($type->generics[count($type->generics) - 1], $depth, $genericExtendsConstraint);
            }
            $isFull = true;
        }

        if ($type->symbol) {
            $attr = $type->symbol->getAttribute(Rename::class);
            if ($attr != null) {
                $expr = $attr->args[0]->value;
                if ($expr instanceof String_) {
                    $result = $expr->value;
                }
            }
        }

        if ($isArray) {
            $result .= "[]";
        }

        if ($isNullable && !str_ends_with($result, "?")) {
            $result .= "?";
        }

        $result = $this->applyReplacer(ProjectConfig::$config->replacer->all, $fullName, $result) ?? "";
        return $this->customReplacer($type, $fullName, $result) ?? "";
    }

    public function determineGenericType(PHPType $type, string $name, int $depth, bool $genericExtendsConstraint)
    {
        if ($name == "") {
            return $name;
        }

        $gens = [];
        foreach ($type->generics as $generic) {
            $temp = $this->parseGenericType($generic, $depth, $genericExtendsConstraint);
            if ($temp) {
                $gens[] = $temp;
            }
        }

        if (count($gens) > 0) {
            $name .= "<" . implode(", ", $gens) . ">";
        }
        return $name;
    }

    protected function parseGenericType(PHPType $type, int $depth, bool $genericExtendsConstraint): string
    {
        if (!$genericExtendsConstraint) {
            return $this->getTypeName($type, $depth + 1, $genericExtendsConstraint);
        }

        if ($type->isTypeParameter) {
            if ($genericExtendsConstraint) {
                $extends = [];
                foreach ($type->constraintsGeneric as $constraint) {
                    $extendsTemp = $this->getTypeName($constraint, $depth + 1);
                    if ($extendsTemp != "") {
                        $extends[] = $extendsTemp;
                    }
                }

                $result = $type->name;
                if (count($extends) > 0) {
                    $result .= " extends " . implode(", ", $extends);
                }
                if ($type->defaultGenericValue) {
                    $defaultType = $this->getTypeName($constraint, $depth + 1, $genericExtendsConstraint);
                    if ($defaultType != "") {
                        $result .= " = " . $defaultType;
                    }
                }

                return $result;
            }
        }
        return $type->name;
    }

    protected function customReplacer(?PHPType $symbol, string $fullname, ?string $result): ?string
    {
        return $result;
    }
    protected function applyReplacer(ProjectConfigReplacerPart $part, string $fullname, ?string $result): ?string
    {
        foreach ($part->type as $_fullName => $info) {
            if ($fullname != "" && $_fullName == $fullname) {
                $result = $info->result;
                if ($info->file != "") {
                    $file = ProjectConfig::absoluteUrl($info->file);
                    if (!array_key_exists($file, $this->importedFiles)) {
                        $this->importedFiles[$file] = [];
                    }
                    if (!in_array($result, $this->importedFiles[$file])) {
                        if ($info->useTypeImport) {
                            $this->importedFiles[$file][] = "type " + $result;
                        } else {
                            $this->importedFiles[$file][] = $result;
                        }
                    }
                }
                break;
            }
        }

        foreach ($part->result as $_fullName => $info) {
            if ($_fullName == $result) {
                $result = $info->result;
                if ($info->file != "") {
                    $file = ProjectConfig::absoluteUrl($info->file);
                    if (!array_key_exists($file, $this->importedFiles)) {
                        $this->importedFiles[$file] = [];
                    }

                    if (!in_array($result, $this->importedFiles[$file])) {
                        if ($info->useTypeImport) {
                            $this->importedFiles[$file][] = "type " + $result;
                        } else {
                            $this->importedFiles[$file][] = $result;
                        }
                    }
                }

                break;
            }
        }
        return $result;
    }
}
