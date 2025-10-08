<?php

namespace Aventus\Transpiler\Config;

use Aventus\Transpiler\Tools\Type;

/** 
 * @property array<string, ProjectConfigReplacerInfo> $type
 * @property array<string, ProjectConfigReplacerInfo> $result
 */
class ProjectConfigReplacerPart
{
    public array $type = [];
    public array $result = [];

    public function prepare()
    {
        foreach ($this->type as $key => $v) {
            $this->type[$key] = Type::fromJson(ProjectConfigReplacerInfo::class, json_encode($v));
        }
        foreach ($this->result as $key => $v) {
            $this->result[$key] = Type::fromJson(ProjectConfigReplacerInfo::class, json_encode($v));
        }
    }
}
