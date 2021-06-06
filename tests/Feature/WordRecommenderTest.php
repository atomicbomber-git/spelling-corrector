<?php

namespace Tests\Feature;

use App\RecommendationEngine\WordRecommender;
use Database\Seeders\KbbiWordSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordRecommenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_most_similar_words()
    {
        (new KbbiWordSeeder)->run();

        $testWord = "hello";
        $wordRecommender = app(WordRecommender::class);
        $recommendations = $wordRecommender->getMostSimilarWords($testWord, 100);

        $this->assertLessThanOrEqual(100, count($recommendations));

        $sizeIsCorrect = true;
        $testWordLen = mb_strlen($testWord);
        foreach ($recommendations as $word => $point) {
            if ((mb_strlen($word) < ($testWordLen - 3)) || (mb_strlen($word) > ($testWordLen + 3))) {
                $sizeIsCorrect = false;
                break;
            }
        }

        $this->assertTrue($sizeIsCorrect);
    }
}