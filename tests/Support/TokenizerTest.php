<?php


namespace Tests\Support;


use App\DomDocumentNLPTools\Tokenizer;
use Tests\TestCase;
use DOMDocument;
use DOMNode;

class TokenizerTest extends TestCase
{
    public function test_can_tokenize_with_squashing()
    {
        $tokenizer = new Tokenizer($this->getTestXmlDocument());

        $sentences = $tokenizer->tokenizeWithSquashing();
        $this->assertCount(4, $sentences);
        $this->assertCount(2, $sentences[0]->tokens);
        $this->assertCount(1, $sentences[1]->tokens);
        $this->assertCount(5, $sentences[2]->tokens);

        $this->assertEquals(0, $sentences[0]->tokens[0]->posInNode);
        $this->assertEquals(0, $sentences[0]->tokens[0]->posInSentence);
        $this->assertEquals(1, $sentences[0]->tokens[1]->posInNode);
        $this->assertEquals(1, $sentences[0]->tokens[1]->posInSentence);

        $this->assertEquals(0, $sentences[1]->tokens[0]->posInNode);
        $this->assertEquals(0, $sentences[1]->tokens[0]->posInSentence);

        $this->assertEquals("Œuf", $sentences[2]->tokens[2]->rawValue);
        $this->assertEquals("œuf", $sentences[2]->tokens[2]->getNormalizedValue());
        $this->assertEquals(0, $sentences[2]->tokens[2]->posInSentence);
        $this->assertEquals(0, $sentences[2]->tokens[2]->posInNode);

        $this->assertEquals("Halloween", $sentences[2]->tokens[3]->rawValue);
        $this->assertEquals("halloween", $sentences[2]->tokens[3]->getNormalizedValue());
        $this->assertEquals(0, $sentences[2]->tokens[3]->posInSentence);
        $this->assertEquals(0, $sentences[2]->tokens[3]->posInNode);
        $this->assertEquals(" Hallo schönbrunn Œuf Halloween", $sentences[2]->tokens[3]->nodes[0]->textContent);

        $this->assertEquals("LITTLE", $sentences[3]->tokens[0]->rawValue);
        $this->assertEquals("little", $sentences[3]->tokens[0]->getNormalizedValue());
        $this->assertEquals(0, $sentences[3]->tokens[0]->posInSentence);
        $this->assertEquals(0, $sentences[3]->tokens[0]->posInNode);

        $this->assertEquals("LAMB", $sentences[3]->tokens[1]->rawValue);
        $this->assertEquals("lamb", $sentences[3]->tokens[1]->getNormalizedValue());
        $this->assertEquals(0, $sentences[3]->tokens[1]->posInSentence);
        $this->assertEquals(0, $sentences[3]->tokens[1]->posInNode);

        $this->assertEquals("Little", $sentences[3]->tokens[2]->rawValue);
        $this->assertEquals("little", $sentences[3]->tokens[2]->getNormalizedValue());
        $this->assertEquals(1, $sentences[3]->tokens[2]->posInSentence);
        $this->assertEquals(1, $sentences[3]->tokens[2]->posInNode);

        $this->assertEquals("Lamb", $sentences[3]->tokens[3]->rawValue);
        $this->assertEquals("lamb", $sentences[3]->tokens[3]->getNormalizedValue());
        $this->assertEquals(1, $sentences[3]->tokens[3]->posInSentence);
        $this->assertEquals(1, $sentences[3]->tokens[3]->posInNode);

        $this->assertEquals("little", $sentences[3]->tokens[4]->rawValue);
        $this->assertEquals("little", $sentences[3]->tokens[4]->getNormalizedValue());
        $this->assertEquals(2, $sentences[3]->tokens[4]->posInSentence);
        $this->assertEquals(2, $sentences[3]->tokens[4]->posInNode);

        $this->assertEquals("lamb", $sentences[3]->tokens[5]->rawValue);
        $this->assertEquals("lamb", $sentences[3]->tokens[5]->getNormalizedValue());
        $this->assertEquals(2, $sentences[3]->tokens[5]->posInSentence);
        $this->assertEquals(2, $sentences[3]->tokens[5]->posInNode);
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