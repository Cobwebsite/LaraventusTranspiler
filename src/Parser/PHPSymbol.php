<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Modifiers;
use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionClass;

/**
 * @property array<string, PHPUse> $uses
 * @property PHPGeneric[] $generics
 * @property PHPType[] $genericValues
 * @property AttributeGroup[] $attrGroups
 */
abstract class PHPSymbol
{
    /** @var array<string, PHPSymbol> */
    public static $symbols = [];

    public static function get(string $fullName)
    {
        if (array_key_exists($fullName, self::$symbols)) {
            return self::$symbols[$fullName];
        }
        return null;
    }

    public string $description = "";
    public bool $isPublic = true;
    public bool $isPrivate = false;
    public bool $isProtected = false;
    public bool $isStatic = false;
    public bool $isAbstract = false;
    public string $uri = "";
    public bool $isOnlyQuick = false;

    public array $generics = [];
    public array $genericValues = [];

    public array $attrGroups = [];

    public PHPType $type;

    // use ref to point on real type when makeGenerics
    public ?PHPSymbol $ref = null;

    protected function __construct(
        ClassLike $node,
        public string $name,
        public string $namespace,
        public array $uses
    ) {
        $type = new PHPType($this);
        $type->name = $this->name;
        $type->fullname = $this->getFullname();
        $this->type = $type;
        $this->attrGroups = $node->attrGroups;
    }

    public function getFullname()
    {
        return $this->namespace . '\\' . $this->name;
    }

    /**
     * @param int[] $flags 
     * @return void
     */
    public function parseFlags(array $flags)
    {
        foreach ($flags as $flag) {
            if ($flag == Modifiers::PROTECTED) {
                $this->isProtected = true;
                $this->isPublic = false;
            } else if ($flag == Modifiers::PRIVATE) {
                $this->isPrivate = true;
                $this->isPublic = false;
            } else if ($flag == Modifiers::STATIC) {
                $this->isStatic = true;
            } else if ($flag == Modifiers::ABSTRACT) {
                $this->isAbstract = true;
            }
        }
    }

    public function hasAttribute(string $name): bool
    {
        return Type::hasAttribute($this->attrGroups, $name, $this->uses, $this->namespace);
    }

    public function getAttribute(string $name): Attribute|null
    {
        return Type::getAttribute($this->attrGroups, $name, $this->uses, $this->namespace);
    }
    public function is(string $name): bool
    {
        return $this->getFullname() == $name;
    }

    public function __get($name)
    {
        if ($name == "ref") {
            return $this->ref;
        }
        if ($name == "type") {
            $type = clone $this->ref->type;
            $type->generics = $this->genericValues;
            return $type;
        }
        if ($this->ref !== null && property_exists($this->ref, $name)) {
            return $this->ref->{$name};
        }

        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }
    /**
     * @param PHPType[] $types
     */
    public function makeGenerics(PHPType ...$types)
    {
        $clone = clone $this;
        $clone->ref = $this;
        $clone->genericValues = $types;
        $reflection = new ReflectionClass($clone);

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            if (in_array($name, ['genericValues', 'ref'])) {
                continue;
            }

            if ($property->isStatic()) continue;
            if ($property->isPublic()) {
                unset($clone->$name);
            } else {
                $property->setAccessible(true);
                $property->setValue($clone, null);
            }
        }
        return $clone;
    }
}
