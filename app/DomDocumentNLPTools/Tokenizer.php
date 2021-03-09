<?php


namespace App\DomDocumentNLPTools;

use DOMDocument;
use DOMNode;

class Tokenizer {
    private DOMDocument $document;
    private string $textAccumulator = "";

    /** @var array | Token[] */
    private array $tokens = [];
    private int $tokenIndex = 0;

    private ?DOMNode $prevLetterTextNode = null;
    private array $textNodeAccumulator = [];

    private ?Token $prevMultiNodeToken = null;
    private int $charIndex = 0;
    private string $prevChar = "";
    private string $currChar = "";
    private int $multiNodeTokenLastNodeTextLength = 0;
    private bool $hasEncounteredLetterInCurrentTextNode = false;

    private int $sentenceIndex = 0;
    private string $sentenceAccumulator = "";
    /** @var array | Sentence[] */
    private array $sentences = [];

    private ?DOMNode $prevNode;
    private bool $hasUnhandledLinebreak = false;

    /** @var array | int */
    private array $tokenPositionsInSentence = [];
    private array $tokenPositionsInNode = [];
    private int $textNodeIndex = 0;
    private bool $squashNodes = false;

    public function __construct(DOMDocument $document)
    {
        $this->document = $document;
    }

    private function walk(DOMNode $node, Callable $callable) {
        $callable($node);
        foreach ($node->childNodes as $childNode) {
            $this->walk($childNode, $callable);
        }
    }

    public function tokenize(): array
    {
        $this->walk($this->document, function (DOMNode $node) {
            if ($node->nodeName === "w:p") {
                $this->walk($node, function (DOMNode $subNode) {
                    if ($subNode->nodeName === "w:t") {
                        $this->hasEncounteredLetterInCurrentTextNode = false;

                        if ($this->squashNodes) {
                            $this->squashNodesInPreviousMultiNodeToken();
                        }

                        if ($this->hasUnhandledLinebreak) {
                            $this->saveToken();
                            $this->saveSentence();
                            $this->hasUnhandledLinebreak = false;
                        }

                        for ($this->charIndex = 0; $this->charIndex < mb_strlen($subNode->textContent); ++$this->charIndex) {
                            $this->currChar = mb_substr($subNode->textContent, $this->charIndex, 1);

                            if (preg_match("/[\p{L}]/ui", $this->currChar)) {
                                $this->hasEncounteredLetterInCurrentTextNode = true;
                                $this->textAccumulator .= $this->currChar;

                                if ($subNode !== $this->prevLetterTextNode) {
                                    $this->textNodeAccumulator[] = $subNode;
                                }

                                $this->prevLetterTextNode = $subNode;
                            }
                            /* Non letters */
                            else {
                                $this->saveToken();
                            }

                            if ($this->charIsSeparator($this->currChar) && ($this->prevChar === ".")) {
                                $this->saveSentence();
                            }

                            $this->sentenceAccumulator .= $this->currChar;
                            $this->prevChar = $this->currChar;
                        }

                        ++$this->textNodeIndex;
                    } elseif ($subNode->nodeName === "w:br") {
                        $this->hasUnhandledLinebreak = true;
                    }

                    $this->prevNode = $subNode;
                });

                $this->saveToken();
                $this->squashNodesInPreviousMultiNodeToken();
            }

            $this->saveSentence();
        });
        return $this->sentences;
    }

    public function tokenizeWithSquashing(): array
    {
        $this->squashNodes = true;
        return $this->tokenize();
    }

    private function getAndIncrementTokenPositionInTextNode(int $textNodeIndex, string $normalizedTokenValue): int {
        $this->tokenPositionsInNode[$textNodeIndex][$normalizedTokenValue] ??= 0;
        $pos = $this->tokenPositionsInNode[$textNodeIndex][$normalizedTokenValue];
        ++$this->tokenPositionsInNode[$textNodeIndex][$normalizedTokenValue];
        return $pos;
    }

    private function getAndIncrementTokenPositionInSentence(string $normalizedTokenValue): int {
        $this->tokenPositionsInSentence[$normalizedTokenValue] ??= 0;
        $pos = $this->tokenPositionsInSentence[$normalizedTokenValue];
        ++$this->tokenPositionsInSentence[$normalizedTokenValue];
        return $pos;
    }

    public function saveToken(): void
    {
        if ($this->textAccumulator !== "") {
            $newToken = new Token($this->textAccumulator, $this->tokenIndex++, $this->textNodeAccumulator);

            $newToken->posInSentence = $this->getAndIncrementTokenPositionInSentence($newToken->getNormalizedValue());

            $textNodeIndex = $this->squashNodes && (count($this->textNodeAccumulator) > 1) ?
                $this->textNodeIndex - count($this->textNodeAccumulator) + 1:
                $this->textNodeIndex;

            $newToken->posInNode = $this->getAndIncrementTokenPositionInTextNode($textNodeIndex, $newToken->getNormalizedValue());

            $this->tokens[] = $newToken;
            $this->textAccumulator = "";

            if (count($this->textNodeAccumulator) > 1) {
                $this->prevMultiNodeToken = $newToken;
                $this->multiNodeTokenLastNodeTextLength = $this->charIndex;
            }

            if (!$this->hasEncounteredLetterInCurrentTextNode) {
                $this->textNodeAccumulator = [];
            } else {
                $this->textNodeAccumulator = [array_pop($this->textNodeAccumulator)];
            }
        }
    }

    public function squashNodesInPreviousMultiNodeToken(): void
    {
        if ($this->prevMultiNodeToken !== null) {
            $textAccumulator = "";
            $domNodes = $this->prevMultiNodeToken->nodes;
            $lastDomNode = $domNodes[array_key_last($domNodes)];
            $firstDomNode = $domNodes[array_key_first($domNodes)];

            for ($i = 1; $i < (count($domNodes) - 1); ++$i) {
                $textAccumulator .= $domNodes[$i]->textContent;
            }

            $textAccumulator .= mb_substr($lastDomNode->textContent, 0, $this->multiNodeTokenLastNodeTextLength);
            $lastDomNode->textContent = mb_substr($lastDomNode->textContent, $this->multiNodeTokenLastNodeTextLength);
            $firstDomNode->textContent .= $textAccumulator;
            $this->prevMultiNodeToken = null;
        }
    }

    public function saveSentence(): void
    {
        if ($this->sentenceAccumulator !== "") {
            $this->sentences[$this->sentenceIndex++] ??= new Sentence(
                $this->sentenceAccumulator,
                $this->sentenceIndex,
                $this->tokens,
            );

            $this->tokens = [];
            $this->sentenceAccumulator = "";
            $this->tokenPositionsInSentence = [];
        }
    }

    /**
     * @return bool
     */
    public function charIsSeparator(string $char): bool
    {
        return (preg_match("/[\p{Z}]/ui", $char) > 0);
    }
}
