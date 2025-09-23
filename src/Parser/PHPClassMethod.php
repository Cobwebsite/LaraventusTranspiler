<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Transpiler\Tools\Type;
use PhpParser\Modifiers;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Attribute;

/**
 * @property AttributeGroup[] $attrGroups
 * @property PHPClassMethodCtrlInfo[] $ctrlInfo
 */
class PHPClassMethod
{

    /**
     * @param ClassMethod $info
     */
    public static function parse(ClassMethod $info, PHPClass $phpClass): PHPClassMethod
    {
        $doc = PHPDoc::parse($info->getDocComment()?->getText(), $phpClass);
        $result = new PHPClassMethod($phpClass, $info->name, $doc->documentation, $info->attrGroups);
        $result->parseFlags($info->flags);
        $result->return = PHPType::parseCode($info->returnType, $phpClass);
        if ($doc->return) {
            $result->return = $doc->return;
        }
        foreach ($info->params as $param) {
            $result->params[] = PHPParam::parse($param, $doc, $phpClass, $param->attrGroups);
        }
        return $result;
    }

    public bool $isPrivate = false;
    public bool $isProtected = false;
    public bool $isStatic = false;
    public bool $isAbstract = false;
    public bool $isExported = false;
    public array $ctrlInfo = [];
    public PHPType $type;

    public PHPType $return;

    /**
     * @var PHPParam[]
     */
    public array $params = [];

    public function __construct(
        public ?PHPClass $class,
        public string $name,
        public ?string $description,
        public array $attrGroups
    ) {
        $this->return = PHPType::any();
    }

    /**
     * @param int $flags 
     * @return void
     */
    public function parseFlags(int $flags)
    {
        $_flags = Type::parseModifiers($flags);
        foreach ($_flags as $flag) {
            if ($flag == Modifiers::PROTECTED ) {
                $this->isProtected = true;
            } else if ($flag == Modifiers::PRIVATE ) {
                $this->isPrivate = true;
            } else if ($flag == Modifiers::STATIC ) {
                $this->isStatic = true;
            } else if ($flag == Modifiers::ABSTRACT ) {
                $this->isAbstract = true;
            }
        }
    }

    /**
     * Summary of completePropWithDoc
     * @param PHPDoc $doc
     * @return void
     */
    public function completeWithDoc(PHPDoc $doc)
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