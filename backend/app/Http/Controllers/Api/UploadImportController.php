<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ImportFileRequest;

class UploadImportController extends Controller
{
    public function upload(ImportFileRequest $request)
    {
        return response()->json(['message' => 'Import uploaded successfully']);
    }
}