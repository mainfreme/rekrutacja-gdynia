<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Http\Requests\ImportListRequest;
use App\Http\Resources\ImportResource;
use App\Models\Import;
use Illuminate\Http\Request;

final class ImportListService
{
    /**
     * @return array{imports: array<int, mixed>, pagination: array<string, int|null>}
     */
    public function paginatedList(ImportListRequest $request): array
    {
        $perPage = (int) $request->validated('per_page');
        $currentPage = (int) $request->validated('current_page');

        $paginator = Import::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'current_page',
                page: $currentPage,
            );

        return [
            'imports' => ImportResource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @return array{import: array<string, mixed>}|null
     */
    public function details(Request $request, string $id): ?array
    {
        $import = Import::query()->find($id);

        if ($import === null) {
            return null;
        }

        return [
            'import' => (new ImportResource($import))->toArray($request),
        ];
    }
}
