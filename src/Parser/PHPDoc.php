<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Tools\Console;
use PhpParser\Node\Stmt\Const_;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
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
     * @var array<string, PHPGeneric>
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
        $docParser = new CustomParser($config, $typeParser, $constExprParser);
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

        #region property
        $nodes = $phpDocNode->getPropertyTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $prop = new PHPClassPropriete($phpClass, $node->propertyName, $type, $node->description, null, []);
            $result->props[$prop->name] = $prop;
        }

        $nodes = $phpDocNode->getPropertyTagValues('@exportProperty');
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $prop = new PHPClassPropriete($phpClass, $node->propertyName, $type, $node->description, null, []);
            $result->props[$prop->name] = $prop;
        }

        $nodes = $phpDocNode->getTagsByName("@noExportProperty");
        foreach ($nodes as $node) {
            if ($node->value instanceof GenericTagValueNode) {
                $name = trim($node->value->value);
                if (str_starts_with($name, "$")) {
                    $name = substr($name, 1);
                }
                if (isset($result->props[$name])) {
                    $result->props[$name]->isNoExportForce = true;
                }
            }
        }
        #endregion


        #region variables
        $nodes = $phpDocNode->getVarTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->vars[$node->variableName] = new PHPVarComment(
                $node->variableName,
                $type,
                $node->description
            );
        }

        $nodes = $phpDocNode->getVarTagValues("@exportVar");
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->vars[$node->variableName] = new PHPVarComment(
                $node->variableName,
                $type,
                $node->description
            );
        }

        $nodes = $phpDocNode->getTagsByName("@noExportVar");
        foreach ($nodes as $node) {
            if ($node->value instanceof GenericTagValueNode) {
                $name = trim($node->value->value);
                if (str_starts_with($name, "$")) {
                    $name = substr($name, 1);
                }
                if (isset($result->vars[$name])) {
                    $result->vars[$name]->isNoExport = true;
                }
            }
        }
        #endregion

        #region params
        $nodes = $phpDocNode->getParamTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->params[$node->parameterName] = new PHPVarComment(
                $node->parameterName,
                $type,
                $node->description
            );
        }

        $nodes = $phpDocNode->getParamTagValues("@exportParam");
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->params[$node->parameterName] = new PHPVarComment(
                $node->parameterName,
                $type,
                $node->description
            );
        }

        $nodes = $phpDocNode->getTagsByName("@noExportParam");
        foreach ($nodes as $node) {
            if ($node->value instanceof GenericTagValueNode) {
                $name = trim($node->value->value);
                if (str_starts_with($name, "$")) {
                    $name = substr($name, 1);
                }
                if (isset($result->params[$name])) {
                    $result->params[$name]->isNoExport = true;
                }
            }
        }
        #endregion

        #region return
        $nodes = $phpDocNode->getReturnTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->return = $type;
        }

        $nodes = $phpDocNode->getReturnTagValues("@exportReturn");
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->return = $type;
        }

        $nodes = $phpDocNode->getTagsByName("@noExportReturn");
        foreach ($nodes as $node) {
            $result->return = null;
        }
        #endregion

        #region extends
        $nodes = $phpDocNode->getExtendsTagValues();
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            $result->extends[] = $type;
        }

        $nodes = $phpDocNode->getExtendsTagValues("@exportExtends");
        foreach ($nodes as $node) {
            $type = PHPType::parseDoc($node->type, $phpClass) ?? PHPType::any();
            if (count($result->extends) == 0) {
                $result->extends[] = $type;
            } else {
                $result->extends[0] = $type;
            }
        }

        $nodes = $phpDocNode->getTagsByName("@noExportExtends");
        foreach ($nodes as $node) {
            $result->extends = [];
        }
        #endregion

        #region templates
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
            $result->genericParameters[$node->name] = new PHPGeneric($node->name, $constraint, $default);
        }

        $nodes = $phpDocNode->getTemplateTagValues("@exportTemplate");
        foreach ($nodes as $node) {
            $constraint = null;
            if ($node->bound != null) {
                $constraint = PHPType::parseDoc($node->bound, $phpClass) ?? PHPType::any();
            }
            $default = null;
            if ($node->default != null) {
                $default = PHPType::parseDoc($node->default, $phpClass) ?? PHPType::any();
            }
            $result->genericParameters[$node->name] = new PHPGeneric($node->name, $constraint, $default);
        }

        $nodes = $phpDocNode->getTagsByName("@noExportTemplate");
        foreach ($nodes as $node) {
           if ($node->value instanceof GenericTagValueNode) {
                $name = trim($node->value->value);
                if (str_starts_with($name, "$")) {
                    $name = substr($name, 1);
                }
                if (isset($result->genericParameters[$name])) {
                    $result->genericParameters[$name]->isNoExport = true;
                }
            }
        }
        #endregion

        return $result;
    }
}
