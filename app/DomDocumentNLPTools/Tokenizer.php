<?php


namespace App\DomDocumentNLPTools;

use DOMDocument;
use DOMNode;

class Tokenizer
{
    public DOMDocument $document;
    private array $words = [];

    private string $currentLetter = "";
    private ?DOMNode $currentLetterNode = null;

    private string $letterAccumulator = "";
    private array $letterNodeAccumulator = [];
    private ?DOMNode $prevLetterNode = null;
    private bool $newParagraphHandled = false;
    private int $wordIndex = 0;
    private ?DOMNode $currentParagraphNode = null;
    private bool $hasUnhandledLinebreak = false;

    private string $sentenceCharAccumulator = "";
    private string $currentSentenceChar = "";
    private string $prevSentenceChar = "";
    private array $sentences = [];
    private int $sentenceIndex = 0;
    private array $wordNodeCounter = [];
    private array $sentenceWordCounter = [];

    private ?Word $previousMultiNodeWord = null;
    private bool $squash = false;
    private int $multiNodeWordLastNodeTextLength = 0;
    private int $letterIndex = 0;

    public function load(DOMDocument $document)
    {
        $this->document = $document;
    }

    public function tokenize(): array
    {
        $this->walk($this->document, function (DOMNode $node) {
            if ($node->nodeName === "w:p") {
                $this->currentParagraphNode = $node;

                $this->walk($node, function (DOMNode $subNode) {
                    if ($this->hasUnhandledLinebreak) {
                        $this->saveWord();
                        $this->saveSentence();
                        $this->letterNodeAccumulator = [];
                        $this->hasUnhandledLinebreak = false;
                    }

                    if ($subNode->nodeName === "w:t") {
                        for ($i = 0; $i < mb_strlen($subNode->textContent); ++$i) {
                            $this->letterIndex = $i;
                            $currentChar = mb_substr($subNode->textContent, $i, 1);
                            $this->currentSentenceChar = $currentChar;

                            if (preg_match("/[^\p{Z}]/ui", $currentChar)) {
                                $this->currentLetter = $currentChar;
                                $this->currentLetterNode = $subNode;

                                if ($this->currentLetterNode !== $this->prevLetterNode) {
                                    if (($this->letterAccumulator === "")) {
                                        $this->letterNodeAccumulator = [];
                                    }

                                    $this->letterNodeAccumulator[] = $this->currentLetterNode;
                                }

                                $this->letterAccumulator .= $this->currentLetter;
                                $this->prevLetterNode = $this->currentLetterNode;
                                $this->newParagraphHandled = true;
                            } else {
                                $this->saveWord();
                            }

                            $this->sentenceCharAccumulator .= $this->currentSentenceChar;

                            if (($this->prevSentenceChar === ".") && ($this->currentSentenceChar === " ")) {
                                $this->saveSentence();
                            }

                            $this->prevSentenceChar = $this->currentSentenceChar;
                            $this->squash && $this->squashNodesInPreviousMultiNodeToken();
                        }
                    } elseif ($subNode->nodeName === "w:br") {
                        $this->hasUnhandledLinebreak = true;
                    }
                });

                $this->saveWord();
                $this->saveSentence();
                $this->letterNodeAccumulator = [];
            }
        });

        return $this->sentences;
    }

    public function tokenizeWithSquashing(): array
    {
        $this->squash = true;
        return $this->tokenize();
    }

    private function walk(DOMNode $node, callable $callback)
    {
        $callback($node);

        foreach ($node->childNodes as $childNode) {
            $this->walk($childNode, $callback);
        }
    }

    private function saveWord(): void
    {
        if ($this->letterAccumulator !== "") {
            $word = new Word(
                $this->letterAccumulator,
                $this->wordIndex++,
                $this->letterNodeAccumulator
            );

            $word->posInNode = $this->getNextWordPosInNode($word);
            $word->posInSentence = $this->getNextWordPosInSentence($word);
            $this->words[] = $word ;

            if (count($this->letterNodeAccumulator) > 1) {
                $this->letterNodeAccumulator = [array_pop($this->letterNodeAccumulator)];
                $this->previousMultiNodeWord = $word;
                $this->multiNodeWordLastNodeTextLength = $this->letterIndex;
            }

            $this->letterAccumulator = "";
        }
    }

    private function saveSentence(): void
    {
        $this->sentences[] = new Sentence(
            $this->sentenceCharAccumulator,
            $this->sentenceIndex++,
            $this->words
        );

        $this->words = [];
        $this->sentenceCharAccumulator = "";
    }

    private function squashNodesInPreviousMultiNodeToken(): void
    {
        if ($this->previousMultiNodeWord !== null) {
            $textAccumulator = "";
            $domNodes = $this->previousMultiNodeWord->nodes;
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

            $this->previousMultiNodeWord->nodes = [$firstDomNode];
            $this->previousMultiNodeWord = null;
        }
    }

    private function getNextWordPosInNode(Word $word)
    {
        $hash = spl_object_hash($this->letterNodeAccumulator[0]);
        $this->wordNodeCounter[$word->getNormalizedValue()][$hash] ??= 0;
        return $this->wordNodeCounter[$word->getNormalizedValue()][$hash]++;
    }

    private function getNextWordPosInSentence(Word $word): int
    {
        $this->sentenceWordCounter[$word->getNormalizedValue()] ??= 0;
        return $this->sentenceWordCounter[$word->getNormalizedValue()]++;
    }
}
