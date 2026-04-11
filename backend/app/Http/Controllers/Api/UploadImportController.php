<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ImportFileRequest;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ImportResource;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class UploadImportController extends ApiController
{
    public function upload(ImportFileRequest $request): JsonResponse
    {
        $file = $request->file('file');

        try {
            $path = $file->store('imports');
            if ($path === false) {
                return $this->errorResponse(
                    'Could not store the uploaded file.',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                );
            }

            $import = Import::create([
                'file_name' => $file->getClientOriginalName(),
            ]);

            ProcessImportJob::dispatch($import->id, $path);

            $import->refresh();

            return $this->successResponse([
                'message' => 'Import uploaded successfully',
                'import' => ImportResource::make($import),
            ], '', Response::HTTP_ACCEPTED);

        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
