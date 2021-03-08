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
    public string $rawWord;
    public int $index;

    public function __construct(
        int $index,
        string $word,
        string $rawWord,
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
        $this->rawWord = $rawWord;
        $this->index = $index;
    }
}