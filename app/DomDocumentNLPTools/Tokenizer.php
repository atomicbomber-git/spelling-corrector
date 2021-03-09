<?php


namespace App\DomDocumentNLPTools;

use DOMDocument;
use DOMNode;

class Tokenizer
{
    private DOMDocument $document;
    private string $textAccumulator = "";

    /** @var array | Token[] */
    private array $tokens = [];
    private int $tokenIndex = 0;

    private ?DOMNode $prevLetterTextNode = null;

    /** @var array | DOMNode[] */
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
                        if ($this->hasUnhandledLinebreak) {
                            $this->textNodeIndex--;
                            $this->saveToken();
                            $this->saveSentence();
                            $this->textNodeIndex++;
                            $this->hasUnhandledLinebreak = false;
                        }

                        if ($this->textAccumulator === "") {
                            $this->textNodeAccumulator = [$subNode];
                        } else {
                            $this->textNodeAccumulator[] = $subNode;
                        }

                        $this->hasEncounteredLetterInCurrentTextNode = false;

                        if ($this->squashNodes) {
                            $this->squashNodesInPreviousMultiNodeToken();
                        }

                        for ($this->charIndex = 0; $this->charIndex < mb_strlen($subNode->textContent); ++$this->charIndex) {
                            $this->currChar = mb_substr($subNode->textContent, $this->charIndex, 1);

                            if (preg_match("/[^\p{Z}]/ui", $this->currChar)) {
                                $this->hasEncounteredLetterInCurrentTextNode = true;
                                $this->textAccumulator .= $this->currChar;
                                $this->prevLetterTextNode = $subNode;
                            } /* Non letters */
                            else {
                                $this->saveToken();
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
                $this->saveToken();
                $this->textNodeIndex++;

                $this->squashNodes && $this->squashNodesInPreviousMultiNodeToken();
            }

            $this->saveSentence();
        });

        /* Hacky */
        if ($this->squashNodes) {
            $skips = [];

            foreach ($this->sentences as $sentence) {
                foreach ($sentence->tokens as $token) {
                    $value = $token->getNormalizedValue();
                    $hash = spl_object_hash($token->nodes[0]);

                    $skips[$value][$hash] ??= 0;
                    $token->posInNode = $skips[$value][$hash];
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

    public function saveToken(): void
    {
        if ($this->textAccumulator !== "") {
            $nodesToBeSaved = $this->textNodeAccumulator;

            if (!$this->hasEncounteredLetterInCurrentTextNode) {
                $this->textNodeAccumulator = [array_pop($nodesToBeSaved)];
            }

            $newToken = new Token($this->textAccumulator, $this->tokenIndex++, $nodesToBeSaved);
            $newToken->posInSentence = $this->getAndIncrementTokenPositionInSentence($newToken->getNormalizedValue());
            $textNodeIndex = $this->textNodeIndex - count($this->textNodeAccumulator) + 1;
            $newToken->posInNode = $this->getAndIncrementTokenPositionInTextNode($textNodeIndex, $newToken->getNormalizedValue());

            $this->tokens[] = $newToken;
            $this->textAccumulator = "";

            if (count($newToken->nodes) > 1) {
                $this->prevMultiNodeToken = $newToken;

                $this->multiNodeTokenLastNodeTextLength =
                    $this->hasEncounteredLetterInCurrentTextNode ?
                        $this->charIndex : ($this->prevCharIndex + 1);
            }
        }
    }

    private function getAndIncrementTokenPositionInSentence(string $normalizedTokenValue): int
    {
        $this->tokenPositionsInSentence[$normalizedTokenValue] ??= 0;
        $pos = $this->tokenPositionsInSentence[$normalizedTokenValue];
        ++$this->tokenPositionsInSentence[$normalizedTokenValue];
        return $pos;
    }

    private function getAndIncrementTokenPositionInTextNode(int $textNodeIndex, string $normalizedTokenValue): int
    {
        $this->tokenPositionsInNode[$textNodeIndex][$normalizedTokenValue] ??= 0;
        $pos = $this->tokenPositionsInNode[$textNodeIndex][$normalizedTokenValue];
        ++$this->tokenPositionsInNode[$textNodeIndex][$normalizedTokenValue];
        return $pos;
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

    public function squashNodesInPreviousMultiNodeToken(): void
    {
        if ($this->prevMultiNodeToken !== null) {
            $textAccumulator = "";
            $domNodes = $this->prevMultiNodeToken->nodes;
            $lastDomNode = $domNodes[array_key_last($domNodes)];
            $firstDomNode = $domNodes[array_key_first($domNodes)];


            for ($i = 1; $i < (count($domNodes) - 1); ++$i) {
                $textAccumulator .= $domNodes[$i]->textContent;
                
                if ($domNodes[$i]->parentNode !== null) {
                    $domNodes[$i]->parentNode->removeChild($domNodes[$i]);
                }
                
            }

            $textAccumulator .= mb_substr($lastDomNode->textContent, 0, $this->multiNodeTokenLastNodeTextLength);
            $lastDomNode->textContent = mb_substr($lastDomNode->textContent, $this->multiNodeTokenLastNodeTextLength);
            $firstDomNode->textContent .= $textAccumulator;

            if (mb_strlen($lastDomNode->textContent) === 0) {
                $lastDomNode->parentNode->removeChild($lastDomNode);
            }

            $this->prevMultiNodeToken->nodes = [$firstDomNode];
            $this->prevMultiNodeToken = null;
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
