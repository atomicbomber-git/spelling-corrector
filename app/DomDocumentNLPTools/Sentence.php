<?php


namespace App\DomDocumentNLPTools;

class Sentence {
    public string $value;
    public int $index;
    /** @var array | Word[] */
    public array $tokens;

    public function __construct(string $value, int $index, array $tokens)
    {
        $this->value = $value;
        $this->index = $index;
        $this->tokens = $tokens;
    }
}
