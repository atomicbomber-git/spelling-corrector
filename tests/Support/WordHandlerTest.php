<?php

namespace Tests\Support;

use DOMDocument;
use DOMNode;
use PHPUnit\Framework\TestCase;

class Token
{
    public int $index;
    public string $value;
    /** @var array|DOMNode[] */
    public array $domNodes;
    public int $sentenceIndex;
    public int $posInSentence;
    public ?int $posInFirstNode = null;
    private string $normalizedValue;

    public function __construct(int $posInSentence, int $index, string $value, array $domNodes, int $sentenceIndex)
    {
        $this->value = $value;
        $this->domNodes = $domNodes;
        $this->sentenceIndex = $sentenceIndex;
        $this->index = $index;
        $this->posInSentence = $posInSentence;

        $this->normalizedValue = mb_strtolower($this->value);
    }

    public function dump()
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }

    /**
     * @return string
     */
    public function getNormalizedValue(): string
    {
        return $this->normalizedValue;
    }
}

class OpenXMLTokenizer
{
    public DOMDocument $wordXmlDomDocument;

    /** @var array | Token[] */
    public array $tokens = [];

    /** @var array | string[] */
    public array $sentences;

    private string $sentenceAccumulator = "";

    private string $textAccumulator = "";

    /** @var array | DOMNode[] */
    private array $domNodeAccumulator = [];
    private int $currentTagCharIndex;
    private int $sentenceIndex = 0;

    private bool $previousLinebreakIsHandled = false;
    private string $previousChar = "";
    private DOMNode $previousNode;
    private bool $hasEncounteredValidCharInThisNode = false;
    private int $tokenIndex = 0;
    private array $tokenPositionsInSentence = [];

    public function loadXmlDocument(DOMDocument $xmlDocument)
    {
        $this->wordXmlDomDocument = $xmlDocument->cloneNode(true);
    }

    /**
     * @return array|Token[]
     */
    public function tokenizeAndPrepareForSubstitution(): array
    {
        $tokens = $this->tokenize();

        foreach ($tokens as $token) {
            $this->squashTokenDomNodes($token);
        }

        $tokenAndDomNodeCounter = [];
        foreach ($tokens as $token) {
            $firstDomNodeHash = spl_object_hash($token->domNodes[0]);
            $tokenAndDomNodeCounter[$token->getNormalizedValue()][$firstDomNodeHash] ??= 0;
            $token->posInFirstNode = $tokenAndDomNodeCounter[$token->getNormalizedValue()][$firstDomNodeHash];
            ++$tokenAndDomNodeCounter[$token->getNormalizedValue()][$firstDomNodeHash];
        }

        return $tokens;
    }

    /**
     * @return array|Token[]
     */
    public function tokenize(): array
    {
        $this->walk($this->wordXmlDomDocument, function (DOMNode $node) {
            if ($node->nodeName === "w:p") {
                $this->walk($node, function (DOMNode $subNode) {
                    if ($subNode->nodeName === "w:t") {
                        $this->hasEncounteredValidCharInThisNode = false;
                        $textContent = $subNode->textContent;
                        $this->domNodeAccumulator[] = $subNode;

                        for ($this->currentTagCharIndex = 0; $this->currentTagCharIndex < mb_strlen($textContent); ++$this->currentTagCharIndex) {
                            $currentChar = $this->charAt($textContent, $this->currentTagCharIndex);
                            $this->sentenceAccumulator .= $currentChar;

                            /** Skips consecutive spaces */
                            if (!($this->charIsSeparator($this->previousChar) && $this->charIsSeparator($currentChar))) {

                                /* Have we encountered any valid char in this node? */
                                if ($this->charIsValid($currentChar)) {
                                    if (!$this->hasEncounteredValidCharInThisNode) {
                                        $this->hasEncounteredValidCharInThisNode = true;
                                    }
                                }

                                $this->textAccumulator .= $currentChar;

                                /* Save normal sentences */
                                if (($this->charIsSeparator($currentChar) && ($this->previousChar === "."))) {
                                    $this->sentences[$this->sentenceIndex] ??= $this->sentenceAccumulator;
                                    $this->sentenceAccumulator = "";
                                    $this->sentenceIndex++;
                                }

                                /* Save sentences that ends with line breaks */
                                if ($this->hasUnhandledLinebreak()) {
                                    $lastCharInSentenceAccumulator = mb_substr($this->sentenceAccumulator, mb_strlen($this->sentenceAccumulator) - 1, 1);
                                    $sentenceAccumulatorWithoutLastChar = mb_substr($this->sentenceAccumulator, 0, mb_strlen($this->sentenceAccumulator) - 1);
                                    $this->sentences[$this->sentenceIndex] ??= $sentenceAccumulatorWithoutLastChar;
                                    $this->sentenceAccumulator = $lastCharInSentenceAccumulator;
                                    $this->sentenceIndex++;
                                }

                                /* Save tokens in the middle of the paragraph */
                                if ($this->charIsSeparator($currentChar) || $this->hasUnhandledLinebreak()) {
                                    $this->saveTokenAtStartOrMiddleOfParagraph();
                                    $this->markLinebreakAsHandledIfNeeded();
                                }
                            }

                            $this->previousChar = $currentChar;
                        }
                    }
                    $this->previousNode = $subNode;
                    $this->markLinebreakAsUnhandledIfNeeded();
                });

                $this->saveTokenAtTheEndOfParagraph();
                $this->saveSentenceAtTheEndOfParagraph();
            }
        });

        return $this->tokens;
    }

    public function walk(DOMNode $node, callable $callable): void
    {
        $callable($node);
        foreach ($node->childNodes as $childNode) {
            $this->walk($childNode, $callable);
        }
    }

    private function charAt(string $text, int $pos): string
    {
        return mb_substr($text, $pos, 1);
    }

    private function charIsSeparator(string $char): bool
    {
        return (preg_match("/\p{Z}/u", $char) > 0);
    }

    private function charIsValid(string $char): bool
    {
        return (preg_match("/[^\p{P}\p{Z}]/u", $char) > 0);
    }

    public function hasUnhandledLinebreak(): bool
    {
        return
            ($this->previousNode->nodeName === "w:br") &&
            (!$this->previousLinebreakIsHandled);
    }

    private function saveTokenAtStartOrMiddleOfParagraph()
    {
        $lastCharInTextAccumulator = mb_substr($this->textAccumulator, mb_strlen($this->textAccumulator) - 1, 1);

        $cleanedText = $this->textAccumulator;
        if ($this->hasUnhandledLinebreak()) {
            $cleanedText = mb_substr($cleanedText, 0, mb_strlen($cleanedText) - 1);
        }

        $cleanedText = $this->trimText($cleanedText);

        $domNodesToBeSaved = $this->domNodeAccumulator;
        if (!$this->hasEncounteredValidCharInThisNode) {
            array_pop($domNodesToBeSaved);
        }

        $normalizedTokenValue = mb_strtolower($cleanedText);
        $this->tokenPositionsInSentence[$normalizedTokenValue][$this->sentenceIndex] ??= 0;

        $this->tokens[] = new Token(
            $this->tokenPositionsInSentence[$normalizedTokenValue][$this->sentenceIndex]++,
            $this->tokenIndex++,
            $cleanedText,
            $domNodesToBeSaved,
            $this->sentenceIndex,
        );

        $this->textAccumulator = "";
        $this->domNodeAccumulator = [array_pop($this->domNodeAccumulator)];

        if (!$this->charIsSeparator($lastCharInTextAccumulator)) {
            $this->textAccumulator .= $lastCharInTextAccumulator;
        }
    }

    private function trimText(string $text): string
    {
        $temp = $text;
        $temp = preg_replace("/^[\p{P}\p{S}\p{Z}]*/u", "", $temp);
        $temp = preg_replace("/[\p{P}\p{S}\p{Z}]*$/u", "", $temp);
        return $temp;
    }

    public function markLinebreakAsHandledIfNeeded(): void
    {
        if ($this->previousNode->nodeName === "w:br") {
            $this->previousLinebreakIsHandled = true;
        }
    }

    public function markLinebreakAsUnhandledIfNeeded(): void
    {
        if ($this->previousNode->nodeName === "w:br") {
            $this->previousLinebreakIsHandled = false;
        }
    }

    private function saveTokenAtTheEndOfParagraph()
    {
        /* No-op if text accumulator is empty */
        if (mb_strlen($this->textAccumulator) === 0) {
            return;
        }

        $rawTokenValue = $this->trimText($this->textAccumulator);
        $normalizedTokenValue = mb_strtolower($rawTokenValue);
        $this->tokenPositionsInSentence[$normalizedTokenValue][$this->sentenceIndex] ??= 0;

        $this->tokens[] = new Token(
            $this->tokenPositionsInSentence[$normalizedTokenValue][$this->sentenceIndex]++,
            $this->tokenIndex++,
            $rawTokenValue,
            $this->domNodeAccumulator,
            $this->sentenceIndex
        );

        $this->domNodeAccumulator = [];
        $this->textAccumulator = "";
    }

    public function saveSentenceAtTheEndOfParagraph(): void
    {
        /* No-op if sentence accumulator is empty */
        if (mb_strlen($this->sentenceAccumulator) === 0) {
            return;
        }

        $this->sentences[$this->sentenceIndex] ??= $this->sentenceAccumulator;
        $this->sentenceAccumulator = "";
        $this->sentenceIndex++;
    }

    private function squashTokenDomNodes(Token $token)
    {
        /* No-op if $token only has one domNode */
        if (count($token->domNodes) === 1) {
            return;
        }

        $firstDomNode = $token->domNodes[0];

        // Find the last position of a separator in the first component
        $matches = [];
        preg_match_all("/\p{Z}/ui", $firstDomNode->textContent, $matches, PREG_OFFSET_CAPTURE);
        $posOfLastSeparatorInFirstComponent = ($matches[0][array_key_last($matches[0])][1] ?? null);

        $accumulatedText = "";
        $remainingWordLen = mb_strlen($token->value) - (mb_strlen($firstDomNode->textContent) - ($posOfLastSeparatorInFirstComponent + 1));

        for ($i = 1; $i < (count($token->domNodes) - 1); ++$i) {
            $accumulatedText .= $token->domNodes[$i]->textContent;
            $remainingWordLen -= mb_strlen($token->domNodes[$i]->textContent);
        }

        $lastDomNode = $token->domNodes[array_key_last($token->domNodes)];
        $accumulatedText .= mb_substr($lastDomNode->textContent, 0, $remainingWordLen);

        $firstDomNode->textContent .= $accumulatedText;
        $lastDomNode->textContent = mb_substr($lastDomNode->textContent, $remainingWordLen);

        $token->domNodes = [$firstDomNode];

        $quotedValue = preg_quote($token->value);
        $token->posInFirstNode = preg_match_all("/\b{$quotedValue}\b/ui", $firstDomNode->textContent) - 1;
    }
}


class WordHandlerTest extends TestCase
{
    /** @test */
    public function it_can_process_tokens_that_are_split_in_multiple_tags()
    {
        $extractor = new OpenXMLTokenizer();
        $extractor->loadXmlDocument($this->getTestXmlForCase1());
        $tokens = $extractor->tokenizeAndPrepareForSubstitution();

        $this->assertCount(8, $tokens);
        $this->assertEquals("hello", $tokens[0]->value);
        $this->assertEquals(0, $tokens[0]->posInSentence);
        $this->assertEquals(0, $tokens[0]->sentenceIndex);

        $this->assertEquals("hello", $tokens[1]->value);
        $this->assertEquals(1, $tokens[1]->posInSentence);
        $this->assertEquals(0, $tokens[1]->sentenceIndex);

        $this->assertEquals("hello hello", $tokens[1]->domNodes[0]->textContent);
        $this->assertEquals(1, $tokens[1]->posInFirstNode);
        $this->assertEquals(" hello", $tokens[2]->domNodes[0]->textContent);

        $this->assertEquals("hello", $tokens[2]->value);
        $this->assertEquals(2, $tokens[2]->posInSentence);
        $this->assertEquals(0, $tokens[2]->sentenceIndex);

        $this->assertEquals(0, $tokens[3]->posInFirstNode);
        $this->assertEquals(1, $tokens[4]->posInFirstNode);
        $this->assertEquals(2, $tokens[5]->posInFirstNode);
        $this->assertEquals(0, $tokens[6]->posInFirstNode);
        $this->assertEquals(0, $tokens[7]->posInFirstNode);
    }

    private function getTestXmlForCase1(): DOMDocument
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
                <w:t>o hello</w:t>
                <w:br/>
                <!-- Token 3, 4, 5, 6, 7  -->
                <w:t> Hallo Hallo Hallo Hallo</w:t>
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

    private function getTestXmlForCase2(): DOMDocument
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
                <w:t>lo hello</w:t>
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
