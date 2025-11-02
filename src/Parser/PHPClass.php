<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Attributes\IsController;
use Aventus\Laraventus\Attributes\NoExport;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Modifiers;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * Summary of PHPClass
 */
class PHPClass extends PHPSymbol
{
    public static function parse(Interface_|Class_|Trait_ $node, $namespace, array $useStatements): PHPClass|null
    {
        $className = $node->name->name ?? 'AnonymousClass';
        if (Type::hasAttribute($node->attrGroups, NoExport::class, $useStatements, $namespace)) {
            return null;
        }
        $fullName = $namespace . '\\' . $className;
        $result = self::get($fullName);
        if ($result == null) {
            $phpClass = new PHPClass($node, $className, $namespace, $useStatements);
            $doc = PHPDoc::parse($node->getDocComment()?->getText(), $phpClass);
            if ($doc->documentation) {
                $phpClass->description = $doc->documentation;
            }
            self::$symbols[$fullName] = $phpClass;
            $result = $phpClass;
        }

        return $result;
    }
    /**
     * Summary of properties
     * @var array<string, PHPClassPropriete>
     */
    public array $properties = [];
    /**
     * Summary of methods
     * @var array<string, PHPClassMethod>
     */
    public array $methods = [];

    public ?PHPClassMethod $cst = null;

    public bool $isInterface = false;
    public bool $isTrait = false;

    public ?PHPClass $parent = null;
    /** @var PHPClass[] */
    public array $interfaces = [];
    /** @var PHPClass[] */
    public array $traits = [];
    public bool $isController = false;

    /** @var array<string, string|string[]> */
    public array $additionalFcts = [];


    protected function __construct(Interface_|Class_|Trait_ $node, string $name, string $namespace = '', array $uses)
    {
        parent::__construct($node, $name, $namespace, $uses);
        $this->isInterface = $node instanceof Interface_;
        $this->isTrait = $node instanceof Trait_;
        $this->isController = $this->hasAttribute(IsController::class);
    }

    public function extends(string $name)
    {
        if ($this->parent == null) {
            return false;
        }
        if ($this->parent->getFullname() == $name) {
            return true;
        }
        return $this->parent->extends($name);
    }

    public function implements(string $name)
    {
        if (count($this->interfaces) == 0) {
            return false;
        }
        foreach ($this->interfaces as $interface) {
            if ($interface->getFullname() == $name) {
                return true;
            }
        }
        if ($this->parent != null) {
            return $this->parent->implements($name);
        }
        return false;
    }

    public function hasTrait(string $name)
    {
        foreach ($this->traits as $trait) {
            if ($trait->getFullname() == $name) {
                return true;
            }
            // on ne gère pas encore l'extension de trait
        }
        if ($this->parent != null) {
            return $this->parent->hasTrait($name);
        }
        return false;
    }

    public function getMethod(string $name): PHPClassMethod|null
    {
        foreach ($this->methods as $key => $method) {
            if ($key == $name) {
                return $method;
            }
        }
        if ($this->parent != null) {
            return $this->parent->getMethod($name);
        }
        return null;
    }
    /**
     * @return array<string, PHPClassMethod>
     */
    public function getMethods(&$result = []): array
    {
        foreach ($this->methods as $key => $method) {
            if (!array_key_exists($key, $result)) {
                $result[$key] = $method;
            }
        }
        if ($this->parent != null) {
            return $this->parent->getMethods($result);
        }
        return $result;
    }

    public function is(string $name): bool
    {
        if ($this->getFullname() == $name) {
            return true;
        }
        if ($this->extends($name)) {
            return true;
        }
        if ($this->implements($name)) {
            return true;
        }
        if ($this->hasTrait($name)) {
            return true;
        }
        return false;
    }
}
