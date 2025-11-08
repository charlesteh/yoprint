<?php

use App\Http\Controllers\CsvUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CsvUploadController::class, 'index'])->name('home');
Route::post('/api/uploads', [CsvUploadController::class, 'store'])->name('uploads.store');
Route::post('/api/uploads/chunk', [CsvUploadController::class, 'storeChunk'])->name('uploads.store_chunk');
Route::get('/api/uploads', [CsvUploadController::class, 'index_api'])->name('uploads.index_api');
