<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Attributes\NoExport;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Tools\Type;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UseItem;

class FileVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, PHPUse> $useStatements
     */
    public array $useStatements = [];
    /**
     * @var PHPSymbol[] $symbols
     */
    public array $symbols = [];
    public string $uri = "";
    public bool $isQuick = false;
    public string $currentNamespace = '';

    public function __construct()
    {
        $this->useStatements = [];
    }

    public function enterNode(Node $node)
    {
        // Détecter les namespaces
        if ($node instanceof Namespace_) {
            $this->currentNamespace = $node->name->toString();
        } else if ($node instanceof UseItem) {

            $useTemp = PHPUse::parseCode($node);
            $this->useStatements[$useTemp->getName()] = $useTemp;
        }
        // Vérifier si le noeud est une déclaration de classe
        else if ($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_) {
            $phpClass = PHPClass::parse($node, $this->currentNamespace, $this->useStatements);
            if (!$phpClass) return;
            if (isset($node->flags))
                $phpClass->parseFlags(Type::parseModifiers($node->flags));

            $phpClass->uri = $this->uri;
            $phpClass->isOnlyQuick = $this->isQuick;
            if (!$this->isQuick) {

                $doc = PHPDoc::parse($node->getDocComment()?->getText(), $phpClass);

                $phpClass->generics = $doc->genericParameters;
                $phpClass->type->generics = [];
                foreach ($doc->genericParameters as $name => $genericParameter) {
                    if ($genericParameter->isNoExport) continue;
                    $phpClass->type->generics[] = $genericParameter->toPHPType();
                }

                if ($node instanceof Class_) {
                    if (count($doc->extends) == 1) {
                        $name = $doc->extends[0]->name;
                        $symbol = Type::getSymbol($name, $this->useStatements, $this->currentNamespace);
                        if ($symbol) {
                            if (count($doc->extends[0]->generics) > 0) {
                                $symbol = $symbol->makeGenerics(...$doc->extends[0]->generics);
                            }
                            if ($symbol instanceof PHPClass) {
                                $phpClass->parent = $symbol;
                            }
                        }
                    } else if ($node->extends) {
                        $symbol = Type::getSymbol($node->extends->name, $this->useStatements, $this->currentNamespace);
                        if ($symbol instanceof PHPClass) {
                            $phpClass->parent = $symbol;
                        }
                    }
                }
                if ($node instanceof Interface_ || $node instanceof Class_) {
                    foreach ($node->implements as $implement) {
                        $symbol = Type::getSymbol($implement->name, $this->useStatements, $this->currentNamespace);
                        if ($symbol instanceof PHPClass) {
                            $phpClass->interfaces[] = $symbol;
                        }
                    }
                }

                $properties = $doc->props;

                foreach ($node->getMethods() as $method) {
                    $name = $method->name;
                    $doc = PHPDoc::parse($method->getDocComment()?->getText(), $phpClass);
                    if ($name == "__construct") {
                        if (count($method->params) > 0) {
                            foreach ($method->params as $param) {
                                $name = $param->var->name;
                                $modifiers = Type::parseModifiers($param->flags);
                                if (
                                    in_array(Modifiers::PUBLIC, $modifiers) ||
                                    in_array(Modifiers::PROTECTED, $modifiers) ||
                                    in_array(Modifiers::PRIVATE, $modifiers)
                                ) {
                                    // no export
                                    if (Type::hasAttribute($param->attrGroups, NoExport::class, $this->useStatements, $this->currentNamespace))
                                        continue;

                                    // already exist
                                    if (array_key_exists($name, $properties))
                                        continue;

                                    $doc = PHPDoc::parse($param->getDocComment()?->getText(), $phpClass);
                                    $type = PHPType::parseCode($param->type, $phpClass) ?? PHPType::any();
                                    $prop = new PHPClassPropriete($phpClass, $name, $type, $doc->documentation, null, []);
                                    $prop->parseFlags($param->flags);
                                    $prop->completePropWithDoc($doc);
                                    $properties[$prop->name] = $prop;
                                }
                            }
                        }

                        $phpClass->cst = PHPClassMethod::parse($method, $phpClass);
                    } else {
                        $m = PHPClassMethod::parse($method, $phpClass);
                        $phpClass->methods[$m->name] = $m;
                    }
                }


                // Parcourir les propriétés de la classe
                foreach ($node->getProperties() as $property) {
                    foreach ($property->props as $prop) {
                        $name = $prop->name->name;
                        if (array_key_exists($name, $properties))
                            continue;

                        $type = PHPType::parseCode($property->type, $phpClass) ?? PHPType::any();
                        $doc = PHPDoc::parse($property->getDocComment()?->getText(), $phpClass);


                        $classProp = new PHPClassPropriete($phpClass, $name, $type, $doc->documentation, $prop->default, $property->attrGroups);
                        $classProp->parseFlags($property->flags);
                        $classProp->completePropWithDoc($doc);

                        $properties[$classProp->name] = $classProp;
                    }
                }

                foreach ($node->getConstants() as $constants) {
                    foreach ($constants->consts as $constant) {
                        $name = $constant->name->name;
                        if (array_key_exists($name, $properties))
                            continue;

                        $type = PHPType::any();
                        if ($constant->value instanceof Int_ || $constant->value instanceof Float_) {
                            $type = PHPType::number();
                        } else if ($constant->value instanceof String_) {
                            $type = PHPType::string();
                        }
                        $doc = PHPDoc::parse($constants->getDocComment()?->getText(), $phpClass);

                        $classProp = new PHPClassPropriete($phpClass, $name, $type, $doc->documentation, $constant->value, $constants->attrGroups);
                        $classProp->parseFlags($constants->flags);
                        $classProp->isStatic = true;
                        $classProp->completePropWithDoc($doc);

                        $properties[$classProp->name] = $classProp;
                    }
                }

                $phpClass->properties = $properties;

                foreach ($node->getTraitUses() as $traitUses) {
                    foreach ($traitUses->traits as $trait) {
                        $fullname = Type::getFullname($trait->name, $this->useStatements, $this->currentNamespace);
                        $result = ClassManager::getInstance()->loadSymbolByFullname($fullname);
                        if ($result instanceof PHPClass) {
                            $phpClass->traits[] = $result;
                        }
                    }
                }
            }

            $this->symbols[] = $phpClass;
        } else if ($node instanceof Enum_) {
            $phpEnum = PHPEnum::parse($node, $this->currentNamespace, $this->useStatements);
            if (!$phpEnum) return;
            $phpEnum->uri = $this->uri;
            $this->symbols[] = $phpEnum;
        }
    }
}
