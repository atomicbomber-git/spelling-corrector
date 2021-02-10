<?php


namespace App\BusinessLogic;


use App\NgramFrequency;
use App\Support\SimilarityCalculator;
use App\Word;
use Illuminate\Support\Collection;
use NlpTools\Tokenizers\TokenizerInterface;
use NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;
use TextAnalysis\Tokenizers\GeneralTokenizer;

class RekomendatorKoreksiEjaan
{
    public const MAX_RECOMMENDATIONS = 5;
    public const JARO_WINKLER_WEIGHT = 1;
    public const NGRAM_FREQUENCY_WEIGHT = 0;

    public string $text;
    public array $tokens;

    public Collection $dictionary;
    public TokenizerInterface $tokenizer;

    public function __construct(string $text)
    {
        $this->dictionary = $this->getDictionary();
        $this->tokenizer = new WhitespaceAndPunctuationTokenizer();
        $this->text = $text;
        $this->preprocess();
    }

    public function tokenDoesntExistOnDictionary(string $token): bool
    {
        return !$this->tokenExistsInDictionary($token);
    }

    public function tokenExistsInDictionary(string $token): bool
    {
        return isset($this->dictionary[$token]);
    }


    private function preprocess()
    {
        $this->tokens = $this->tokenizer->tokenize($this->text);
        $this->tokens = $this->filterTokens($this->tokens);
    }

    private function filterTokens(array $tokens): array
    {
        $tokens = array_map(
            fn ($token) => preg_replace('/\W/', '', $token),
            $tokens
        );

        $tokens = array_values(array_filter(
            $tokens,
            fn ($token) => strlen($token) > 0
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

    public function getDictionary(): Collection
    {
        return Word::query()
            ->select("content")
            ->get()
            ->keyBy("content");
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