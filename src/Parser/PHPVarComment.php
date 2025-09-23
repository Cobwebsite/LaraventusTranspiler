<?php

namespace Aventus\Transpiler\Parser;

use PhpParser\Node\UseItem;



/**
 * Summary of PHPType
 */
class PHPVarComment
{
    public function __construct(
        public string $identifier,
        public PHPType $type,
        public ?string $description = null
    ) {
    }


}