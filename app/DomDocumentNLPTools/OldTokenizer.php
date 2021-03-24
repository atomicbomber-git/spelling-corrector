<?php

namespace App\DomDocumentNLPTools;

use DOMDocument;
use DOMNode;

class OldTokenizer
{
    private DOMDocument $document;
    private string $textAccumulator = "";

    /** @var array | Word[] */
    private array $words = [];
    private int $wordIndex = 0;

    private ?DOMNode $prevLetterTextNode = null;

    /** @var array | DOMNode[] */
    private array $textNodeAccumulator = [];

    private ?Word $prevMultiNodeWord = null;
    private int $charIndex = 0;
    private string $prevChar = "";
    private string $currChar = "";
    private int $multiNodeWordLastNodeTextLength = 0;
    private bool $hasEncounteredLetterInCurrentTextNode = false;

    private int $sentenceIndex = 0;
    private string $sentenceAccumulator = "";
    /** @var array | Sentence[] */
    private array $sentences = [];

    private ?DOMNode $prevNode;
    private bool $hasUnhandledLinebreak = false;

    /** @var array | int */
    private array $wordPositionsInSentence = [];
    private array $wordPositionsInNode = [];
    private int $textNodeIndex = 0;
    private bool $squashNodes = false;
    private int $prevCharIndex = 0;

    public function load(DOMDocument $document)
    {
        $this->document = $document;
    }

    public function tokenizeWithSquashing(): array
    {
        $this->squashNodes = true;
        return $this->tokenize();
    }

    public function tokenize(): array
    {
        $this->walk($this->document, function (DOMNode $node) {
            if ($node->nodeName === "w:p") {
                $this->textAccumulator = "";
                $this->textNodeAccumulator = [];
                $this->sentenceAccumulator = "";

                $this->walk($node, function (DOMNode $subNode) {
                    if ($subNode->nodeName === "w:t") {
                        $this->hasEncounteredLetterInCurrentTextNode = false;

                        if ($this->textAccumulator === "") {
                            $this->textNodeAccumulator = [$subNode];
                        } else {
                            $this->textNodeAccumulator[] = $subNode;
                        }

                        if ($this->hasUnhandledLinebreak) {
                            $this->textNodeIndex--;
                            $this->saveWord();
                            $this->saveSentence();
                            $this->textNodeIndex++;
                            $this->hasUnhandledLinebreak = false;
                        }

                        if ($this->squashNodes) {
                            $this->squashNodesInPreviousMultiNodeWord();
                        }

                        for ($this->charIndex = 0; $this->charIndex < mb_strlen($subNode->textContent); ++$this->charIndex) {
                            $this->currChar = mb_substr($subNode->textContent, $this->charIndex, 1);

                            /* Non-separators */
                            if (preg_match("/[^\p{Z}]/ui", $this->currChar)) {
                                $this->hasEncounteredLetterInCurrentTextNode = true;
                                $this->textAccumulator .= $this->currChar;
                                $this->prevLetterTextNode = $subNode;
                            }
                            else {
                                $this->saveWord();
                            }

                            if ($this->charIsSeparator($this->currChar) && ($this->prevChar === ".")) {
                                $this->saveSentence();
                            }

                            $this->sentenceAccumulator .= $this->currChar;
                            $this->prevChar = $this->currChar;
                            $this->prevCharIndex = $this->charIndex;
                        }

                        ++$this->textNodeIndex;
                    } elseif ($subNode->nodeName === "w:br") {
                        $this->hasUnhandledLinebreak = true;
                    }
                    $this->prevNode = $subNode;
                });

                $this->textNodeIndex--;
                $this->saveWord();
                $this->textNodeIndex++;

                $this->squashNodes && $this->squashNodesInPreviousMultiNodeWord();
            }

            $this->saveSentence();
        });

        /* TODO: Hacky and ugly, find other solutions */
        if ($this->squashNodes) {
            $skips = [];
            foreach ($this->sentences as $sentence) {
                foreach ($sentence->words as $word) {
                    $value = $word->getNormalizedValue();
                    $hash = spl_object_hash($word->nodes[0]);

                    $skips[$value][$hash] ??= 0;
                    $word->posInNode = $skips[$value][$hash];
                    ++$skips[$value][$hash];
                }
            }
        }

        return $this->sentences;
    }

    private function walk(DOMNode $node, callable $callable)
    {
        $callable($node);
        foreach ($node->childNodes as $childNode) {
            $this->walk($childNode, $callable);
        }
    }

    public function saveWord(): void
    {
        if ($this->textAccumulator !== "") {
            $nodesToBeSaved = $this->textNodeAccumulator;

            if (!$this->hasEncounteredLetterInCurrentTextNode) {
                $this->textNodeAccumulator = [array_pop($nodesToBeSaved)];
            } else {
                $this->textNodeAccumulator = [array_pop($this->textNodeAccumulator)];
            }

            $newWord = new Word($this->textAccumulator, $this->wordIndex++, $nodesToBeSaved);
            $newWord->posInSentence = $this->getAndIncrementWordPositionInSentence($newWord->getNormalizedValue());
            $textNodeIndex = $this->textNodeIndex - count($this->textNodeAccumulator) + 1;
            $newWord->posInNode = $this->getAndIncrementWordPositionInTextNode($textNodeIndex, $newWord->getNormalizedValue());

            $this->words[] = $newWord;
            $this->textAccumulator = "";

            if (count($newWord->nodes) > 1) {
                $this->prevMultiNodeWord = $newWord;
                $this->multiNodeWordLastNodeTextLength =
                    $this->hasEncounteredLetterInCurrentTextNode ?
                        $this->charIndex : ($this->prevCharIndex + 1);
            }
        }
    }

    private function getAndIncrementWordPositionInSentence(string $normalizedWordValue): int
    {
        $this->wordPositionsInSentence[$normalizedWordValue] ??= 0;
        $pos = $this->wordPositionsInSentence[$normalizedWordValue];
        ++$this->wordPositionsInSentence[$normalizedWordValue];
        return $pos;
    }

    private function getAndIncrementWordPositionInTextNode(int $textNodeIndex, string $normalizedWordValue): int
    {
        $this->wordPositionsInNode[$textNodeIndex][$normalizedWordValue] ??= 0;
        $pos = $this->wordPositionsInNode[$textNodeIndex][$normalizedWordValue];
        ++$this->wordPositionsInNode[$textNodeIndex][$normalizedWordValue];
        return $pos;
    }

    public function saveSentence(): void
    {
        if ($this->sentenceAccumulator !== "") {
            $this->sentences[$this->sentenceIndex++] ??= new Sentence(
                $this->sentenceAccumulator,
                $this->sentenceIndex,
                $this->words,
            );

            $this->words = [];
            $this->sentenceAccumulator = "";
            $this->wordPositionsInSentence = [];
        }
    }

    public function squashNodesInPreviousMultiNodeWord(): void
    {
        if ($this->prevMultiNodeWord !== null) {
            $textAccumulator = "";
            $domNodes = $this->prevMultiNodeWord->nodes;
            $lastDomNode = $domNodes[array_key_last($domNodes)];
            $firstDomNode = $domNodes[array_key_first($domNodes)];

            for ($i = 1; $i < (count($domNodes) - 1); ++$i) {
                $textAccumulator .= $domNodes[$i]->textContent;
                if ($domNodes[$i]->parentNode !== null) {
                    $domNodes[$i]->parentNode->removeChild($domNodes[$i]);
                }
            }

            $textAccumulator .= mb_substr($lastDomNode->textContent, 0, $this->multiNodeWordLastNodeTextLength);
            $lastDomNode->textContent = mb_substr($lastDomNode->textContent, $this->multiNodeWordLastNodeTextLength);
            $firstDomNode->textContent .= $textAccumulator;

            if (mb_strlen($lastDomNode->textContent) === 0) {
                $lastDomNode->parentNode->removeChild($lastDomNode);
            }

            $this->prevMultiNodeWord->nodes = [$firstDomNode];
            $this->prevMultiNodeWord = null;
        }
    }

    public function charIsSeparator(string $char): bool
    {
        return (preg_match("/[\p{Z}]/ui", $char) > 0);
    }
}
