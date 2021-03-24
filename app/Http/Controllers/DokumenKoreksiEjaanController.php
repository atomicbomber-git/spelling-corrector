<?php

namespace App\Http\Controllers;

use App\DocumentProcessing\SubstitutionList;
use App\DokumenWord;
use App\DomDocumentNLPTools\Tokenizer;
use App\Support\FileConverter;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\ResponseFactory;
use ZipArchive;

class DokumenKoreksiEjaanController extends Controller
{
    private ResponseFactory $responseFactory;
    private Tokenizer $tokenizer;

    public function __construct(ResponseFactory $responseFactory, Tokenizer $tokenizer)
    {
        $this->responseFactory = $responseFactory;
        $this->tokenizer = $tokenizer;
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param DokumenWord $dokumen_word
     * @return Response
     */
    public function __invoke(Request $request, DokumenWord $dokumen_word)
    {
        $data = $request->validate([
            "corrections" => ["array", "required"],
            "corrections.*.original" => ["required", "string"],
            "corrections.*.replacements" => ["required", "array"],
            "corrections.*.replacements.*.index" => ["required", "integer"],
            "corrections.*.replacements.*.correction" => ["required", "string"],
        ]);

        $substitutionList = new SubstitutionList();
        foreach ($data["corrections"] as $correction) {
            foreach ($correction["replacements"] as $replacement) {
                $substitutionList->addEntry(
                    mb_strtolower($correction["original"]),
                    $replacement["index"],
                    $replacement["correction"],
                );
            }
        }

        $wordDocumentPath = $dokumen_word->getFirstMediaPath(DokumenWord::COLLECTION_WORD_FILE);
        $zipArchive = new ZipArchive();
        $zipResource = $zipArchive->open($wordDocumentPath);
        $pathToDocumentInsideZip = "word/document.xml";

        if ($zipResource === true) {
            $domDocument = new DOMDocument();
            $domDocument->loadXML($zipArchive->getFromName($pathToDocumentInsideZip));
            $newDocument = $this->substituteWordsInDomDocument($domDocument, $substitutionList);


            $zipArchive->deleteName($pathToDocumentInsideZip);
            $zipArchive->addFromString($pathToDocumentInsideZip, $newDocument->saveXML());
            $zipArchive->close();
        } else {
            throw new Exception("Failed to open zip file.");
        }

        $dokumen_word->saveHtml(
            FileConverter::wordToHTML($wordDocumentPath)
        );

        return $this->responseFactory->noContent(200);
    }

    public function substituteWordsInDomDocument(DOMDocument $domDocument, SubstitutionList $substitutionList)
    {
        /* Obtain all words inside the dom document */
        $this->tokenizer->load($domDocument);
        $sentences = $this->tokenizer->tokenizeWithSquashing();

        /* How often has a particular word value been replaced in a domNode? */
        $wordValueDomNodeSubCounter = [];

        foreach ($sentences as $sentence) {
            foreach ($sentence->words as $word) {
                $value = $word->getNormalizedValue();
                $substitute = $substitutionList->getSubstituteFor($value, $word->index);

                /* Determine the words that need to be fixed (based on index in substitution list) */
                if ($substitute === null) {
                    continue;
                }

                /* Perform replace work word by word; Also keep a tally on how often a word's value has been replaced in a domNode */
                $targetDomNode = $word->nodes[0];
                $domNodeHash = spl_object_hash($targetDomNode);
                $backOffset = $wordValueDomNodeSubCounter[$domNodeHash][$value] ?? 0;
                $quotedValue = preg_quote($value, "/");
                $counter = 0;

                $targetTextContent = $targetDomNode->textContent;

                $targetDomNode->textContent = preg_replace_callback("/\b(_)*({$quotedValue})(_)*\b/ui", function ($match) use ($substitute, &$counter, $backOffset, $word) {
                    return ($counter++ === ($word->posInNode - $backOffset)) ?
                        ($match[1] ?? "") . $substitute . ($match[3] ?? "") :
                        $match[0];
                }, $targetTextContent);

                $wordValueDomNodeSubCounter[$domNodeHash][$value] ??= 0;
                ++$wordValueDomNodeSubCounter[$domNodeHash][$value];
            }
        }

        return $domDocument;
    }

}
