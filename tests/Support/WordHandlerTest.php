<?php

namespace Tests\Support;

use App\Support\StringUtil;
use DOMDocument;
use DOMNode;
use Exception;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class Token
{
    public string $value;

    /** @var array|DOMNode[] */
    public array $domNodes;

    public function __construct(string $value, array $domNodes)
    {
        $this->value = $value;
        $this->domNodes = $domNodes;
    }

    public function dump()
    {
        return [
            "value" => $this->value,
            "nodes" => collect($this->domNodes)->map->textContent,
        ];
    }

}

class WordXmlExtractor
{
    public DOMDocument $wordXmlDomDocument;

    /** @var array | Token[] */
    public array $tokens = [];

    private string $textAccumulator = "";

    /** @var array | DOMNode[] */
    private array $domNodeAccumulator = [];
    private int $currentTagCharIndex;

    private bool $previousLinebreakIsHandled = false;
    private string $previousChar;
    private DOMNode $previousNode;
    private bool $hasEncounteredValidCharInThisNode = false;

    public function load(string $pathToDocxFile)
    {
        $zipArchive = new ZipArchive();
        $zipResource = $zipArchive->open("/home/atomicbomber/Documents/jefri/heavily-formatted.docx");
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

    public function loadTokens(): array
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

                            if ($this->charIsValid($currentChar)) {
                                if (!$this->hasEncounteredValidCharInThisNode) {
                                    $this->hasEncounteredValidCharInThisNode = true;
                                }
                            }

                            $this->textAccumulator .= $currentChar;

                            if ($this->charIsSeparator($currentChar) || $this->hasUnhandledLinebreak()) {
                                $this->saveTokenAtStartOrMiddleOfParagraph();
                                $this->markLinebreakAsHandledIfNeeded();
                            }

                            $this->previousChar = $currentChar;
                        }
                    }
                    $this->previousNode = $subNode;
                    $this->markLinebreakAsUnhandledIfNeeded();
                });

                $this->saveTokenAtTheEndOfParagraph();
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

        $cleanedText = StringUtil::trimUnicode($cleanedText);

        $domNodesToBeSaved = $this->domNodeAccumulator;
        if (!$this->hasEncounteredValidCharInThisNode) {
            array_pop($domNodesToBeSaved);
        }

        $this->tokens[] = new Token(
            $cleanedText,
            $domNodesToBeSaved,
        );

        $this->textAccumulator = "";
        $this->domNodeAccumulator = [array_pop($this->domNodeAccumulator)];

        if (!$this->charIsSeparator($lastCharInTextAccumulator)) {
            $this->textAccumulator .= $lastCharInTextAccumulator;
        }
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
        $this->tokens[] = new Token(StringUtil::trimUnicode($this->textAccumulator), $this->domNodeAccumulator);
        $this->domNodeAccumulator = [];
        $this->textAccumulator = "";
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

        $tokens = $wordXmlExtractor->loadTokens();
        foreach ($tokens as $token) {
            dump($token->dump());
        }
    }
}
