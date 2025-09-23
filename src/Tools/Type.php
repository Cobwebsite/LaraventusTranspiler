<?php

namespace Aventus\Transpiler\Tools;

use Aventus\Laraventus\Attributes\Export;
use Aventus\Laraventus\Attributes\NoExport;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Parser\PHPClassMethod;
use Aventus\Transpiler\Parser\PHPSymbol;
use Aventus\Transpiler\Parser\PHPUse;
use Exception;
use InvalidArgumentException;
use PhpParser\Modifiers;
use PhpParser\Node\Attribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class Type
{
    private static $types = [
        'int',
        'integer',
        'float',
        'double',
        'real',
        'string',
        'bool',
        'boolean',
        'array',
        'object',
        'callable',
        'iterable',
        'resource',
        'null',
        'mixed',
        'void',
        'false',
        'true',
        'self',
        'parent',
        'static',
    ];
    public static function isNative(string $name)
    {
        return in_array(strtolower($name), self::$types);
    }
    /**
     * Summary of modifiersList
     * @var int[]
     */
    private static array $modifiersList = [];
    /**
     * 
     * @param int $flags
     * @return int[] You can use Modifiers::
     */
    public static function parseModifiers(int $flags)
    {
        if (count(self::$modifiersList) == 0) {
            $temp = (new ReflectionClass(Modifiers::class))->getConstants();
            foreach ($temp as $name => $value) {
                if (is_int($value)) {
                    self::$modifiersList[] = $value;
                }
            }
        }

        $result = [];
        foreach (self::$modifiersList as $value) {
            if ($flags & $value) {
                $result[] = $value;
            }
        }

        return $result;
    }


    /**
     * Summary of hasAttribute
     * @param \PhpParser\Node\AttributeGroup[] $attrGroups
     * @param string $name
     * @param array<string, PHPUse> $uses
     * @param string $namespace
     * @return bool
     */
    public static function hasAttribute(array $attrGroups, string $name, array $uses, string $namespace)
    {
        return self::getAttribute($attrGroups, $name, $uses, $namespace) !== null;
    }

    /**
     * Summary of hasAttribute
     * @param \PhpParser\Node\AttributeGroup[] $attrGroups
     * @param string $name
     * @param array<string, PHPUse> $uses
     * @param string $namespace
     * @return Attribute|null
     */
    public static function getAttribute(array $attrGroups, string $name, array $uses, string $namespace): Attribute|null
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {

                $nameAttr = $attr->name->name;
                if (isset($uses[$nameAttr])) {
                    $nameAttr = $uses[$nameAttr]->fullname;
                } else {
                    $nameAttr = $namespace . '\\' . $nameAttr;
                }
                // Console::dump($nameAttr);
                // Console::dump($name);
                if ($nameAttr == $name) {
                    return $attr;
                }
            }
        }
        return null;
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @param string|array $json
     * @return T
     */
    public static function fromJson(string $class, $json)
    {
        if (is_array($json)) {
            $data = $json;
        } else {
            try {
                $data = json_decode($json, true);
            } catch (Exception $e) {
                echo $e->getMessage();
                return;
            }
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON provided.');
        }

        $reflection = new ReflectionClass($class);
        $instance = new $class();
        // $instance = $reflection->newInstanceWithoutConstructor();
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();

            if (!array_key_exists($name, $data)) {
                continue;
            }

            $type = $property->getType();
            $value = $data[$name];

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $isNullable = $type->allowsNull();

                if ($value === null && !$isNullable) {
                    continue;
                }

                if (class_exists($typeName)) {
                    // Cas d'un objet
                    $propertyValue = self::fromJson($typeName, $value);
                } else {
                    // Types scalaires
                    settype($value, $typeName);
                    $propertyValue = $value;
                }
            } else {
                $propertyValue = $value;
            }


            $property->setValue($instance, $propertyValue);
        }
        return $instance;
    }

    /**
     * Return the fullname
     * @param string $name
     * @param array<string, PHPUse> $uses
     */
    public static function getFullname(string $name, array $uses, string $namespace)
    {
        if (array_key_exists($name, $uses)) {
            $name = $uses[$name]->fullname;
        } else if (str_starts_with($name, "\\")) {
            $name = substr($name, 1);
        } else if (!self::isNative($name)) {
            $name = $namespace . '\\' . $name;
        }
        return $name;
    }
    /**
     * Return the symbol link to a name
     * @param string $name
     * @param array<string, PHPUse> $uses
     * @param string $namespace
     */
    public static function getSymbol($name, $uses, $namespace): PHPSymbol|null
    {
        $name = self::getFullname($name, $uses, $namespace);
        $result = PHPSymbol::get($name);
        if ($result != null) return $result;

        return PHPSymbol::get($namespace . "\\" . $name);
    }

    public static function exportToTypesript(PHPSymbol|PHPClassMethod $symbol, bool $defaultValue): bool
    {

        if ($defaultValue) {
            if ($symbol->hasAttribute(NoExport::class)) {
                return false;
            }
        } else {
            if ($symbol->hasAttribute(Export::class)) {
                return true;
            }
        }

        return $defaultValue;
    }
}
