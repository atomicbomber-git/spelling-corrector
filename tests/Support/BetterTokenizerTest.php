<?php


namespace Tests\Support;


use Tests\TestCase;
use DOMDocument;
use DOMNode;

class Sentence {
    public string $value;
    public int $index;
    /** @var array | Token[] */
    public array $tokens;

    public function __construct(string $value, int $index, array $tokens)
    {
        $this->value = $value;
        $this->index = $index;
        $this->tokens = $tokens;
    }
}

class BetterToken {
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

class BetterTokenizer {
    private DOMDocument $document;
    private string $textAccumulator = "";

    /** @var array | BetterToken[] */
    private array $tokens = [];
    private int $tokenIndex = 0;

    private ?DOMNode $prevLetterTextNode = null;
    private array $textNodeAccumulator = [];

    private ?BetterToken $prevMultiNodeToken = null;
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
                        $this->squashNodesInPreviousMultiNodeToken();

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
            $newToken = new BetterToken($this->textAccumulator, $this->tokenIndex++, $this->textNodeAccumulator);

            $newToken->posInSentence = $this->getAndIncrementTokenPositionInSentence($newToken->getNormalizedValue());

            $textNodeIndex = count($this->textNodeAccumulator) > 1 ?
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

class BetterTokenizerTest extends TestCase
{
    public function test_it_can_work()
    {
        $tokenizer = new BetterTokenizer($this->getTestXmlDocument());

        $sentences = $tokenizer->tokenize();

        ray()->send($sentences);

        $this->assertCount(3, $sentences);
        $this->assertCount(2, $sentences[0]->tokens);
        $this->assertCount(1, $sentences[1]->tokens);
        $this->assertCount(5, $sentences[2]->tokens);
        
        



//        $this->assertEquals("hello", $tokens[0]->rawValue);
//        $this->assertEquals(0, $tokens[0]->posInSentence);
//        $this->assertEquals(0, $tokens[0]->sentenceIndex);
//
//        $this->assertEquals("hello", $tokens[1]->rawValue);
//        $this->assertEquals(1, $tokens[1]->posInSentence);
//        $this->assertEquals(0, $tokens[1]->sentenceIndex);
//
//        $this->assertEquals("hello hello", $tokens[1]->domNodes[0]->textContent);
//        $this->assertEquals(1, $tokens[1]->posInFirstNode);
//        $this->assertEquals(" hello", $tokens[2]->domNodes[0]->textContent);
//
//        $this->assertEquals("hello", $tokens[2]->rawValue);
//        $this->assertEquals(2, $tokens[2]->posInSentence);
//        $this->assertEquals(0, $tokens[2]->sentenceIndex);
//
//        $this->assertEquals(0, $tokens[3]->posInFirstNode);
//        $this->assertEquals(1, $tokens[4]->posInFirstNode);
//        $this->assertEquals(2, $tokens[5]->posInFirstNode);
//        $this->assertEquals(0, $tokens[6]->posInFirstNode);
//        $this->assertEquals(0, $tokens[7]->posInFirstNode);

        $this->assertTrue(true);
    }

    private function getTestXmlDocument(): DOMDocument
    {
        $domDocument = new DOMDocument();
        $domDocument->loadXML(/** @lang XML */ <<<HERE
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
            mc:Ignorable="w14 wp14">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Normal"/>
                <w:bidi w:val="0"/>
                <w:jc w:val="left"/>
                <w:rPr>
                    <w:b/>
                    <w:b/>
                    <w:bCs/>
                </w:rPr>
            </w:pPr>
            <w:r>
                <w:rPr></w:rPr>
                <w:t>hello hel</w:t>
                <w:t>l</w:t>
                <w:t>o... hello</w:t><w:br/><w:t> Hallo Hallo Hallo Hallo</w:t>
                <w:t>ween Hallo</w:t>
            </w:r>
        </w:p>
        <w:sectPr>
            <w:type w:val="nextPage"/>
            <w:pgSz w:w="11906" w:h="16838"/>
            <w:pgMar w:left="1134" w:right="1134" w:header="0" w:top="1134" w:footer="0" w:bottom="1134" w:gutter="0"/>
            <w:pgNumType w:fmt="decimal"/>
            <w:formProt w:val="false"/>
            <w:textDirection w:val="lrTb"/>
            <w:docGrid w:type="default" w:linePitch="100" w:charSpace="0"/>
        </w:sectPr>
    </w:body>
</w:document>
HERE
        );
        return $domDocument;
    }
}