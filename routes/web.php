<?php

use App\DokumenWord;
use App\DomDocumentNLPTools\Sentence;
use App\Http\Controllers\DokumenWordController;
use App\Http\Controllers\DokumenWordDownloadController;
use App\Http\Controllers\ImportWordsFromDocumentController;
use App\Http\Controllers\DokumenKoreksiEjaanController;
use App\Http\Controllers\MahasiswaController;
use App\Http\Controllers\RekomendasiPembenaranController;
use App\Http\Controllers\WordController;
use App\Support\DomNodeTraverser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes([
    "login"
]);

Route::get("dokumen-word/{dokumen_word}/xml", function (DokumenWord $dokumen_word) {
    $path = $dokumen_word->getFirstMediaPath(DokumenWord::COLLECTION_WORD_FILE);
    $zipArchive = new \ZipArchive();
    $zipResource = $zipArchive->open($path);

    if ($zipResource === true) {
        $fileContentAsText = $zipArchive->getFromName("word/document.xml");
        $zipArchive->close();
        return response($fileContentAsText, 200, [
            "Content-Disposition" => "attachment; filename=\"document.xml\"",
            "Content-Type" => "application/xml"
        ]);
    } else {
        throw new \Exception("Failed to open zip file.");
    }
});

Route::redirect("/", "/login");
Route::resource("mahasiswa", class_basename(MahasiswaController::class));
Route::resource("dokumen-word", class_basename(DokumenWordController::class));
Route::get("dokumen-word/{dokumen_word}/download", class_basename(DokumenWordDownloadController::class))->name("dokumen-word.download");
Route::post("dokumen-word/{dokumen_word}/koreksi", class_basename(DokumenKoreksiEjaanController::class))->name("dokumen-word.koreksi-ejaan");
Route::resource("/word", class_basename(WordController::class));
Route::post("/import-words-from-document", class_basename(ImportWordsFromDocumentController::class))->name("import-words-from-document");
