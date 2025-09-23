<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Tools\Console;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Summary of PHPDoc
 */
class PHPDoc
{

    public ?string $documentation = null;
    /**
     * Summary of props
     * @var array<string, PHPClassPropriete>
     */
    public array $props = [];
    /**
     * Summary of vars
     * @var array<string, PHPVarComment>
     */
    public array $vars = [];
    /**
     * Summary of params
     * @var array<string, PHPVarComment>
     */
    public array $params = [];

    public PHPType|null $return = null;
    /**
     * @var PHPType[]
     */
    public array $extends = [];

    /**
     * @var PHPGeneric[]
     */
    public array $genericParameters = [];

    /**
     * Summary of parse
     * @param ?string $docComment
     * @param PHPClass $phpClass
     * @return PHPDoc
     */
    public static function parse(?string $docComment, PHPClass|PHPEnum $phpClass)
    {
        $result = new PHPDoc();
        if ($docComment == null)
            return $result;

        $config = new ParserConfig(usedAttributes: []);
        $lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $docParser = new PhpDocParser($config, $typeParser, $constExprParser);

        $tokens = new TokenIterator($lexer->tokenize($docComment));
        $phpDocNode = $docParser->parse($tokens); // PhpDocNode


        foreach ($phpDocNode->children as $child) {
            if ($child instanceof PhpDocTextNode) {
                if ($result->documentation == null) {
                    $result->documentation = "" . $child->text;
                } else {
                    $result->documentation .= "\r\n" . $child->text;
                }
            }
        }

        $nodes = $phpDocNode->getPropertyTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $prop = new PHPClassPropriete($phpClass, $node->propertyName, $type, $node->description, null, []);
            $result->props[$prop->name] = $prop;
        }

        $nodes = $phpDocNode->getVarTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->vars[$node->variableName] = new PHPVarComment(
                $node->variableName,
                $type,
                $node->description
            );
        }

        $nodes = $phpDocNode->getParamTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->params[$node->parameterName] = new PHPVarComment(
                $node->parameterName,
                $type,
                $node->description
            );
        }

        $nodes = $phpDocNode->getReturnTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->return = $type;
        }

        $nodes = $phpDocNode->getExtendsTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->extends[] = $type;
        }

        $nodes = $phpDocNode->getTemplateTagValues();
        foreach ($nodes as $node) {
            $constraint = null;
            if ($node->bound != null) {
                $constraint = PHPType::parseDoc($node->bound, $phpClass) ?? PHPType::any();
            }
            $default = null;
            if ($node->default != null) {
                $default = PHPType::parseDoc($node->default, $phpClass) ?? PHPType::any();
            }
            $result->genericParameters[] = new PHPGeneric($node->name, $constraint, $default);
        }

        return $result;
    }
}
