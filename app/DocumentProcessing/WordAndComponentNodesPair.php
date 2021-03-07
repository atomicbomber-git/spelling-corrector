<?php


namespace App\DocumentProcessing;


class WordAndComponentNodesPair
{
    /** @var array|ComponentNode[] */
    public array $componentNodes;
    public string $word;
    public ?string $sentence;
    public int $wordPosInSentence;
    public int $sentenceIndex;

    public function __construct(
        string $word,
        array $componentNodes,
        int $sentenceIndex,
        string $sentence = null,
        int $wordPosInSentence = 0
    )
    {
        $this->word = $word;
        $this->componentNodes = $componentNodes;
        $this->sentenceIndex = $sentenceIndex;
        $this->sentence = $sentence;
        $this->wordPosInSentence = $wordPosInSentence;
    }
}