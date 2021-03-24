<?php


namespace App\DomDocumentNLPTools;

class Sentence {
    public string $value;
    public int $index;
    /** @var array | Word[] */
    public array $words;

    public function __construct(string $value, int $index, array $words)
    {
        $this->value = $value;
        $this->index = $index;
        $this->words = $words;
    }
}
