<?php

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

Route::get("/", class_basename(\App\Http\Controllers\SpellingCorrectorFormController::class));
Route::post("/", class_basename(\App\Http\Controllers\SpellingCorrectorProcessController::class));
