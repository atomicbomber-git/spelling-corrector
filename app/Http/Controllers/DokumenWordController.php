<?php

namespace App\Http\Controllers;

use App\Constants\MessageState;
use App\DocumentProcessing\WordAndComponentNodesPair;
use App\DocumentProcessing\WordXmlProcessor;
use App\DokumenWord;
use App\DomDocumentNLPTools\Sentence;
use App\DomDocumentNLPTools\Token;
use App\DomDocumentNLPTools\Tokenizer;
use App\RecommendationEngine\WordRecommender;
use App\Support\FileConverter;
use App\Support\SessionHelper;
use Barryvdh\Debugbar\LaravelDebugbar;
use DebugBar\DebugBar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use NlpTools\Tokenizers\TokenizerInterface;
use NlpTools\Tokenizers\WhitespaceTokenizer;
use function Symfony\Component\String\s;

class DokumenWordController extends Controller
{
    private ResponseFactory $responseFactory;
    private TokenizerInterface $tokenizer;

    public function __construct(ResponseFactory $responseFactory)
    {
        $this->middleware("auth");
        $this->responseFactory = $responseFactory;
        $this->tokenizer = new WhitespaceTokenizer();
    }

    public function index(): Response
    {
        return $this->responseFactory->view("dokumen-word.index", [
            "dokumen_words" => DokumenWord::query()
                ->where("user_id", Auth::id())
                ->orderByDesc("updated_at")
                ->orderByDesc("created_at")
                ->paginate()
        ]);
    }

    public function filterOutShortTokens(Token $token): bool {
        return mb_strlen($token->getNormalizedValue()) > 1;
    }

    public function filterOutNumerals(Token $token): bool {
        $value = $token->getNormalizedValue();

        /* Remove all punctuations and spaces so we can handle formatted numerals like 10 000 000 */
        $value = preg_replace("/[\p{P}\p{Z}]/ui", "", $value);
        return ! is_numeric($value);
    }

    private function filterTokensInSentences(Sentence $sentence): Sentence {
        $sentence->tokens = collect($sentence->tokens)
            ->filter([$this, "filterOutShortTokens"])
            ->filter([$this, "filterOutNumerals"])
            ->toArray();

        return $sentence;
    }

    public function show(Request $request, DokumenWord $dokumen_word)
    {
        if ($request->ajax()) {
            return $this->responseFactory->json([
                "nama" => $dokumen_word->nama,
                "konten_html" => $dokumen_word->getHtml()
            ]);
        }

        $tokenizer = new Tokenizer();
        $tokenizer->load($dokumen_word->getWordXmlDomDocument());
        $sentences = $tokenizer->tokenize();

        $sentences = array_map([$this, "filterTokensInSentences"], $sentences);

        return $this->responseFactory->view("dokumen-word.show", [
            "dokumen_word" => $dokumen_word->makeHidden("konten_html"),
            "tokens_with_error" => (new WordRecommender)->getRecommendations($sentences),
        ]);
    }

    public function create()
    {
        return $this->responseFactory->view("dokumen-word.create");
    }

    public function edit(DokumenWord $dokumen_word)
    {
        return $this->responseFactory->view("dokumen-word.edit", [
            "dokumen_word" => $dokumen_word,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "nama" => ["required", "string", Rule::unique(DokumenWord::class)->where("user_id", Auth::id())],
            "berkas" => ["required", "file", "mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document"],
        ], [
            "berkas.mimetypes" => "Berkas harus dalam format .docx",
        ]);

        DB::beginTransaction();

        $dokumenWord = DokumenWord::query()
            ->create([
                "user_id" => Auth::id(),
                "nama" => $data["nama"],
            ]);

        try {
            $dokumenWord->saveHtml(
                FileConverter::wordToHTML(
                    $request->file("berkas")->getRealPath(),
                )
            );
        } catch (HttpClientException $exception) {
            throw ValidationException::withMessages([
                "berkas" => "Gagal menghubungi server dokumen di " . config("application.document_server_url") . "."
            ]);
        }

        $dokumenWord
            ->addMediaFromRequest("berkas")
            ->toMediaCollection(DokumenWord::COLLECTION_WORD_FILE);

        DB::commit();

        SessionHelper::flashMessage(
            __("messages.create.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->redirectToRoute(
            "dokumen-word.show",
            $dokumenWord
        );
    }

    public function update(Request $request, DokumenWord $dokumen_word)
    {
        $data = $request->validate([
            "nama" => ["required", "string", Rule::unique(DokumenWord::class)->where("user_id", Auth::id())->ignoreModel($dokumen_word)],
            "berkas" => ["nullable", "file", "mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document"],
        ], [
            "berkas.mimetypes" => "Berkas harus dalam format .docx",
        ]);

        DB::beginTransaction();

        $dokumen_word->fill([
            "nama" => $data["nama"]
        ]);

        if ($request->hasFile("berkas")) {
            $dokumen_word->saveHtml(
                FileConverter::wordToHTML(
                    $request->file("berkas")->getRealPath(),
                )
            );

            $dokumen_word
                ->addMediaFromRequest("berkas")
                ->toMediaCollection(DokumenWord::COLLECTION_WORD_FILE);
        }

        $dokumen_word->save();

        DB::commit();

        SessionHelper::flashMessage(
            __("messages.update.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->redirectToRoute("dokumen-word.show", $dokumen_word);
    }

    public function destroy(DokumenWord $dokumen_word)
    {
        DB::beginTransaction();

        $dokumen_word->media()->delete();
        $dokumen_word->delete();

        DB::commit();

        SessionHelper::flashMessage(
            __("messages.update.success"),
            MessageState::STATE_SUCCESS,
        );

        return $this->responseFactory->redirectToRoute("dokumen-word.index");
    }
}
