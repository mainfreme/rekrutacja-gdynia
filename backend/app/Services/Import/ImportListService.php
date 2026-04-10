<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Http\Requests\ImportListRequest;
use App\Http\Resources\ImportResource;
use App\Http\Resources\ImportLogResource;
use App\Http\Resources\TransactionResource;
use App\Models\Import;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    /**
     * @return array{
     *     import: array<string, mixed>,
     *     transactions: array<int, mixed>,
     *     transactions_pagination: array<string, int|null>,
     *     logs: array<int, mixed>,
     *     logs_pagination: array<string, int|null>,
     * }|null
     */
    public function details(Request $request, string $id): ?array
    {
        $import = Import::query()->find($id);

        if ($import === null) {
            return null;
        }

        $transactionsPerPage = $this->clampPerPage((int) $request->input('transactions_per_page', 30));
        $transactionsPage = max(1, (int) $request->input('transactions_page', 1));
        $logsPerPage = $this->clampPerPage((int) $request->input('logs_per_page', 30));
        $logsPage = max(1, (int) $request->input('logs_page', 1));

        $transactionsPaginator = $import->transactions()
            ->orderBy('id')
            ->paginate(
                perPage: $transactionsPerPage,
                columns: ['*'],
                pageName: 'transactions_page',
                page: $transactionsPage,
            );

        $logsPaginator = $import->logs()
            ->orderByDesc('id')
            ->paginate(
                perPage: $logsPerPage,
                columns: ['*'],
                pageName: 'logs_page',
                page: $logsPage,
            );

        return [
            'import' => (new ImportResource($import))->resolve($request),
            'transactions' => TransactionResource::collection($transactionsPaginator->getCollection())->resolve($request),
            'transactions_pagination' => $this->paginationMeta($transactionsPaginator),
            'logs' => ImportLogResource::collection($logsPaginator->getCollection())->resolve($request),
            'logs_pagination' => $this->paginationMeta($logsPaginator),
        ];
    }

    /**
     * @param  LengthAwarePaginator<\Illuminate\Database\Eloquent\Model>  $paginator
     * @return array<string, int|null>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function clampPerPage(int $perPage): int
    {
        if ($perPage < 1) {
            return 1;
        }

        return min($perPage, 100);
    }
}
