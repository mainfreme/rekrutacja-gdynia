<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UploadImportController;
use App\Http\Controllers\Api\ImportController;

Route::post('/imports', [UploadImportController::class, 'upload']);

Route::get('/imports', [ImportController::class, 'list']);

Route::get('/imports/{id}', [ImportController::class, 'details']);