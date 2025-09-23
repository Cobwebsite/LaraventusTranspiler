<?php

namespace Aventus\Transpiler\Parser;

use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Writer\FileToWrite;
use Aventus\Transpiler\Parser\Parser as ParserParser;
use Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\Parser as RealParser;

class ClassManager
{

    private static ClassManager|null $instance = null;

    public static function getInstance(): ClassManager
    {
        if (self::$instance == null) {
            self::$instance = new ClassManager();
        }
        return self::$instance;
    }

    private RealParser $parser;
    private function __construct()
    {
        $factory = new ParserFactory();
        $this->parser = $factory->createForVersion(PhpVersion::fromString("8.3"));
    }

    /**
     * key is fullname
     * @var array<string, PHPSymbol>
     */
    private array $symbols = [];

    /**
     * List of uri loaded
     * @var string[]
     */
    private array $uriLoaded = [];


    public function quickLoadSymbol(string $uri)
    {
        if (in_array($uri, $this->uriLoaded)) return;
        $this->uriLoaded[] = $uri;
        $code = file_get_contents($uri);
        $traverser = new NodeTraverser();
        $visitor = new FileVisitor();
        $visitor->isQuick = true;
        $visitor->uri = $uri;

        $traverser->addVisitor($visitor);

        try {
            $ast = $this->parser->parse($code, new Collecting());
            if ($ast) {
                $traverser->traverse($ast);
                foreach ($visitor->symbols as $_class) {
                    $this->symbols[$_class->getFullname()] = $_class;
                }
            }
        } catch (Error $e) {
            echo "Erreur d'analyse : ", $e->getMessage(), "\n";
        }
    }
    public function completeSymbols()
    {
        $traverser = new NodeTraverser();
        $visitor = new FileVisitor();
        $traverser->addVisitor($visitor);
        foreach ($this->uriLoaded as $uri) {
            $visitor->uri = $uri;
            $code = file_get_contents($uri);
            // Console::log($uri);
            try {
                $ast = $this->parser->parse($code, new Collecting());
                if ($ast) {
                    $traverser->traverse($ast);
                    foreach ($visitor->symbols as $_class) {
                        $this->symbols[$_class->getFullname()] = $_class;
                    }
                }
            } catch (Error $e) {
                echo "Erreur d'analyse : ", $e->getMessage(), "\n";
            }
        }
    }

    public function completeSymbol($uri)
    {
        $traverser = new NodeTraverser();
        $visitor = new FileVisitor();
        $visitor->uri = $uri;
        $traverser->addVisitor($visitor);
        $code = file_get_contents($uri);

        try {
            $ast = $this->parser->parse($code, new Collecting());
            if ($ast) {
                $traverser->traverse($ast);
                foreach ($visitor->symbols as $_class) {
                    $this->symbols[$_class->getFullname()] = $_class;
                }
            }
        } catch (Error $e) {
            echo "Erreur d'analyse : ", $e->getMessage(), "\n";
        }
    }


    public function loadSymbolByFullname(string $fullname): PHPSymbol|null
    {
        $config = ParserParser::$config;
        if (isset($this->symbols[$fullname])) {
            if ($this->symbols[$fullname]->isOnlyQuick) {
                $uri = $config->namespaceToUri($fullname);
                if (!$uri) {
                    return null;
                }
                $this->completeSymbol($uri);
            }
            return $this->symbols[$fullname];
        } else {
            $uri = $config->namespaceToUri($fullname);
            if ($uri) {
                $this->quickLoadSymbol($uri);
                $this->completeSymbol($uri);
                if (isset($this->symbols[$fullname])) {
                    return $this->symbols[$fullname];
                }
            }
        }
        return null;
    }


    public function symbolsToContainers()
    {

        foreach ($this->symbols as $fullname => $_class) {

            FileToWrite::registerType($_class);
        }
    }
}
