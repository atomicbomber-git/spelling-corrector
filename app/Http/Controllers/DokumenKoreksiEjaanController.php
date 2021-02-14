<?php

namespace App\Http\Controllers;

use App\DokumenWord;
use App\Support\FileConverter;
use App\Support\MessageState;
use App\Support\SessionHelper;
use App\Support\StringUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Shared\ZipArchive;

class DokumenKoreksiEjaanController extends Controller
{
    private ResponseFactory $responseFactory;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param DokumenWord $dokumen_word
     * @return \Illuminate\Http\Response
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

        $replacementPairs = (new Collection($data["corrections"]))
            ->pluck("replacements", "original")
            ->toArray();

        $docxFilepath = $dokumen_word->getFirstMediaPath(DokumenWord::COLLECTION_WORD_FILE);
        $docxAsZipArchive = new ZipArchive();
        $docxAsZipArchive->open($docxFilepath);

        if ($docxAsZipArchive->open($docxFilepath)) {
            $documentXmlPath = "word/document.xml";
            $documentContent = $docxAsZipArchive->getFromName($documentXmlPath);

            foreach ($replacementPairs as $original => $replacements) {
                $replacements = array_filter(
                    $replacements,
                    fn ($replacement) => strtolower($replacement["correction"]) !== strtolower($original)
                );

                $replacements = array_map(
                    fn ($replacement) => array_merge($replacement, [
                        "correction" => "$1{$replacement['correction']}$3"
                    ]),
                    $replacements,
                );

                $original = preg_quote($original, "/");
                $delimiter = $this->getWordDelimitersRegex();

                $documentContent = StringUtil::replaceAllRegexMultiple(
                    "/([{$delimiter}])({$original})([{$delimiter}])/i",
                    $replacements,
                    $documentContent
                );
            }

            $docxAsZipArchive->addFromString($documentXmlPath, $documentContent);
            $docxAsZipArchive->close();
        } else {
            throw new \Exception("Failed to open docx file.");
        }

        $dokumen_word->update([
            "konten_html" => FileConverter::wordToHTML($docxFilepath)
        ]);

        SessionHelper::flashMessage(
            __("messages.update.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->noContent(200);
    }

    private function getWordDelimitersRegex(): string
    {
        return preg_quote(implode("", [
            ',', '<', '>', '"', '\'', '(', ')', '.', '!', '?', ' ', ':', ';', '-',
        ]));
    }
}
