<?php


namespace App\DomDocumentNLPTools;

use DOMNode;

class Token {
    public string $rawValue;
    /** @var array | DOMNode[] */
    public array $nodes;
    public int $index;
    public int $posInSentence;
    public int $posInNode;

    public function __construct(string $rawValue, int $index, array $nodes)
    {
        $this->rawValue = $rawValue;
        $this->nodes = $nodes;
        $this->index = $index;
    }

    public function getNormalizedValue(): string
    {
        return mb_strtolower($this->rawValue);
    }
}