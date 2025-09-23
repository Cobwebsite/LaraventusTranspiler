<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Attributes\NoExport;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;

/**
 * Summary of PHPEnum
 */
class PHPEnum extends PHPSymbol
{
    public static function parse(Enum_ $node, $namespace, array $useStatements): PHPEnum|null
    {
        $enumName = $node->name->name ?? 'AnonymousEnum';
        if (Type::hasAttribute($node->attrGroups, NoExport::class, $useStatements, $namespace)) {
            return null;
        }
        $fullName = $namespace . '\\' . $enumName;
        $result = self::get($fullName);
        if ($result == null) {
            $phpClass = new PHPEnum($node, $enumName, $namespace, $useStatements);
            $doc = PHPDoc::parse($node->getDocComment()?->getText(), $phpClass);
            if ($doc->documentation) {
                $phpClass->description = $doc->documentation;
            }
            self::$symbols[$fullName] = $phpClass;
            $result = $phpClass;
        }

        return $result;
    }

    /** @var array<string, any> */
    protected $values = [];



    protected function __construct(Enum_ $node, string $name, string $namespace = '', array $uses)
    {
        parent::__construct($node, $name, $namespace, $uses);
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof EnumCase) {
                $name = $stmt->name->name;
                $expr = $stmt->expr;
                $value = "";
                if ($expr instanceof ConstFetch) {
                    $value = $expr->name->name;
                }
                if ($expr instanceof String_) {
                    $value = '"' . $expr->value . '"';
                }
                if ($expr instanceof Int_) {
                    $value = $expr->value;
                }
                if ($expr instanceof Float_) {
                    $value = $expr->value;
                }

                $this->values[$name] = $value;
            }
        }
    }
}
