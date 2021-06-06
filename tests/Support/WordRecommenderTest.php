<?php


namespace Tests\Support;


use App\DomDocumentNLPTools\Sentence;
use App\DomDocumentNLPTools\Tokenizer;
use App\FrekuensiBigram;
use App\Kata;
use App\SimilaritasJaroWinkler;
use DOMDocument;
use Tests\TestCase;

class WordRecommender
{
    const MAX_INTERNAL_LIMIT = 200;

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
            ->pluck("similaritas", "word_b");

        if ($results->isEmpty()) {
            $results = Kata::query()
                ->select("teks")
                ->selectRaw("jaro_winkler_similarity(?, teks) AS similaritas", [$word])
                ->orderByRaw("jaro_winkler_similarity(?, teks) DESC", [$word])
                ->limit($limit)
                ->pluck("similaritas", "teks");

            SimilaritasJaroWinkler::query()
                ->insert(
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

class WordRecommenderTest extends TestCase
{
    public function test_can_give_recommendations()
    {
        $tokenizer = new Tokenizer();
        $tokenizer->load($this->getTestXmlDocument());
        $sentences = $tokenizer->tokenize();
        $recommender = new WordRecommender();
    }

    private function getTestXmlDocument(): DOMDocument
    {
        $domDocument = new DOMDocument();
        $domDocument->loadXML(/** @lang XML */ <<<HERE
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
            mc:Ignorable="w14 wp14">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Normal"/>
                <w:bidi w:val="0"/>
                <w:jc w:val="left"/>
                <w:rPr>
                    <w:b/>
                    <w:b/>
                    <w:bCs/>
                </w:rPr>
            </w:pPr>
            <w:r>
                <w:rPr></w:rPr>
                <w:t> Dari hsil yang tlah diperoleh pda tahapan pengjian </w:t>
            </w:r>
        </w:p>
        <w:sectPr>
            <w:type w:val="nextPage"/>
            <w:pgSz w:w="11906" w:h="16838"/>
            <w:pgMar w:left="1134" w:right="1134" w:header="0" w:top="1134" w:footer="0" w:bottom="1134" w:gutter="0"/>
            <w:pgNumType w:fmt="decimal"/>
            <w:formProt w:val="false"/>
            <w:textDirection w:val="lrTb"/>
            <w:docGrid w:type="default" w:linePitch="100" w:charSpace="0"/>
        </w:sectPr>
    </w:body>
</w:document>
HERE
        );
        return $domDocument;
    }
}