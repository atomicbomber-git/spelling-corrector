<?php

namespace App\Http\Controllers;

use App\NgramFrequency;
use App\Support\SimilarityCalculator;
use App\SimilaritasJaroWinkler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use TextAnalysis\Tokenizers\GeneralTokenizer;

class SpellingCorrectorProcessController extends Controller
{
    private const MAX_RECOMMENDATIONS = 5;
    private const JARO_WINKLER_WEIGHT = 0.7;
    private const NGRAM_FREQUENCY_WEIGHT = 0.3;

    private Collection $dictionary;
    private GeneralTokenizer $tokenizer;

    public function __construct(GeneralTokenizer $tokenizer)
    {
        $this->dictionary = SimilaritasJaroWinkler::query()
            ->select("content")
            ->get()
            ->keyBy("content");

        $this->tokenizer = $tokenizer;
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            "text" => ["required", "string"]
        ]);

        $text = $data["text"];
        $tokens = $this->tokenizer->tokenize($text);
        $cleanedTokens = [];

        foreach (range(0, count($tokens) - 1) as $index) {
            $token = $tokens[$index];

            $prev_word_1 = $tokens[$index - 2] ?? null;
            $prev_word_2 = $tokens[$index - 1] ?? null;

            $cleanedToken = strtolower(preg_replace("/[^A-Za-z0-9 ]/", '', $token));
            $isIncorrect = $this->doesntExistInDictionary($cleanedToken);

            $cleanedTokens[] = [
                "original" => $token,
                "cleaned" => $cleanedToken,
                "incorrect" => $isIncorrect,
                "corrections" => $isIncorrect ? $this->getRecommendations($cleanedToken, $prev_word_1, $prev_word_2) : [],
                "ngram_recommendations" => []
            ];
        }

        return response($cleanedTokens);
    }

    public function doesntExistInDictionary(string $word): bool
    {
        return !$this->existsInDictionary($word);
    }

    public function existsInDictionary(string $word): bool
    {
        return isset($this->dictionary[$word]);
    }

    private function getMostSimilarWords(string $incorrectWord, int $max = 5): Collection
    {
        return $this->dictionary->map(fn($entry) => [
            "word" => $entry["content"],
            "points" => SimilarityCalculator::jaroWinklerSimilarity($incorrectWord, $entry["content"])
        ])->sortByDesc("similarity")->take($max);
    }

    public function getMostFrequentNgramFrequencies(?string $word_1, ?string $word_2, $candidates = []): Collection
    {
        $most_frequent_ngram_frequencies = new Collection();

        $most_frequent_ngram_frequencies = $most_frequent_ngram_frequencies->merge(
            NgramFrequency::query()
                ->select("word3", "frequency")
                ->where(["word1" => $word_1, "word2" => $word_2])
                ->orderByDesc("frequency")
                ->limit(self::MAX_RECOMMENDATIONS)
                ->get()
        );

        $most_frequent_ngram_frequencies->merge(
            NgramFrequency::query()
                ->select("word3", "frequency")
                ->where(["word1" => $word_1, "word2" => $word_2])
                ->whereIn("word3", $candidates)
                ->orderByDesc("frequency")
                ->get()
        );

        $ngram_frequency_sum = $most_frequent_ngram_frequencies->sum("frequency");

        return $most_frequent_ngram_frequencies->map(fn($ngram_frequency) => [
            "word" => $ngram_frequency->word3,
            "points" => $ngram_frequency->frequency / $ngram_frequency_sum,
        ]);
    }

    /**
     * @param string $cleanedToken
     * @param string $prev_word_1
     * @param string $prev_word_2
     */
    public function getRecommendations(string $cleanedToken, ?string $prev_word_1, ?string $prev_word_2): array
    {
        $most_similar_words = $this->getMostSimilarWords($cleanedToken, self::MAX_RECOMMENDATIONS)->pluck("points", "word");
        $most_frequent_ngram_frequencies = $this->getMostFrequentNgramFrequencies($prev_word_1, $prev_word_2)->pluck("points", "word");

        return (new Collection())
            ->merge($most_similar_words->keys())
            ->merge($most_frequent_ngram_frequencies->keys())
            ->map(function ($word) use ($most_similar_words, $most_frequent_ngram_frequencies) {
                return [
                    "word" => $word,
                    "points" =>
                        ($most_similar_words[$word] ?? 0 * self::JARO_WINKLER_WEIGHT) +
                        ($most_frequent_ngram_frequencies[$word] ?? 0 * self::NGRAM_FREQUENCY_WEIGHT)
                ];
            })
            ->sortByDesc("points")
            ->pluck("word")
            ->toArray();
    }
}
