<?php

namespace Aventus\Transpiler\Parser;

/**
 */
class PHPGeneric
{
    public function __construct(
        public string $name,
        public ?PHPType $constraint,
        public ?PHPType $default,
    ) {}


    public function toPHPType(): PHPType
    {
        $type = new PHPType();
        $type->name = $this->name;
        $type->fullname = $this->name;
        $type->defaultGenericValue = $this->default;
        if ($this->constraint)
            $type->constraintsGeneric = [$this->constraint];
        $type->isTypeParameter = true;
        return $type;
    }
}
