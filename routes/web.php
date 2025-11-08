<?php

use App\Http\Controllers\CsvUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CsvUploadController::class, 'index'])->name('home');
