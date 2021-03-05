<?php

namespace Tests\Support;

use App\Support\DomNodeTraverser;
use App\Support\SimilarityCalculator;
use PHPUnit\Framework\TestCase;

class WordNode {
    public function __construct(
        public string $word,
        public array $nodes,
    ) {}
}

class WordHandlerTest extends TestCase
{
    public function testCanOpenWordDocument()
    {
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . "temp.txt";

        $domDocument = new \DOMDocument();
        $domDocument->loadXML(file_get_contents($filePath));

        $wordAndNodes = [];
        $textAccumulator = "";
        $nodeAccumulator = [];

        DomNodeTraverser::traverse($domDocument, function (\DOMNode $node) use (&$wordAndNodes, &$textAccumulator, &$nodeAccumulator) {
            if ($node->nodeName === "w:p") {
                DomNodeTraverser::traverse($node, function (\DOMNode $subNode) use (&$wordAndNodes, &$textAccumulator, &$nodeAccumulator) {
                    if ($subNode->nodeName === "w:t") {
                        $nodeAccumulator[] = $subNode->textContent;

                        $text = $subNode->textContent;

                        for ($i = 0; $i < strlen($text); ++$i) {
                            if (ctype_alpha($text[$i])) {
                                $textAccumulator .= $text[$i];
                            } else {
                                $wordAndNodes[] = new WordNode(
                                    $textAccumulator,
                                    $nodeAccumulator
                                );

                                $textAccumulator = "";
                                $nodeAccumulator = [$subNode->textContent];
                            }
                        }
                    }
                });

                $wordAndNodes[] = new WordNode(
                    $textAccumulator,
                    $nodeAccumulator
                );

                $textAccumulator = "";
                $nodeAccumulator = [];
                return false;
            }

            return true;
        });
    }
}
