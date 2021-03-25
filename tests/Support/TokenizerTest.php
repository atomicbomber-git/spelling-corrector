<?php


namespace Tests\Support;


use App\DomDocumentNLPTools\Tokenizer;
use Tests\TestCase;
use DOMDocument;

class TokenizerTest extends TestCase
{
    public function test_can_tokenize_with_squashing()
    {
        $tokenizer = new Tokenizer();
        $tokenizer->load($this->getTestXmlDocument());

        $sentences = $tokenizer->tokenizeWithSquashing();
        $this->assertCount(4, $sentences);
        $this->assertCount(2, $sentences[0]->words);
        $this->assertCount(1, $sentences[1]->words);
        $this->assertCount(5, $sentences[2]->words);

        $this->assertEquals(0, $sentences[0]->words[0]->posInNode);
        $this->assertEquals(0, $sentences[0]->words[0]->posInSentence);
        $this->assertEquals(1, $sentences[0]->words[1]->posInNode);
        $this->assertEquals(1, $sentences[0]->words[1]->posInSentence);

        $this->assertEquals(0, $sentences[1]->words[0]->posInNode);
        $this->assertEquals(0, $sentences[1]->words[0]->posInSentence);

        $this->assertEquals("Œuf", $sentences[2]->words[2]->rawValue);
        $this->assertEquals("œuf", $sentences[2]->words[2]->getNormalizedValue());
        $this->assertEquals(0, $sentences[2]->words[2]->posInSentence);
        $this->assertEquals(0, $sentences[2]->words[2]->posInNode);

        $this->assertEquals("Halloween", $sentences[2]->words[3]->rawValue);
        $this->assertEquals("halloween", $sentences[2]->words[3]->getNormalizedValue());
        $this->assertEquals(0, $sentences[2]->words[3]->posInSentence);
        $this->assertEquals(0, $sentences[2]->words[3]->posInNode);
        $this->assertEquals(" Hallo schönbrunn Œuf Halloween", $sentences[2]->words[3]->nodes[0]->textContent);

        $this->assertEquals("LITTLE", $sentences[3]->words[0]->rawValue);
        $this->assertEquals("little", $sentences[3]->words[0]->getNormalizedValue());
        $this->assertEquals(0, $sentences[3]->words[0]->posInSentence);
        $this->assertEquals(0, $sentences[3]->words[0]->posInNode);

        $this->assertEquals("LAMB,", $sentences[3]->words[1]->rawValue);
        $this->assertEquals("lamb", $sentences[3]->words[1]->getNormalizedValue());
        $this->assertEquals(0, $sentences[3]->words[1]->posInSentence);
        $this->assertEquals(0, $sentences[3]->words[1]->posInNode);

        $this->assertEquals("Little", $sentences[3]->words[2]->rawValue);
        $this->assertEquals("little", $sentences[3]->words[2]->getNormalizedValue());
        $this->assertEquals(1, $sentences[3]->words[2]->posInSentence);
        $this->assertEquals(1, $sentences[3]->words[2]->posInNode);

        $this->assertEquals("Lamb,", $sentences[3]->words[3]->rawValue);
        $this->assertEquals("lamb", $sentences[3]->words[3]->getNormalizedValue());
        $this->assertEquals(1, $sentences[3]->words[3]->posInSentence);
        $this->assertEquals(1, $sentences[3]->words[3]->posInNode);

        $this->assertEquals("little", $sentences[3]->words[4]->rawValue);
        $this->assertEquals("little", $sentences[3]->words[4]->getNormalizedValue());
        $this->assertEquals(2, $sentences[3]->words[4]->posInSentence);
        $this->assertEquals(2, $sentences[3]->words[4]->posInNode);

        $this->assertEquals("lamb", $sentences[3]->words[5]->rawValue);
        $this->assertEquals("lamb", $sentences[3]->words[5]->getNormalizedValue());
        $this->assertEquals(2, $sentences[3]->words[5]->posInSentence);
        $this->assertEquals(2, $sentences[3]->words[5]->posInNode);
    }

    private function getTestXmlDocument(): DOMDocument
    {
        $domDocument = new DOMDocument();
        $domDocument->loadXML(file_get_contents(__DIR__ . "/test-document.xml"));
        return $domDocument;
    }
}