<x-filament::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Selection Summary</x-slot>
            <x-slot name="description">Snapshot of the scope targeted by this anonymization job.</x-slot>

            <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Databases</dt>
                    <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $record->databases->count() }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Schemas</dt>
                    <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $record->schemas->count() }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Tables</dt>
                    <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $record->tables->count() }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Columns</dt>
                    <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $record->columns->count() }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Selected Databases</x-slot>
            @forelse ($record->databases as $database)
            <div class="flex items-start justify-between border-b border-slate-100 py-3 last:border-b-0">
                <div>
                    <div class="text-sm font-medium text-slate-900">{{ $database->database_name }}</div>
                    @if ($database->description)
                    <div class="text-xs text-slate-500">{{ $database->description }}</div>
                    @endif
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ $database->schemas->count() }} schemas
                </span>
            </div>
            @empty
            <p class="text-sm text-slate-500">No databases selected.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Selected Schemas</x-slot>
            @forelse ($record->schemas as $schema)
            <div class="flex items-start justify-between border-b border-slate-100 py-3 last:border-b-0">
                <div>
                    <div class="text-sm font-medium text-slate-900">{{ $schema->schema_name }}</div>
                    <div class="text-xs text-slate-500">{{ $schema->database?->database_name ?? 'Unknown database' }}</div>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ $schema->tables->count() }} tables
                </span>
            </div>
            @empty
            <p class="text-sm text-slate-500">No schemas selected.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Selected Tables</x-slot>
            @forelse ($record->tables as $table)
            <div class="flex items-start justify-between border-b border-slate-100 py-3 last:border-b-0">
                <div>
                    <div class="text-sm font-medium text-slate-900">{{ $table->table_name }}</div>
                    <div class="text-xs text-slate-500">
                        {{ $table->schema?->schema_name ?? 'Unknown schema' }} · {{ $table->schema?->database?->database_name ?? 'Unknown database' }}
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ $table->columns_count ?? 0 }} columns
                </span>
            </div>
            @empty
            <p class="text-sm text-slate-500">No tables selected.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Selected Columns</x-slot>
            @if ($record->columns->isEmpty())
            <p class="text-sm text-slate-500">No columns selected.</p>
            @else
            <div class="rounded-xl border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Column</th>
                                <th class="px-4 py-3">Table</th>
                                <th class="px-4 py-3">Schema</th>
                                <th class="px-4 py-3">Method</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($record->columns as $column)
                            @php
                            $table = $column->table;
                            $schema = $table?->schema;
                            $methodId = $column->pivot?->anonymization_method_id;
                            $method = $methodId ? $record->methods->firstWhere('id', $methodId) : null;
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-slate-900">{{ $column->column_name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $table?->table_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $schema?->schema_name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($method)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $method->name }}</span>
                                    @else
                                    <span class="text-xs text-slate-400">Not assigned</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </x-filament::section>
    </div>
</x-filament::page>