<?php

namespace Aventus\Transpiler\Parser;

use PhpParser\Node\UseItem;



/**
 * Summary of PHPType
 */
class PHPUse
{
    public function __construct(
        public string $identifier,
        public string $fullname,
        public ?string $alias = null

    ) {
    }

    public function getName()
    {
        return $this->alias ?? $this->identifier;
    }

    public static function parseCode(UseItem $use): PHPUse
    {
        return new PHPUse(
            $use->name->getLast(),
            $use->name->toString(),
            $use->alias?->name
        );
    }
}