<?php

namespace Aventus\Transpiler\Writer;

class FileWriterHelper
{
    protected int $indent = 0;
    protected array $content = [];

    public function getIndentedText(string $txt): string
    {
        return str_repeat("\t", $this->indent) . $txt;
    }

    public function addTxt(string|array $txt, ?array &$result = null): void
    {
        if ($result === null) {
            $result = &$this->content;
        }

        if (is_array($txt)) {
            foreach ($txt as $line) {
                $this->addTxt($line, $result);
            }
        } else {
            $line = str_repeat("\t", $this->indent) . $txt;
            $result[] = $line;
        }
    }

    public function addTxtOpen(string $txt, ?array &$result = null): void
    {
        if ($result === null) {
            $result = &$this->content;
        }

        $this->addTxt($txt, $result);
        $this->indent++;
    }

    public function addTxtClose(string $txt, ?array &$result = null): void
    {
        if ($result === null) {
            $result = &$this->content;
        }

        $this->indent--;
        $this->addTxt($txt, $result);
    }

    public function addIndent(): void
    {
        $this->indent++;
    }

    public function removeIndent(): void
    {
        $this->indent--;
    }

    public function getContent(): string
    {
        return implode("\r\n", $this->content);
    }
}
