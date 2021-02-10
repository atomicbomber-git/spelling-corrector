<?php

use App\Http\Controllers\DokumenWordController;
use App\Http\Controllers\ImportWordsFromDocumentController;
use App\Http\Controllers\SpellingCorrectorFormController;
use App\Http\Controllers\SpellingCorrectorProcessController;
use App\Http\Controllers\WordController;
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

Route::redirect("/", "/login");
Route::resource("dokumen-word", class_basename(DokumenWordController::class));


//Route::get("/", class_basename(SpellingCorrectorFormController::class));
//Route::post("/", class_basename(SpellingCorrectorProcessController::class));
Route::resource("/word", class_basename(WordController::class));
Route::post("/import-words-from-document", class_basename(ImportWordsFromDocumentController::class))->name("import-words-from-document");
