<?php


namespace App\BusinessLogic;


use App\NgramFrequency;
use App\SimilaritasJaroWinkler;
use App\Word;
use Illuminate\Support\Collection;
use NlpTools\Tokenizers\RegexTokenizer;
use NlpTools\Tokenizers\TokenizerInterface;

class RekomendatorKoreksiEjaan
{
    public const MAX_RECOMMENDATIONS = 5;
    public const JARO_WINKLER_WEIGHT = 0.7;
    public const NGRAM_FREQUENCY_WEIGHT = 0.3;

    public string $text;
    public array $tokens;

    public TokenizerInterface $tokenizer;

    public function __construct(array $tokens)
    {
        $this->tokenizer = new RegexTokenizer(
            array_map(fn($pattern) => "/" . preg_quote($pattern) . "/", [
                ',', '<', '>', '"', '\'', '(', ')', '.', '!', '?', ' ', ':', ';', '-'
            ])
        );

        $this->tokens = $tokens;
        $this->preprocess();
    }

    private function preprocess()
    {
        $this->tokens = $this->filterTokens($this->tokens);
    }

    private function filterTokens(array $tokens): array
    {
        $tokens = array_values(array_filter(
            $tokens,
            fn($token) => strlen($token) > 0
        ));

        return $tokens;
    }

    public function recommendations(): array
    {
        $outputTokens = [];

        foreach (range(0, count($this->tokens) - 1) as $index) {
            $token = $this->tokens[$index];

            $prev_word_1 = $this->tokens[$index - 2] ?? null;
            $prev_word_2 = $this->tokens[$index - 1] ?? null;
            $isIncorrect = $this->tokenDoesntExistOnDictionary($token);

            if (!$isIncorrect) {
                continue;
            }

            $outputTokens[] = [
                "token" => $token,
                "recommendations" => $this->getRecommendations($token, $prev_word_1, $prev_word_2),
            ];
        }

        return $outputTokens;
    }

    public function tokenDoesntExistOnDictionary(string $token): bool
    {
        return !$this->tokenExistsInDictionary($token);
    }

    public function tokenExistsInDictionary(string $token): bool
    {
        return Word::query()
                ->where("content", "=", $token)
                ->count() > 0;
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

    private function getMostSimilarWords(string $incorrectWord, int $max = 5): Collection
    {
        $cachedSimilarWords = SimilaritasJaroWinkler::query()
            ->select([
                "word_b AS word",
                "similaritas AS points"
            ])
            ->where("word_a", "=", $incorrectWord)
            ->orderByDesc("similaritas")
            ->get();


        if ($cachedSimilarWords->isNotEmpty()) {
            return $cachedSimilarWords;
        }

        $similarWords = Word::query()
            ->select("content AS word")
            ->selectRaw("jaro_winkler_similarity(?, content) AS points", [$incorrectWord])
            ->orderByRaw("jaro_winkler_similarity(?, content) DESC", [$incorrectWord])
            ->limit($max)
            ->get();

        SimilaritasJaroWinkler::query()->insert(
            $similarWords->map(function ($similarWord) use ($incorrectWord) {
                return [
                    "word_a" => $incorrectWord,
                    "word_b" => $similarWord->word,
                    "similaritas" => $similarWord->points,
                ];
            })->toArray()
        );

        return $similarWords;
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
}