<?php


namespace App\RecommendationEngine;


use App\DomDocumentNLPTools\Token;

class WordRecommender
{
    static $cachedResults = [];

    /**
     * @param array | Token $tokens
     * @return array
     */
    public function getRecommendations(array $tokens): array
    {

    }
}