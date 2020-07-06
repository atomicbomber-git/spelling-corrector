<?php

namespace App\Http\Controllers;

use App\Support\SimilarityCalculator;
use App\Word;
use Illuminate\Http\Request;
use TextAnalysis\Tokenizers\GeneralTokenizer;
use TextAnalysis\Tokenizers\PennTreeBankTokenizer;

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

    public function existsInDictionary(string $word): bool
    {
        return isset($this->dictionary[$word]);
    }

    public function doesntExistInDictionary(string $word): bool
    {
        return !$this->existsInDictionary($word);
    }

    private function getCorrections(string $incorrectWord, $max=5): array
    {
        return $this->dictionary->sortByDesc(
            fn ($word) => SimilarityCalculator::jaroWinklerSimilarity($incorrectWord, $word["content"])
        )->take($max)->pluck("content")->toArray();
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            "text" => ["required", "string"]
        ]);

        $text = $data["text"];
        $tokenizer = new GeneralTokenizer();
        $tokens = $tokenizer->tokenize($text);

        $clearedTokens = array_map(
            function ($token)  {
                $cleanedToken = strtolower(preg_replace("/[^A-Za-z0-9 ]/", '', $token));
                $isIncorrect = $this->doesntExistInDictionary($cleanedToken);
                return [
                    "original" => $token,
                    "cleaned" => $cleanedToken,
                    "incorrect" => $isIncorrect,
                    "corrections" => $isIncorrect ? $this->getCorrections($cleanedToken) : []
                ]; }, $tokens
        );

        return response($clearedTokens);
    }
}
