<?php


namespace Tests\Support;


use App\DocumentProcessing\SubstitutionList;
use App\DomDocumentNLPTools\OldTokenizer;
use App\DomDocumentNLPTools\Sentence;
use App\DomDocumentNLPTools\Word;
use App\Http\Controllers\DokumenKoreksiEjaanController;
use App\RecommendationEngine\WordRecommender;
use DirectoryIterator;
use DOMDocument;
use Exception;
use SplFileInfo;
use Tests\TestCase;
use ZipArchive;

class DocumentCorrectionTest extends TestCase
{
    /** @test */
    public function it_can_correct_documents()
    {
        /** @var DirectoryIterator | SplFileInfo[] $directory */
        $directory = new DirectoryIterator(__DIR__ . "/" . "test_documents");

        /** @var array | DOMDocument[] $domDocuments */
        $domDocuments = [];

        foreach ($directory as $file) {
            if ($file->getExtension() !== "docx") continue;
            $zipArchive = new ZipArchive();
            $zipResource = $zipArchive->open($file->getRealPath());

            if ($zipResource === true) {
                $fileContentAsText = $zipArchive->getFromName("word/document.xml");
                $domDocument = new DOMDocument();
                $domDocument->loadXML($fileContentAsText);
                $domDocuments[] = $domDocument;
            } else {
                throw new Exception("Failed to open zip file.");
            }
        }

        collect($domDocuments)
            ->each(function (DOMDocument $document) {
                $tokenizer = new OldTokenizer();
                $tokenizer->load($document);

                $sentences = $tokenizer->tokenizeWithSquashing();
                $sentences = array_map([$this, "filterTokensInSentences"], $sentences);

                $recommendations = collect((new WordRecommender)->getRecommendations($sentences));

                $substitutionList =
                    $recommendations
                        ->reduce(function (SubstitutionList $sub, array $wordAndRecs) {
                            $sub->addEntry(
                                $wordAndRecs["value"],
                                $wordAndRecs["index"],
                                array_key_first($wordAndRecs["recommendations"]),
                            );
                            return $sub;
                        }, new SubstitutionList());

                $newDomDocument = DokumenKoreksiEjaanController::substituteWordsInDomDocument($document, $tokenizer, $substitutionList);
                $newTokenizer = new OldTokenizer();
                $newTokenizer->load($newDomDocument);
                $sentences = array_map([$this, "filterTokensInSentences"], $newTokenizer->tokenizeWithSquashing());
                $recommendations = collect((new WordRecommender)->getRecommendations($sentences));

                $this->assertCount(
                    0,
                    $recommendations,
                );
            });
    }

    public function filterOutShortTokens(Word $token): bool
    {
        return mb_strlen($token->getNormalizedValue()) > 1;
    }

    public function filterOutNumerals(Word $token): bool
    {
        $value = $token->getNormalizedValue();

        /* Remove all punctuations and spaces so we can handle formatted numerals like 10 000 000 */
        $value = preg_replace("/[\p{P}\p{Z}]/ui", "", $value);
        return !is_numeric($value);
    }

    private function filterTokensInSentences(Sentence $sentence): Sentence
    {
        $sentence->words = collect($sentence->words)
            ->filter([$this, "filterOutShortTokens"])
            ->filter([$this, "filterOutNumerals"])
            ->toArray();

        return $sentence;
    }
}