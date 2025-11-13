<x-filament::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">How It Works</x-slot>
            <p class="text-sm text-gray-600">
                Use the Import Metadata action to upload a Siebel metadata CSV. The sync job will populate the anonymized catalog tables. The reset action clears everything for a fresh test run.
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Recent Uploads</x-slot>

            @if (empty($this->recentUploads))
            <p class="text-sm text-gray-500">No uploads have been queued yet.</p>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-3 py-2 font-semibold">ID</th>
                            <th class="px-3 py-2 font-semibold">File</th>
                            <th class="px-3 py-2 font-semibold">Queued At</th>
                            <th class="px-3 py-2 font-semibold">Status</th>
                            <th class="px-3 py-2 font-semibold text-right">Inserted</th>
                            <th class="px-3 py-2 font-semibold text-right">Updated</th>
                            <th class="px-3 py-2 font-semibold text-right">Deleted</th>
                            <th class="px-3 py-2 font-semibold">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($this->recentUploads as $upload)
                        @php
                        $status = $upload['status'] ?? 'unknown';
                        $badgeClasses = match ($status) {
                        'completed' => 'bg-emerald-100 text-emerald-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'failed' => 'bg-rose-100 text-rose-800',
                        'queued' => 'bg-amber-100 text-amber-800',
                        default => 'bg-gray-100 text-gray-800',
                        };
                        @endphp
                        <tr>
                            <td class="px-3 py-2 align-top text-xs text-gray-500">{{ $upload['id'] }}</td>
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium text-gray-900">{{ $upload['original_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $upload['created_at_human'] ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-gray-500">{{ $upload['created_at'] }}</td>
                            <td class="px-3 py-2 align-top">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $badgeClasses }}">
                                    {{ \Illuminate\Support\Str::title($status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-xs text-gray-700">{{ $upload['inserted'] }}</td>
                            <td class="px-3 py-2 align-top text-right text-xs text-gray-700">{{ $upload['updated'] }}</td>
                            <td class="px-3 py-2 align-top text-right text-xs text-gray-700">{{ $upload['deleted'] }}</td>
                            <td class="px-3 py-2 align-top text-xs text-rose-600">
                                {{ $upload['error'] ? \Illuminate\Support\Str::limit($upload['error'], 120) : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </x-filament::section>
    </div>
</x-filament::page>