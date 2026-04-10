<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ImportListRequest;
use App\Services\Import\ImportListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends ApiController
{
    public function __construct(
        private readonly ImportListService $importListService,
    ) {
    }

    /**
     * Get the list of imports
     */
    public function list(ImportListRequest $request): JsonResponse
    {
        return $this->successResponse($this->importListService->paginatedList($request));
    }

    /**
     * Get the details of an import
     */
    public function details(Request $request, string $id): JsonResponse
    {
        $payload = $this->importListService->details($request, $id);

        if ($payload === null) {
            return $this->errorResponse('Import not found', 404);
        }

        return $this->successResponse($payload);
    }
}
