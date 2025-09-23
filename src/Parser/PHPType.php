<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

/**
 * Summary of PHPType
 * @property bool $isInternal
 * @property ?PHPSymbol $symbol
 */
class PHPType
{
    public string $name;
    public string $fullname;
    private ?PHPSymbol $_symbol = null;
    private ?bool $_isInternal = null;
    public bool $isNullable = false;
    public bool $isArray = false;
    /**
     * @var PHPType[] 
     */
    public array $unions = [];

    /**
     * @var PHPType[]
     */
    public array $generics = [];

    /**
     * @var PHPType[]
     */
    public array $constraintsGeneric = [];

    public ?PHPType $defaultGenericValue = null;

    /**
     * Is a type Parameter like &lt;T&gt;
     */
    public bool $isTypeParameter = false;

    public function __construct(?PHPSymbol $symbol = null)
    {
        // $this->_symbol = $symbol;
    }

    /**
     * Summary of parseDoc
     * @param TypeNode $node
     * @param PHPClass|PHPEnum $phpClass
     * @return PHPType|null
     */
    public static function parseDoc(TypeNode $node, PHPClass|PHPEnum $phpClass): PHPType|null
    {
        if ($node instanceof ArrayTypeNode) {
            $result = self::parseDoc($node->type, $phpClass);
            $result->isArray = true;
            return $result;
        }

        if ($node instanceof NullableTypeNode) {
            $result = self::parseDoc($node->type, $phpClass);
            $result->isNullable = true;
            return $result;
        }

        if ($node instanceof UnionTypeNode) {
            $result = new PHPType();
            foreach ($node->types as $type) {
                $temp = self::parseDoc($type, $phpClass);
                if ($temp) {
                    $result->unions[] = $temp;
                }
            }
            return $result;
        }

        if ($node instanceof GenericTypeNode) {
            $genericNode = $node;
            $result = self::parseDoc($genericNode->type, $phpClass);
            foreach ($genericNode->genericTypes as $type) {
                $temp = self::parseDoc($type, $phpClass);
                if ($temp) {
                    $result->generics[] = $temp;
                }
            }
            return $result;
        }

        if ($node instanceof IdentifierTypeNode) {
            $type = new PHPType();
            // TODO check if starts with \ => extract namespace to name
            $type->name = $node->name;
            if (isset($phpClass->uses[$type->name])) {
                $type->fullname = $phpClass->uses[$type->name]->fullname;
            } else if (str_starts_with($node->name, "\\")) {
                $type->fullname = substr($node->name, 1);
            } else if (Type::isNative($node->name)) {
                $type->fullname = $node->name;
            } else {
                $type->fullname = $phpClass->namespace . '\\' . $node->name;
            }
            return $type;
        }

        return null;
    }
    /**
     * Summary of parseCode
     * @param ?Node $type
     * @return PHPType|null
     */
    public static function parseCode(?Node $node, PHPClass|PHPEnum $phpClass): PHPType|null
    {
        if (!$node) {
            return self::any();
        }

        if ($node instanceof Name) {
            $type = new PHPType();
            $type->name = $node->name;
            if (isset($phpClass->uses[$type->name])) {
                $type->fullname = $phpClass->uses[$type->name]->fullname;
            } else if (str_starts_with($node->name, "\\")) {
                $type->fullname = substr($node->name, 1);
            } else if (Type::isNative($node->name)) {
                $type->fullname = $node->name;
            } else {
                $type->fullname = $phpClass->namespace . '\\' . $node->name;
            }
            return $type;
        }

        if ($node instanceof Identifier) {
            $type = new PHPType();
            $type->name = $node->name;
            $type->fullname = $node->name;
            return $type;
        }
        if ($node instanceof NullableType) {
            $type = self::parseCode($node->type, $phpClass);
            if ($type) {
                $type->isNullable = true;
            }
            return $type;
        }

        if ($node instanceof UnionType) {
            $result = new PHPType();
            foreach ($node->types as $type) {
                $temp = self::parseCode($type, $phpClass);
                if ($temp) {
                    $result->unions[] = $temp;
                }
            }
            return $result;
        }

        Console::log("PHPTYPE ligne 162");
        Console::dump($node);
        die();
        return null;
    }

    public static function any()
    {
        $result = new PHPType();
        $result->name = "any";
        $result->fullname = "any";
        return $result;
    }
    public static function number()
    {
        $result = new PHPType();
        $result->name = "number";
        $result->fullname = "number";
        return $result;
    }
    public static function string()
    {
        $result = new PHPType();
        $result->name = "string";
        $result->fullname = "string";
        return $result;
    }

    public static function void()
    {
        $result = new PHPType();
        $result->name = "void";
        $result->fullname = "void";
        return $result;
    }

    public function __get($name)
    {
        if ($name == "isInternal") {
            if ($this->_isInternal == null) {
                $this->_isInternal = Parser::$config->isInternal($this->fullname);
            }
            return $this->_isInternal;
        }
        if ($name == "symbol") {
            if ($this->_symbol == null) {
                $this->_symbol = ClassManager::getInstance()->loadSymbolByFullname($this->fullname);
            }
            return $this->_symbol;
        }
        return null;
    }
}
