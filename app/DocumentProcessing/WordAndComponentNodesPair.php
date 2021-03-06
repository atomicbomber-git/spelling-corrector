<?php


namespace App\DocumentProcessing;


class WordAndComponentNodesPair
{
    /** @var array|ComponentNode[] */
    public array $componentNodes;
    public string $word;

    public function __construct(
        string $word,
        array $componentNodes
    )
    {
        $this->word = $word;
        $this->componentNodes = $componentNodes;
    }
}