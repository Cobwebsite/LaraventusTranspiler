<?php

declare(strict_types=1);

namespace Aventus\Transpiler\Parser;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use LogicException;
use PHPStan\PhpDocParser\Ast;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\Doctrine;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\ShouldNotHappenException;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionMethod;

use function array_key_exists;
use function count;
use function rtrim;
use function str_replace;
use function trim;

class CustomParser extends PhpDocParser
{

    public function __construct(
        ParserConfig $config,
        private TypeParser $typeParser,
        ConstExprParser $constantExprParser
    ) {
        parent::__construct($config, $typeParser, $constantExprParser);
    }

    public function parseTagValue(TokenIterator $tokens, string $tag): Ast\PhpDoc\PhpDocTagValueNode
    {
        $customTags = [
            "@exportTemplate",
            "@exportReturn",
            "@exportExtends",
            "@exportProperty",
            "@exportVar",
            "@exportParam",
        ];

        if (in_array($tag, $customTags)) {
            $startLine = $tokens->currentTokenLine();
            $startIndex = $tokens->currentTokenIndex();

            try {
                $tokens->pushSavePoint();
                switch ($tag) {
                    case '@exportTemplate':
                        $tagValue = $this->typeParser->parseTemplateTagValue(
                            $tokens,
                            fn($tokens) => $this->parseOptionalDescription($tokens, true),
                        );
                        break;
                    case '@exportReturn':
                        $tagValue = $this->parseReturnTagValue($tokens);
                        break;
                    case '@exportExtends':
                        $tagValue = $this->parseExtendsTagValue('@extends', $tokens);
                        break;
                    case '@exportProperty':
                        $tagValue = $this->parsePropertyTagValue($tokens);
                        break;
                    case '@exportVar':
                        $tagValue = $this->parseVarTagValue($tokens);
                        break;
                    case '@exportParam':
                        $tagValue = $this->parseParamTagValue($tokens);
                        break;
                }

                $tokens->dropSavePoint();
            } catch (ParserException $e) {
                $tokens->rollback();
                $tagValue = new Ast\PhpDoc\InvalidTagValueNode($this->parseOptionalDescription($tokens, false), $e);
            }
            return $this->enrichWithAttributes($tokens, $tagValue, $startLine, $startIndex);
        } else {
            return parent::parseTagValue($tokens, $tag);
        }
    }

    /**
     * @param bool $limitStartToken true should be used when the description immediately follows a parsed type
     */
    private function parseOptionalDescription(TokenIterator $tokens, bool $limitStartToken): string
    {
        $method = new ReflectionMethod(PhpDocParser::class, 'parseOptionalDescription');
        $method->setAccessible(true);
        return $method->invoke($this, $tokens, $limitStartToken);
    }

    /**
     * @template T of Ast\Node
     * @param T $tag
     * @return T
     */
    private function enrichWithAttributes(TokenIterator $tokens, Ast\Node $tag, int $startLine, int $startIndex): Ast\Node
    {
        $method = new ReflectionMethod(PhpDocParser::class, 'enrichWithAttributes');
        $method->setAccessible(true);
        return $method->invoke($this, $tokens, $tag, $startLine, $startIndex);
    }

    private function parsePropertyTagValue(TokenIterator $tokens): Ast\PhpDoc\PropertyTagValueNode
    {
        $method = new ReflectionMethod(PhpDocParser::class, 'parsePropertyTagValue');
        $method->setAccessible(true);
        return $method->invoke($this, $tokens);
    }

    private function parseReturnTagValue(TokenIterator $tokens): Ast\PhpDoc\ReturnTagValueNode
    {
        $method = new ReflectionMethod(PhpDocParser::class, 'parseReturnTagValue');
        $method->setAccessible(true);
        return $method->invoke($this, $tokens);
    }

    private function parseExtendsTagValue(string $tagName, TokenIterator $tokens): Ast\PhpDoc\PhpDocTagValueNode
    {
        $method = new ReflectionMethod(PhpDocParser::class, 'parseExtendsTagValue');
        $method->setAccessible(true);
        return $method->invoke($this, $tagName, $tokens);
    }

    private function parseVarTagValue(TokenIterator $tokens): Ast\PhpDoc\VarTagValueNode
    {
        $method = new ReflectionMethod(PhpDocParser::class, 'parseVarTagValue');
        $method->setAccessible(true);
        return $method->invoke($this, $tokens);
    }

    /**
     * @return Ast\PhpDoc\ParamTagValueNode|Ast\PhpDoc\TypelessParamTagValueNode
     */
    private function parseParamTagValue(TokenIterator $tokens): Ast\PhpDoc\PhpDocTagValueNode
    {
        $method = new ReflectionMethod(PhpDocParser::class, 'parseParamTagValue');
        $method->setAccessible(true);
        return $method->invoke($this, $tokens);
    }
}
