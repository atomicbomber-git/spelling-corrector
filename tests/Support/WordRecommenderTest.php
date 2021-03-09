<?php


namespace Tests\Support;


use App\DomDocumentNLPTools\Sentence;
use App\DomDocumentNLPTools\Token;
use App\DomDocumentNLPTools\Tokenizer;
use Tests\TestCase;
use DOMDocument;

class WordRecommender
{
    static $cachedResults = [];

    /** @var array | Token */
    public $tokens = [];

    /**
     * @param array | Sentence[] $sentences
     * @return array
     */
    public function getRecommendations(array $sentences): array
    {
        foreach ($sentences as $sentence) {
            array_push($this->tokens, $sentence->tokens);
        }
    }
}

class WordRecommenderTest extends TestCase
{
    public function test_can_give_recommendations()
    {
        $tokenizer = new Tokenizer($this->getTestXmlDocument());
        $sentences = $tokenizer->tokenize();


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
                <w:t>hello hel</w:t>
                <w:t>l</w:t>
                <w:t>o... hello</w:t><w:br/><w:t> Hallo schönbrunn Œuf Hallo</w:t>
                <w:t>ween Hallo</w:t>
                <w:t>. LITTLE LAMB, Little Lamb, little lamb </w:t>
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