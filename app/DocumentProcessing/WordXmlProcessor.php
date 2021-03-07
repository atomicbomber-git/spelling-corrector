<?php


namespace App\DocumentProcessing;


use App\NgramFrequency;
use App\SimilaritasJaroWinkler;
use App\Support\DomNodeTraverser;
use App\Word;
use DOMDocument;
use DOMNode;
use App\Support\StringUtil;

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

        /** @var array|WordAndComponentNodesPair[] $wordNodeComponentPairs */
        $wordNodeComponentPairs = $this->getWordAndComponentNodesPair($newXmlDomDocument);

        foreach ($wordNodeComponentPairs as $wordNodeComponentPair) {
            $targetComponentNode = $wordNodeComponentPair->componentNodes[0];

            $word = strtolower($wordNodeComponentPair->word);
            $domNodeHash = spl_object_hash($targetComponentNode->domNode);

            $tally[$word] ??= 0;
            $substitution = $substitutionList->getSubstitutionFor($word, $tally[$word]++);

            $skipMap[$domNodeHash][$word] ??= 0;
            if ($substitution === null) {
                ++$skipMap[$domNodeHash][$word];
                continue;
            }

            $targetText = collect($wordNodeComponentPair->componentNodes)
                ->reduce(function (string $current, ComponentNode $next) {
                    return $current . $next->domNode->textContent;
                }, "");

            $skips = $skipMap[$domNodeHash][$word];
            $counter = 0;

            $targetComponentNode->domNode->textContent = preg_replace_callback("/\b$wordNodeComponentPair->word\b/i", function ($match) use ($substitution, &$counter, $skips) {
                return ($counter++ === $skips) ?
                    $substitution :
                    $match[0];
            }, $targetText);

            if (count($wordNodeComponentPair->componentNodes) > 1) {
                for ($i = 1; $i < count($wordNodeComponentPair->componentNodes); ++$i) {
                    $node = $wordNodeComponentPair->componentNodes[$i]->domNode;
                    $node->parentNode->removeChild($node);
                }
            }
        }

        return $newXmlDomDocument;
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

    public function getRecommendations(DOMDocument $xmlDocument)
    {
        $wordPairs = $this->getWordAndComponentNodesPair($xmlDocument);

        $words = array_map(fn(WordAndComponentNodesPair $pair) => $pair->word, $wordPairs);
        $words = array_unique($words);

        $correctWords = Word::query()
            ->select("content")
            ->whereIn("content", $words)
            ->pluck("content")
            ->toArray();

        $incorrectWords = array_diff($words, $correctWords);

        return collect($wordPairs)
            ->map(function (WordAndComponentNodesPair $pair, int $index) use ($wordPairs, $incorrectWords) {
                $incorrect = false;
                $recommendations = [];

                if (in_array($pair->word, $incorrectWords)) {
                    $incorrect = true;
                    $wordTwoBehind = ( $wordPairs[$index - 2]->sentenceIndex ?? null ) === $pair->sentenceIndex ? $wordPairs[$index - 2]->word : null;
                    $wordOneBehind = ( $wordPairs[$index - 1]->sentenceIndex ?? null ) === $pair->sentenceIndex ? $wordPairs[$index - 1]->word : null;

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


    public function getWordAndComponentNodesPair(DOMDocument $xmlDocument): array
    {
        $wordAndNodes = [];
        $textAccumulator = "";
        $nodeAccumulator = [];

        $sentenceMap = [];
        $sentenceIndex = 0;
        $wordPairToSentenceIndexMap = [];
        $wordPairSentenceCounter = [];

        $prevNode = null;

        DomNodeTraverser::traverse($xmlDocument, function (DOMNode $node) use (&$wordAndNodes, &$textAccumulator, &$nodeAccumulator, &$sentenceMap, &$sentenceIndex, &$prevNode, &$wordPairToSentenceIndexMap, &$wordPairSentenceCounter) {
            if ($node->nodeName === "w:p") {
                DomNodeTraverser::traverse($node, function (DOMNode $subNode) use (&$wordAndNodes, &$textAccumulator, &$nodeAccumulator, &$sentenceMap, &$sentenceIndex, &$prevNode, &$wordPairToSentenceIndexMap, &$wordPairSentenceCounter) {
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

                            if (($previousChar === ".") && (preg_match("/\p{Z}/u", $currentChar) > 0)) {
                                ++$sentenceIndex;
                            }

                            if (($i === 0) && (($prevNode->nodeName ?? null) === "w:br")) {
                                $wordPair = new WordAndComponentNodesPair(StringUtil::trimAndLowercaseUnicode( $textAccumulator), $nodeAccumulator, $sentenceIndex);
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

                                $wordPair = new WordAndComponentNodesPair(StringUtil::trimAndLowercaseUnicode($textAccumulator), $nodeAccumulator, $sentenceIndex);
                                $wordAndNodes[] = $wordPair;
                                $hash = spl_object_hash($wordPair);
                                $wordPairToSentenceIndexMap[$hash] = $sentenceIndex;

                                $wordPairSentenceCounter[$wordPair->word][$sentenceIndex] ??= 0;
                                $wordPair->wordPosInSentence = $wordPairSentenceCounter[$wordPair->word][$sentenceIndex]++;

                                $textAccumulator = "";
                                $thisComponentNode = new ComponentNode($subNode);
                                $nodeAccumulator = [$thisComponentNode];
                            }

                            $previousChar = $currentChar;
                        }
                    }

                    $prevNode = $subNode;
                });

                $wordPair = new WordAndComponentNodesPair(StringUtil::trimAndLowercaseUnicode($textAccumulator), $nodeAccumulator, $sentenceIndex);
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
        $wordAndNodes = array_filter($wordAndNodes, function (WordAndComponentNodesPair $pair) {
            return mb_strlen(StringUtil::trimAndLowercaseUnicode($pair->word)) > 0;
        });

        /* Buang Semua angka */
        $wordAndNodes = array_filter($wordAndNodes, function (WordAndComponentNodesPair $pair) {
            return !is_numeric($pair->word);
        });

        /* Buang yang terlalu banyak mengandung simbol / angka (> 20% )*/
        $wordAndNodes = array_filter($wordAndNodes, function (WordAndComponentNodesPair $pair) {
            $cleanedWord = preg_replace("/[^\p{L}]/ui", "", $pair->word);
            return (mb_strlen($cleanedWord) / mb_strlen($pair->word)) > 0.8;
        });

        /* Buang yang panjang karakternya < 2 */
        $wordAndNodes = array_filter($wordAndNodes, function (WordAndComponentNodesPair $pair) {
            return mb_strlen($pair->word) > 2;
        });

        $wordAndNodes = array_map(function (WordAndComponentNodesPair $pair) use ($wordPairSentenceCounter, $sentenceMap, $wordPairToSentenceIndexMap) {
            $sentenceIndex = $wordPairToSentenceIndexMap[spl_object_hash($pair)];
            $pair->sentence = $sentenceMap[$sentenceIndex];
            return $pair;
        }, $wordAndNodes);

        $wordAndNodes = array_filter($wordAndNodes, function (WordAndComponentNodesPair $pair) {
            return mb_strlen($pair->word) > 0;
        });

        return $wordAndNodes;
    }
}
