<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\NoExport;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPClassPropriete;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Parser\ClassManager;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * @property PHPClass $symbol
 */
abstract class BaseClassContainer extends BaseContainer
{
    protected bool $isInterface = false;
    public function __construct(
        PHPClass $class
    ) {
        parent::__construct($class);

        $this->isInterface = $class->isInterface;

        if ($this->canConvert()) {
            $this->isConvertible = !$this->isInterface && !$this->symbol->isAbstract && ProjectConfig::$config->isAventus;
        }
    }

    private function getAbstract()
    {
        if (!$this->isInterface && $this->symbol->isAbstract) {
            return "abstract ";
        }
        return "";
    }
    private function getKind()
    {
        return $this->isInterface ? "interface " : "class ";
    }


    protected function writeAction(): string
    {
        $result = [];
        if (ProjectConfig::$config->useNamespace && strlen($this->namespace) > 0) {
            $this->addIndent();
        }
        $documentation = $this->getDocumentation($this->symbol);
        if (strlen($documentation) > 0) {
            $result[] = $documentation;
        }
        if ($this->isConvertible) {
            $this->addTxt("@Convertible()", $result);
        }
        if ($this->isInternal) {
            $this->addTxt("@Internal()", $result);
        }
        $this->addTxtOpen($this->getAccessibilityExport($this->symbol) . $this->getAbstract() . $this->getKind() . $this->getName() . " " . $this->getExtension() . "{", $result);
        $result[] = $this->getContent();
        $this->addTxtClose("}", $result);

        if (ProjectConfig::$config->useNamespace && strlen($this->namespace) > 0) {
            $this->removeIndent();
        }

        return implode("\r\n", $result);
    }


    protected function canConvert(): bool
    {
        return true;
    }

    /** @var string[] */
    protected $extends = [];
    public function getExtension(): string
    {
        /** @var string[] */
        $extends = [];
        /** @var string[] */
        $implements = [];

        if ($this->isInterface) {
            if ($this->symbol->parent) {
                if ($this->isValidExtendsClass($this->symbol->parent)) {
                    $temp = $this->getTypeName($this->symbol->parent->type);
                    if ($temp != "") {
                        $extends[] = $temp;
                    }
                }
            }
            foreach ($this->symbol->interfaces as $interface) {
                if ($this->isValidExtendsInterface($interface)) {
                    $temp = $this->getTypeName($interface->type);
                    if ($temp != "") {
                        $extends[] = $temp;
                    }
                }
            }
        } else {
            if ($this->symbol->parent) {
                if ($this->isValidExtendsClass($this->symbol->parent)) {
                    $temp = $this->getTypeName($this->symbol->parent->type);
                    if ($temp != "") {
                        $extends[] = $temp;
                    }
                }
            }
            foreach ($this->symbol->interfaces as $interface) {
                if ($this->isValidImplements($interface)) {
                    $temp = $this->getTypeName($interface->type);
                    if ($temp != "") {
                        $implements[] = $temp;
                    }
                }
            }
        }


        $this->addExtends(function ($value) use (&$extends) {
            if (!in_array($value, $extends)) {
                $extends[] = $value;
            }
        });
        $this->addImplements(function ($value) use (&$implements) {
            if (!in_array($value, $implements)) {
                $implements[] = $value;
            }
        });

        if (count($extends) == 0 && !$this->isInterface) {
            // $extends[] = GetAventusTypeName("AventusSharp.Data.SharpClass");
        }

        $txt = "";
        if (count($extends) > 0) {
            $txt .= "extends " . implode(", ", $extends);
        }
        if (strlen($txt) > 0) {
            $txt .= " ";
        }
        if (count($implements) > 0) {
            $txt .= "implements " . implode(", ", $implements);
        }
        if (strlen($txt) > 0) {
            $txt .= " ";
        }
        $this->extends = $extends;
        return $txt;
    }

    protected function getDefaultValue(PHPClassPropriete $property, string $type)
    {
        if ($property->default) {
            if (is_string($property->default)) {
                return $property->default;
            }
            return $this->parseExpr($property->default) ?? "undefined";
        }
        return "undefined";
    }
    protected function parseExpr(Expr $expr)
    {
        if ($expr instanceof ConstFetch) {
            return $expr->name->name;
        }
        if ($expr instanceof String_) {
            return '"' . $expr->value . '"';
        }
        if ($expr instanceof Int_) {
            return $expr->value;
        }
        if ($expr instanceof Float_) {
            return $expr->value;
        }
        if ($expr instanceof Array_) {
            $result = [];
            foreach ($expr->items as $item) {
                $temp = $this->parseExpr($item->value);
                if ($temp !== null) {
                    $result[] = $temp;
                }
            }
            return "[" . implode(", ", $result) . "]";
        }
        if ($expr instanceof New_) {
            $name = $expr->class->name;
            $symbol = Type::getSymbol($name, $this->symbol->uses, $this->symbol->namespace);
            if ($symbol != null) {
                $this->importSymbol($symbol);
            }

            return "new " . $expr->class->name . "()";
        }
        Console::log("---- parseExpr ----");
        Console::dump($expr);
        return null;
    }
    private function getContent(): string
    {
        /** @var string[] */
        $loadedFields = [];
        /** @var string[] */
        $result = [];

        $this->getContentBefore($result);

        $this->defineFullname($result);

        foreach ($this->symbol->properties as $property) {
            if (in_array($property->name, $loadedFields)) {
                continue;
            }
            if ($property->isNoExport()) {
                continue;
            }
            if (!$this->isValidProperty($property)) {
                continue;
            }
            $loadedFields[] = $property->name;

            $documentation = $this->getDocumentation($property);
            if (strlen($documentation) > 0) {
                $result[] = $documentation;
            }

            $propName = $property->name;
            if (str_starts_with($propName, "$")) {
                $propName = substr($propName, 1);
            }
            $typeTxt = $this->getTypeName($property->type);
            if (str_ends_with($typeTxt, "?")) {
                $typeTxt = substr($typeTxt, 0, strlen($typeTxt) - 1);
                $propName .= "?";
            }

            $defaultValue = $this->getDefaultValue($property, $typeTxt);
            $isUndefined = false;
            if ($defaultValue == "undefined" && !str_ends_with($propName, "?")) {
                $isUndefined = true;
                if (!$this->isInterface) {
                    $propName .= "!";
                }
            }

            $txt = "";
            if ($this->isInterface) {
                $txt = $propName . ": " . $typeTxt . ";";
            } else if ($isUndefined) {
                $txt = $this->getAccessibility($property) . $propName . ": " . $typeTxt . ";";
            } else {
                $txt = $this->getAccessibility($property) . $propName . ": " . $typeTxt . " = " . $defaultValue . ";";
            }
            $this->addTxt($txt, $result);
            $this->additionalContentProperty($property, $result);
        }

        $this->getContentAfter($result);
        return implode("\r\n", $result);
    }

    protected function additionalContentProperty(PHPClassPropriete $property, array &$result) {}

    /**
     * @param string [] $result
     */
    protected function defineFullname(array &$result)
    {
        if ($this->isConvertible) {
            $typeName = "\"" . str_replace("\\", ".", $this->symbol->type->fullname) . "\"";
            if (!$this->symbol->isInterface && !$this->symbol->isAbstract && count($this->extends) > 0) {
                $this->addTxt("public static override get Fullname(): string { return " . $typeName . "; }", $result);
            } else {
                $this->addTxt("public static get Fullname(): string { return " . $typeName . "; }", $result);
            }
        }
    }
    /**
     * @param string [] $result
     */
    protected function getContentBefore(array &$result) {}
    /**
     * @param string [] $result
     */
    protected function getContentAfter(array &$result) {}


    protected function isValidExtendsInterface(PHPClass $type): bool
    {
        return true;
    }
    protected function isValidExtendsClass(PHPClass $type): bool
    {
        return true;
    }
    protected function isValidImplements(PHPClass $type): bool
    {
        return true;
    }

    protected function isValidProperty(PHPClassPropriete $type): bool
    {
        return true;
    }

    protected function addExtends(callable $add) {}
    protected function addImplements(callable $add) {}
}
