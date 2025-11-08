<?php

use App\Http\Controllers\CsvUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/uploads', [CsvUploadController::class, 'store'])->name('uploads.store');
Route::post('/uploads/chunk', [CsvUploadController::class, 'storeChunk'])->name('uploads.store_chunk');
Route::get('/uploads', [CsvUploadController::class, 'index_api'])->name('uploads.index_api');
Route::delete('/reset-database', [CsvUploadController::class, 'resetDatabase'])->name('reset.database');
