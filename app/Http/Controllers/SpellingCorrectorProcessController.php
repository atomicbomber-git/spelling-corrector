<?php

namespace App\Http\Controllers;

use App\NgramFrequency;
use App\Support\SimilarityCalculator;
use App\Word;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use TextAnalysis\Tokenizers\GeneralTokenizer;

class SpellingCorrectorProcessController extends Controller
{
    private $dictionary;

    public function __construct()
    {
        $this->dictionary = Word::query()
            ->select("content")
            ->get()
            ->keyBy("content");
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
        $tokenizer = new GeneralTokenizer();
        $tokens = $tokenizer->tokenize($text);


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
                "corrections" => $isIncorrect ? $this->getCorrections($cleanedToken) : [],
                "ngram_recommendations" => $isIncorrect ?
                    NgramFrequency::query()
                        ->where([
                            "word1" => $prev_word_1,
                            "word2" => $prev_word_2,
                        ])
                        ->orderByDesc("frequency")
                        ->get([ "word3" ])
                        ->pluck("word3")
                    : []
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

    private function getCorrections(string $incorrectWord, $max = 5): array
    {
        return $this->dictionary->sortByDesc(
            fn($word) => SimilarityCalculator::jaroWinklerSimilarity($incorrectWord, $word["content"])
        )->take($max)->pluck("content")->toArray();
    }
}
