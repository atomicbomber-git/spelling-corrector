<?php


namespace App\RecommendationEngine;


use App\DomDocumentNLPTools\Sentence;
use App\FrekuensiBigram;
use App\Kata;
use App\SimilaritasJaroWinkler;

class WordRecommender
{
    const MAX_INTERNAL_LIMIT = 30;

    /**
     * @param array | Sentence[] $sentences
     * @param int $maxRecommendation
     * @return array
     */
    public function getRecommendations(array $sentences, $maxRecommendation = 5): array
    {
        $dictionary = Kata::query()
            ->pluck("teks")
            ->toArray();

        $recommendations = [];

        foreach ($sentences as $sentence) {
            foreach ($sentence->words as $index => $token) {
                $word = $token->getNormalizedValue();
                $wordBefore = optional($sentence->words[$index - 1] ?? null)->getNormalizedValue();
                $wordAfter = optional($sentence->words[$index + 1] ?? null)->getNormalizedValue();

                if (!in_array($word, $dictionary)) {
                    $recommendations[] = [
                        "raw_value" => $token->rawValue,
                        "value" => $token->getNormalizedValue(),
                        "index" => $token->index,
                        "pos_in_sentence" => $token->posInSentence,
                        "sentence" => $sentence->value,
                        "sentence_index" => $sentence->index,
                        "recommendations" => $this->getRankedRecommendations($word, $wordBefore, $wordAfter, $maxRecommendation)
                    ];
                }
            }
        }

        return $recommendations;
    }

    public function getRankedRecommendations(string $word, ?string $wordBefore, ?string $wordAfter, int $maxRecommendation): array
    {

        $mostSimilarWords = $this->getMostSimilarWords($word, self::MAX_INTERNAL_LIMIT);

        $preBigramFrequencies = FrekuensiBigram::query()
            ->where("gram_1", $wordBefore)
            ->whereIn("gram_2", array_keys($mostSimilarWords))
            ->pluck("frekuensi", "gram_2");

        $postBigramFrequencies = FrekuensiBigram::query()
            ->whereIn("gram_1", array_keys($mostSimilarWords))
            ->where("gram_2", $wordAfter)
            ->pluck("frekuensi", "gram_1");

        foreach ($mostSimilarWords as $word => $similarity) {
            $mostSimilarWords[$word] = (
                    ($this->divide($preBigramFrequencies[$word] ?? 0, $preBigramFrequencies->sum())) +
                    ($this->divide($postBigramFrequencies[$word] ?? 0, $postBigramFrequencies->sum()))
                ) / 2;
        }

        arsort($mostSimilarWords);
        return array_slice($mostSimilarWords, 0, $maxRecommendation);
    }

    private function getMostSimilarWords(string $word, int $limit): array
    {
        $results = SimilaritasJaroWinkler::query()
            ->where("word_a", $word)
            ->orderByDesc("similaritas")
            ->limit($limit)
            ->pluck("similaritas", "word_b");

        if ($results->isEmpty()) {
            $results = Kata::query()
                ->select("teks")
                ->selectRaw("jaro_winkler_similarity(?, teks) AS similaritas", [$word])
                ->orderByRaw("jaro_winkler_similarity(?, teks) DESC", [$word])
                ->limit($limit)
                ->pluck("similaritas", "teks");

            SimilaritasJaroWinkler::query()
                ->insertOrIgnore(
                    $results->map(function ($similarity, $text) use ($word) {
                        [$wordA, $wordB] = collect([$word, $text])->sort()->toArray();
                        return [
                            "word_a" => $wordA,
                            "word_b" => $wordB,
                            "similaritas" => $similarity,
                        ];
                    })->toArray()
                );
        }

        return $results->toArray();
    }

    public function divide($above, $below)
    {
        if ($below === 0) {
            return 0;
        }

        return $above / $below;
    }
}
