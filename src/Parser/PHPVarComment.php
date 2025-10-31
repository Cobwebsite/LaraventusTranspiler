<?php

namespace Aventus\Transpiler\Parser;

use PhpParser\Node\UseItem;



/**
 * Summary of PHPType
 */
class PHPVarComment
{
    public bool $isNoExport = false;
    public function __construct(
        public string $identifier,
        public PHPType $type,
        public ?string $description = null
    ) {
    }


}