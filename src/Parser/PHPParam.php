<?php

namespace Aventus\Transpiler\Parser;

use Attribute;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\UseItem;



/**
 * @property AttributeGroup[] $attrGroups
 */
class PHPParam
{
    /**
     * @param Param $param
     * @param PHPDoc $doc
     */
    public static function parse(Param $param, PHPDoc $doc, PHPClass $phpClass, array $attrGroups): PHPParam
    {
        $doc = PHPDoc::parse($param->getDocComment()?->getText(), $phpClass);
        $name = $param->var->name;

        $type = PHPType::parseCode($param->type, $phpClass) ?? PHPType::any();
        $result = new PHPParam($phpClass, $name, $type, $attrGroups);

        if (isset($doc->params[$name])) {
            $result->description = $doc->params[$name]->description;
            if (isset($doc->params[$name]->type)) {
                $result->type = $doc->params[$name]->type;
            }
        }

        return $result;
        // $param->default?
    }

    public ?string $description = null;
    public ?string $defaultValue = null;

    public function __construct(
        public PHPClass $class,
        public string $name,
        public PHPType $type,
        public array $attrGroups
    ) {}

    public function hasAttribute(string $name): bool
    {
        return Type::hasAttribute($this->attrGroups, $name, $this->class->uses, $this->class->namespace);
    }

    public function getAttribute(string $name): Attribute|null
    {
        return Type::getAttribute($this->attrGroups, $name, $this->class->uses, $this->class->namespace);
    }
}
