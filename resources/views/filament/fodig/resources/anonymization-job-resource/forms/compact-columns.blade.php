@php
    /** @var \App\Filament\Fodig\Resources\AnonymizationJobResource\Pages\EditAnonymizationJob $livewire */
    $livewire = $livewire ?? $getLivewire();
    $paginator = $livewire->paginatedJobColumns;
    $total = $livewire->getJobColumnsTotal();
    $perPage = \App\Filament\Fodig\Resources\AnonymizationJobResource::JOB_COLUMNS_PER_PAGE;
@endphp

<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
        <strong>{{ number_format($total) }}</strong> columns are saved on this job.
        The list below is paginated ({{ number_format($perPage) }} per page) so the edit form stays responsive.
        Use search to filter, or add columns with the picker above.
    </div>

    <div>
        <label for="job-columns-search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Filter saved columns
        </label>
        <input
            id="job-columns-search"
            type="search"
            wire:model.live.debounce.300ms="jobColumnsSearch"
            placeholder="Search by database, schema, table, or column…"
            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
        />
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Column</th>
                        <th class="px-4 py-3">Table</th>
                        <th class="px-4 py-3">Schema</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    @forelse ($paginator as $row)
                        <tr wire:key="job-column-{{ $row->column_id }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                {{ $row->column_name }}
                                <div class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                    {{ $row->database_name }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row->table_name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row->schema_name }}</td>
                            <td class="px-4 py-3">
                                @if ($row->method_name)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">
                                        {{ $row->method_name }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">Not assigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="removeJobColumn({{ (int) $row->column_id }})"
                                    wire:confirm="Remove this column from the job selection?"
                                    class="text-xs font-medium text-danger-600 hover:text-danger-500 dark:text-danger-400"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                No columns match your filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($paginator->hasPages())
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-600 dark:text-gray-400">
            <span>
                Showing {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}
                of {{ number_format($paginator->total()) }} matching
            </span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="goToJobColumnsPage({{ max(1, $paginator->currentPage() - 1) }})"
                    @disabled($paginator->onFirstPage())
                    class="rounded-lg border border-gray-300 px-3 py-1.5 disabled:opacity-40 dark:border-gray-600"
                >
                    Previous
                </button>
                <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                <button
                    type="button"
                    wire:click="goToJobColumnsPage({{ min($paginator->lastPage(), $paginator->currentPage() + 1) }})"
                    @disabled(! $paginator->hasMorePages())
                    class="rounded-lg border border-gray-300 px-3 py-1.5 disabled:opacity-40 dark:border-gray-600"
                >
                    Next
                </button>
            </div>
        </div>
    @endif
</div>
