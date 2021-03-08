<?php


namespace App\DocumentProcessing;


use App\DocumentProcessing\WordAndComponentNodesPair as Pair;
use App\NgramFrequency;
use App\SimilaritasJaroWinkler;
use App\Support\DomNodeTraverser;
use App\Support\StringUtil;
use App\Word;
use DOMDocument;
use DOMNode;

class WordXmlProcessor
{
    const MAX_RECOMMENDATIONS = 5;

    public function substituteWords(DOMDocument $xmlDomDocument, SubstitutionList $substitutionList): DOMDocument
    {
        /* How many times has a particular word been processed? */
        $tally = [];

        /* How many time should a word in a particular node be skipped? */
        $skipMap = [];

        $newXmlDomDocument = $xmlDomDocument->cloneNode(true);

        /** @var array $substitutionListIndexes */
        $substitutionListIndexes = collect($substitutionList->entries)
            ->reduce(function (array $indexes, $corrections) {
                foreach ($corrections as $index => $correction) {
                    $indexes[$index] = $correction;
                }
                return $indexes;
            }, []);

        $pairs = $this->getWordAndComponentNodesPair($newXmlDomDocument);

        collect($pairs)
            ->filter(function (Pair $pair) {
                return count($pair->componentNodes) > 1;
            })->each(function (Pair $pair) {
                $rawWord = $pair->rawWord;
                $lastCharInRawWord = mb_substr($rawWord, mb_strlen($rawWord) - 1);
                $lastComponent = $pair->componentNodes[array_key_last($pair->componentNodes)];
                $firstComponent = $pair->componentNodes[array_key_first($pair->componentNodes)];

                $accumulatedText = "";
                for ($i = 1; $i < count($pair->componentNodes) - 1; ++$i) {
                    $node = $pair->componentNodes[$i]->domNode;
                    $accumulatedText .= $node->textContent;
                    $node->parentNode->removeChild($node);
                }

                $posOfLastLetterOfRawWord = mb_strpos($lastComponent->domNode->textContent, $lastCharInRawWord);

                $accumulatedText .= mb_substr(
                    $lastComponent->domNode->textContent,
                    0,
                    $posOfLastLetterOfRawWord + 1,
                );

                $lastComponent->domNode->textContent = mb_substr($lastComponent->domNode->textContent, $posOfLastLetterOfRawWord + 1);
                $firstComponent->domNode->textContent .= $accumulatedText;


                if (mb_strlen($lastComponent->domNode->textContent) === 0) {
                    $lastComponent->domNode->parentNode->removeChild(
                        $lastComponent->domNode
                    );
                }

                $pair->componentNodes = [$firstComponent];
            });

        $skipMap = [];
        collect($pairs)
            ->each(function (Pair $pair) use (&$skipMap) {
                $hash = spl_object_hash($pair->componentNodes[0]->domNode);

                if (!isset($skipMap[$pair->word][$hash])) {
                    $skipMap[$pair->word][$hash] = 0;
                } else {
                    $skipMap[$pair->word][$hash]++;
                }

                $pair->componentNodes[0]->wordPosition = $skipMap[$pair->word][$hash];
            });

        $skipOffsetMap = [];
        collect($pairs)
            ->each(function (Pair $pair) use (&$skipOffsetMap) {
                $hash = spl_object_hash($pair->componentNodes[0]->domNode);
                $skipOffsetMap[$pair->word][$hash] ??= 0;
            });

        /** @var array|Pair[] $wordNodeComponentPairs */
        $wordNodeComponentPairs = collect($pairs)
            ->filter(function (Pair $pair) use ($substitutionListIndexes) {
                return isset($substitutionListIndexes[$pair->index]);
            });

        foreach ($wordNodeComponentPairs as $pair) {
            $targetComponentNode = $pair->componentNodes[0];

            $word = mb_strtolower($pair->word);
            $domNodeHash = spl_object_hash($targetComponentNode->domNode);

            $tally[$word] ??= 0;
            $substitution = $substitutionListIndexes[$pair->index];

            $skipMap[$domNodeHash][$word] ??= 0;
            if ($substitution === null) {
                ++$skipMap[$domNodeHash][$word];
                continue;
            }

            $targetText = collect($pair->componentNodes)
                ->reduce(function (string $current, ComponentNode $next) {
                    return $current . $next->domNode->textContent;
                }, "");

            $skips = $targetComponentNode->wordPosition;
            $skipOffset = $skipOffsetMap[$word][spl_object_hash($targetComponentNode->domNode)]++;
            $skips = $skips - $skipOffset;

            $counter = 0;

            $quoted = preg_quote($pair->word);

            $targetComponentNode->domNode->textContent = preg_replace_callback("/\b{$quoted}\b/ui", function ($match) use ($substitution, &$counter, $skips) {
                return ($counter++ === $skips) ?
                    $substitution :
                    $match[0];
            }, $targetText);

            if (count($pair->componentNodes) > 1) {
                for ($i = 1; $i < count($pair->componentNodes); ++$i) {
                    $node = $pair->componentNodes[$i]->domNode;
                    $node->parentNode->removeChild($node);
                }
            }
        }

        return $newXmlDomDocument;
    }

    public function getWordAndComponentNodesPair(DOMDocument $xmlDocument): array
    {
        /** @var array|Pair[] $wordAndNodes */
        $wordAndNodes = [];
        $textAccumulator = "";
        $nodeAccumulator = [];

        $sentenceMap = [];
        $wordIndex = 0;
        $sentenceIndex = 0;
        $wordPairToSentenceIndexMap = [];
        $wordPairSentenceCounter = [];
        $componentNodeWordMap = [];

        $prevNode = null;

        DomNodeTraverser::traverse($xmlDocument, function (DOMNode $node) use (&$componentNodeWordMap, &$wordIndex, &$wordAndNodes, &$textAccumulator, &$nodeAccumulator, &$sentenceMap, &$sentenceIndex, &$prevNode, &$wordPairToSentenceIndexMap, &$wordPairSentenceCounter) {
            if ($node->nodeName === "w:p") {
                DomNodeTraverser::traverse($node, function (DOMNode $subNode) use (&$componentNodeWordMap, &$wordIndex, &$wordAndNodes, &$textAccumulator, &$nodeAccumulator, &$sentenceMap, &$sentenceIndex, &$prevNode, &$wordPairToSentenceIndexMap, &$wordPairSentenceCounter) {
                    if ($subNode->nodeName === "w:t") {
                        $text = $subNode->textContent;

                        if (mb_strlen(StringUtil::trimUnicode($textAccumulator)) === 0) {
                            $nodeAccumulator = [];
                        }

                        $sentenceMap[$sentenceIndex] ??= "";
                        $thisComponentNode = new ComponentNode($subNode);
                        $nodeAccumulator[] = $thisComponentNode;

                        $previousChar = null;


                        for ($i = 0; $i < mb_strlen($text); ++$i) {
                            $currentChar = mb_substr($text, $i, 1);

                            if (($i === 0) && (($prevNode->nodeName ?? null) === "w:br")) {
                                $wordPair = new Pair($wordIndex++, StringUtil::trimAndLowercaseUnicode($textAccumulator), $textAccumulator, $nodeAccumulator, $sentenceIndex);
                                $wordAndNodes[] = $wordPair;
                                $hash = spl_object_hash($wordPair);
                                $wordPairToSentenceIndexMap[$hash] = $sentenceIndex;
                                $wordPairSentenceCounter[$wordPair->word][$sentenceIndex] ??= 0;
                                $wordPair->wordPosInSentence = $wordPairSentenceCounter[$wordPair->word][$sentenceIndex]++;

                                /* Line break indicates the beginning of a new word AND a new sentence */
                                ++$sentenceIndex;

                                $textAccumulator = "";
                                $nodeAccumulator = [$thisComponentNode];
                            }

                            $sentenceMap[$sentenceIndex] ??= "";
                            $sentenceMap[$sentenceIndex] .= $currentChar;

                            /* If doesn't match a separator */
                            if (!(preg_match("/\p{Z}/u", $currentChar) > 0) && ($currentChar !== "/")) {
                                $textAccumulator .= $currentChar;
                            } else {
                                if ($i === 0) {
                                    array_pop($nodeAccumulator);
                                }

                                $wordPair = new Pair($wordIndex++, StringUtil::trimAndLowercaseUnicode($textAccumulator), $textAccumulator, $nodeAccumulator, $sentenceIndex);
                                $wordAndNodes[] = $wordPair;
                                $hash = spl_object_hash($wordPair);
                                $wordPairToSentenceIndexMap[$hash] = $sentenceIndex;

                                $wordPairSentenceCounter[$wordPair->word][$sentenceIndex] ??= 0;
                                $wordPair->wordPosInSentence = $wordPairSentenceCounter[$wordPair->word][$sentenceIndex]++;
                                $textAccumulator = "";

                                $thisComponentNode = new ComponentNode($subNode);
                                $nodeAccumulator = [$thisComponentNode];
                            }

                            if (($previousChar === ".") && (preg_match("/\p{Z}/u", $currentChar) > 0)) {
                                ++$sentenceIndex;
                            }

                            $previousChar = $currentChar;
                        }
                    }

                    $prevNode = $subNode;
                });

                $wordPair = new Pair($wordIndex++, StringUtil::trimAndLowercaseUnicode($textAccumulator), $textAccumulator, $nodeAccumulator, $sentenceIndex);
                $wordAndNodes[] = $wordPair;
                $hash = spl_object_hash($wordPair);
                $wordPairToSentenceIndexMap[$hash] = $sentenceIndex;

                $wordPairSentenceCounter[$wordPair->word][$sentenceIndex] ??= 0;
                $wordPair->wordPosInSentence = $wordPairSentenceCounter[$wordPair->word][$sentenceIndex]++;

                $textAccumulator = "";
                $nodeAccumulator = [];

                /* A sentence always ends at the end of a <w:p> (paragraph) tag */
                ++$sentenceIndex;
                return false;
            }
            return true;
        });

        /* Buang semua kata yang kosong */
        $wordAndNodes = array_filter($wordAndNodes, function (Pair $pair) {
            $matches = [];
            $quoted = preg_quote($pair->word);
            preg_match("/\b{$quoted}\b/ui", $pair->rawWord, $matches);
            return count($matches) > 0;
        });

        /* Buang Semua angka */
        $wordAndNodes = array_filter($wordAndNodes, function (Pair $pair) {
            return !is_numeric($pair->word);
        });

        /* Buang yang terlalu banyak mengandung simbol / angka (> 20% )*/
        $wordAndNodes = array_filter($wordAndNodes, function (Pair $pair) {
            $cleanedWord = preg_replace("/[^\p{L}]/ui", "", $pair->word);
            return (mb_strlen($cleanedWord) / mb_strlen($pair->word)) > 0.8;
        });

        /* Buang yang panjang karakternya < 2 */
        $wordAndNodes = array_filter($wordAndNodes, function (Pair $pair) {
            return mb_strlen($pair->word) > 2;
        });

        $wordAndNodes = array_map(function (Pair $pair) use ($wordPairSentenceCounter, $sentenceMap, $wordPairToSentenceIndexMap) {
            $sentenceIndex = $wordPairToSentenceIndexMap[spl_object_hash($pair)];
            $pair->sentence = $sentenceMap[$sentenceIndex];
            return $pair;
        }, $wordAndNodes);

        $wordAndNodes = array_filter($wordAndNodes, function (Pair $pair) {
            return mb_strlen($pair->word) > 0;
        });

        foreach ($wordAndNodes as $wordAndNode) {
            foreach ($wordAndNode->componentNodes as $componentNode) {
                $hash = spl_object_hash($componentNode->domNode);
                $componentNodeWordMap[$wordAndNode->word][$hash] ??= 0;
                $componentNode->wordPosition = $componentNodeWordMap[$wordAndNode->word][$hash];
                $componentNodeWordMap[$wordAndNode->word][$hash]++;
            }
        }

        return $wordAndNodes;
    }

    public function getRecommendations(DOMDocument $xmlDocument)
    {
        $wordPairs = $this->getWordAndComponentNodesPair($xmlDocument);

        $words = array_map(fn(Pair $pair) => $pair->word, $wordPairs);
        $words = array_unique($words);

        $correctWords = Word::query()
            ->select("content")
            ->whereIn("content", $words)
            ->pluck("content")
            ->toArray();

        $incorrectWords = array_diff($words, $correctWords);

        return collect($wordPairs)
            ->map(function (Pair $pair, int $index) use ($wordPairs, $incorrectWords) {
                $incorrect = false;
                $recommendations = [];

                if (in_array($pair->word, $incorrectWords)) {
                    $incorrect = true;
                    $wordTwoBehind = ($wordPairs[$index - 2]->sentenceIndex ?? null) === $pair->sentenceIndex ? $wordPairs[$index - 2]->word : null;
                    $wordOneBehind = ($wordPairs[$index - 1]->sentenceIndex ?? null) === $pair->sentenceIndex ? $wordPairs[$index - 1]->word : null;

                    $recommendations = $this->getMostSimilarWords($pair->word, self::MAX_RECOMMENDATIONS);

                    $ngramData = NgramFrequency::query()
                        ->select("word3", "frequency")
                        ->where("word1", $wordTwoBehind)
                        ->where("word2", $wordOneBehind)
                        ->whereIn("word3", $recommendations)
                        ->orderByDesc("frequency")
                        ->pluck("frequency", "word3");

                    $recommendations = collect($recommendations)
                        ->mapWithKeys(function ($recommendation) use ($ngramData) {
                            return [$recommendation => $ngramData[$recommendation] ?? 0];
                        })->toArray();
                }

                return [
                    "index" => $pair->index,
                    "incorrect" => $incorrect,
                    "word" => $pair->word,
                    "recommendations" => $recommendations,
                    "sentence" => $pair->sentence,
                    "pos_in_sentence" => $pair->wordPosInSentence,
                ];
            })->filter(function ($result) {
                return $result["incorrect"];
            })->values()->toArray();
    }

    public function getMostSimilarWords(string $word, int $limit): array
    {
        $results = SimilaritasJaroWinkler::query()
            ->select("word_b")
            ->where("word_a", "=", $word)
            ->orderByDesc("similaritas")
            ->limit($limit)
            ->pluck("word_b");

        if ($results->isEmpty()) {
            $results = Word::query()
                ->select("content")
                ->selectRaw("jaro_winkler_similarity(?, content) AS similaritas", [$word])
                ->orderByRaw("jaro_winkler_similarity(?, content) DESC", [$word])
                ->limit($limit)
                ->get();

            SimilaritasJaroWinkler::query()->insert(
                $results->map(function (Word $similarWord) use ($word) {
                    return [
                        "word_a" => $word,
                        "word_b" => $similarWord->content,
                        "similaritas" => $similarWord->similaritas,
                    ];
                })->toArray()
            );

            $results = $results->pluck("content");
        }

        return $results->toArray();
    }
}
