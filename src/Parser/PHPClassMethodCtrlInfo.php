<?php

namespace Aventus\Transpiler\Parser;

/**
 * @property string $uri
 * @property string[] $methods
 * @property array<string, string> $parameters
 */
class PHPClassMethodCtrlInfo
{

    public function __construct(
        public string $uri,
        public array $methods,
        public array $parameters,
    ) {}
}
