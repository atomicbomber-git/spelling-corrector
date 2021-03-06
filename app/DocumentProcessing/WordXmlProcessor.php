<?php


namespace App\DocumentProcessing;


use App\Support\DomNodeTraverser;
use DOMDocument;
use DOMNode;

class WordXmlProcessor
{
    public function substituteWords(DOMDocument $xmlDomDocument, SubstitutionList $substitutionList): DOMDocument
    {
        $tally = [];
        $skipMap = [];
        $newXmlDomDocument = $xmlDomDocument->cloneNode(true);

        /** @var array|WordAndComponentNodesPair[] $wordNodeComponentPairs */
        $wordNodeComponentPairs = $this->getWordAndComponentNodesPair($newXmlDomDocument);

        foreach ($wordNodeComponentPairs as $wordNodeComponentPair) {
            $word = strtolower($wordNodeComponentPair->word);

            $tally[$word] ??= 0;
            $substitution = $substitutionList->getSubstitutionFor($word, $tally[$word]++);

            $targetComponentNode = $wordNodeComponentPair->componentNodes[0];

            $skipMap[spl_object_hash($targetComponentNode->domNode)][$word] ??= 0;
            if ($substitution === null) {
                ++$skipMap[spl_object_hash($targetComponentNode->domNode)][$word];
                continue;
            }
            $skips = $skipMap[spl_object_hash($targetComponentNode->domNode)][$word];

            $counter = 0;
            $targetComponentNode->domNode->textContent = preg_replace_callback("/\b$wordNodeComponentPair->word\b/i", function ($match) use ($substitution, &$counter, $skips) {
                return ($counter++ === $skips) ?
                    $substitution :
                    $match[0];
            }, $targetComponentNode->domNode->textContent);

            if (count($wordNodeComponentPair->componentNodes) > 1) {
                for ($i = 1; $i < count($wordNodeComponentPair->componentNodes); ++$i) {
                    $node = $wordNodeComponentPair->componentNodes[$i]->domNode;
                    $node->parentNode->removeChild($node);
                }
            }
        }

        return $newXmlDomDocument;
    }

    public function getWordAndComponentNodesPair(DOMDocument $xmlDocument): array
    {
        $wordAndNodes = [];
        $textAccumulator = "";
        $nodeAccumulator = [];

        DomNodeTraverser::traverse($xmlDocument, function (DOMNode $node) use (&$wordAndNodes, &$textAccumulator, &$nodeAccumulator) {
            if ($node->nodeName === "w:p") {
                DomNodeTraverser::traverse($node, function (DOMNode $subNode) use (&$wordAndNodes, &$textAccumulator, &$nodeAccumulator) {
                    if ($subNode->nodeName === "w:t") {
                        $substringStartPos = 0;

                        $text = $subNode->textContent;

                        if (strlen(trim($textAccumulator)) === 0) {
                            $nodeAccumulator = [];
                        }

                        $thisComponentNode = new ComponentNode($subNode, 0, null);
                        $nodeAccumulator[] = $thisComponentNode;

                        for ($i = 0; $i < strlen($text); ++$i) {
                            if (ctype_alpha($text[$i])) {
                                $textAccumulator .= $text[$i];
                            } else {
                                if ($i === 0) {
                                    array_pop($nodeAccumulator);
                                }

                                $wordAndNodes[] = new WordAndComponentNodesPair(
                                    $textAccumulator,
                                    $nodeAccumulator,
                                );

                                $thisComponentNode->length = $i - $substringStartPos;
                                $substringStartPos = $i + 1;

                                $textAccumulator = "";
                                $thisComponentNode = new ComponentNode($subNode, $substringStartPos, null,);
                                $nodeAccumulator = [$thisComponentNode];
                            }
                        }
                        $thisComponentNode->length = strlen($text) - $substringStartPos;
                    }
                });

                $wordAndNodes[] = new WordAndComponentNodesPair(
                    $textAccumulator,
                    $nodeAccumulator,
                );

                $textAccumulator = "";
                $nodeAccumulator = [];
                return false;
            }
            return true;
        });

        return array_filter($wordAndNodes, fn(WordAndComponentNodesPair $pair) => $pair->word !== "");
    }
}
