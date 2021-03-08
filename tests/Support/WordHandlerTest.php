<?php

namespace Tests\Support;

use DOMDocument;
use DOMNode;
use Exception;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class Token
{
    public int $index;
    public string $value;

    /** @var array|DOMNode[] */
    public array $domNodes;
    public int $sentenceIndex;
    public int $posInSentence;

    public function __construct(int $posInSentence, int $index, string $value, array $domNodes, int $sentenceIndex)
    {
        $this->value = $value;
        $this->domNodes = $domNodes;
        $this->sentenceIndex = $sentenceIndex;
        $this->index = $index;
        $this->posInSentence = $posInSentence;
    }

    public function dump()
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
}

class WordXmlExtractor
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

    public function load(string $pathToDocxFile)
    {
        $zipArchive = new ZipArchive();
        $zipResource = $zipArchive->open($pathToDocxFile);
        $this->wordXmlDomDocument = new DOMDocument();

        if ($zipResource === true) {
            $this->wordXmlDomDocument->loadXML(
                $zipArchive->getFromName("word/document.xml")
            );
            $zipArchive->close();
        } else {
            throw new Exception("Failed to open zip file.");
        }
    }

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

    private function charIsValid(string $char): bool
    {
        return (preg_match("/[^\p{P}\p{Z}]/u", $char) > 0);
    }

    private function charIsSeparator(string $char): bool
    {
        return (preg_match("/\p{Z}/u", $char) > 0);
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
}


class WordHandlerTest extends TestCase
{
    public function test_char_is_valid()
    {

    }

    public function testCanOpenWordDocument()
    {
        $wordXmlExtractor = new WordXmlExtractor();
        $wordXmlExtractor->load("/home/atomicbomber/Documents/jefri/heavily-formatted.docx");

        $tokens = $wordXmlExtractor->tokenize();
        ray()->send(
            collect($tokens)
                ->map->value
        );
    }
}
