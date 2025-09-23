<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Attributes\DefaultValue;
use Aventus\Laraventus\Attributes\DefaultValueRaw;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Modifiers;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;

/**
 * @property AttributeGroup[] $attrGroups
 */
class PHPClassPropriete
{
    public bool $isPrivate = false;
    public bool $isProtected = false;
    public bool $isStatic = false;

    public function __construct(
        public ?PHPClass $class,
        public string $name,
        public PHPType $type,
        public ?string $description = null,
        public null|Expr|string $default,
        public array $attrGroups
    ) {

        if (str_starts_with($this->name, "$")) {
            $this->name = substr($this->name, 1);
        }
        
        $defaultValue = $this->getAttribute(DefaultValue::class);
        if ($defaultValue !== null) {
            $this->default = $defaultValue->args[0]->value;
        } else {
            $defaultValue = $this->getAttribute(DefaultValueRaw::class);
            if ($defaultValue !== null) {
                $expr = $defaultValue->args[0]->value;
                if ($expr instanceof String_) {
                    $this->default = $expr->value;
                }
            }
        }
    }

    /**
     * @param int $flags 
     * @return void
     */
    public function parseFlags(int $flags)
    {
        $_flags = Type::parseModifiers($flags);
        foreach ($_flags as $flag) {
            if ($flag == Modifiers::PROTECTED) {
                $this->isProtected = true;
            } else if ($flag == Modifiers::PRIVATE) {
                $this->isPrivate = true;
            } else if ($flag == Modifiers::STATIC) {
                $this->isStatic = true;
            }
        }
    }

    /**
     * Summary of completePropWithDoc
     * @param PHPDoc $doc
     * @return void
     */
    public function completePropWithDoc(PHPDoc $doc)
    {
        if (isset($doc->documentation) && $doc->documentation != '') {
            $this->description = $doc->documentation;
        }
        if (isset($doc->vars[""])) {
            $this->type = $doc->vars['']->type;
            if ($doc->vars['']->description) {
                $this->description = $doc->vars['']->description;
            }
        } else if (isset($doc->vars[$this->name])) {
            $this->type = $doc->vars[$this->name]->type;
            if ($doc->vars['']->description) {
                $this->description = $doc->vars['']->description;
            }
        }
    }

    public function hasAttribute(string $name): bool
    {
        return Type::hasAttribute($this->attrGroups, $name, $this->class->uses, $this->class->namespace);
    }
    public function getAttribute(string $name): Attribute|null
    {
        return Type::getAttribute($this->attrGroups, $name, $this->class->uses, $this->class->namespace);
    }
}
