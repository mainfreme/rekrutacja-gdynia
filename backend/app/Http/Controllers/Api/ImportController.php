<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    /**
     * Get the list of imports
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        return response()->json(['message' => 'Import uploaded successfully']);
    }

    /**
     * Get the details of an import
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function details(Request $request, $id)
    {
        return response()->json(['message' => 'Import details']);
    }
}