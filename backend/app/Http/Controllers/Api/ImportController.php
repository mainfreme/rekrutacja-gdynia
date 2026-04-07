<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends ApiController
{
    /**
     * Get the list of imports
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $importDto = [];
        return $this->successResponse([
            'imports' => $importDto,
        ]);
    }

    /**
     * Get the details of an import
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function details(string $id): JsonResponse
    {
        $importDto = [];
        return $this->successResponse([
            'imports' => $importDto,
        ]);
    }
}