@php
$requiredColumns = \App\Constants\Fodig\Anonymizer\SiebelMetadata::REQUIRED_HEADER_COLUMNS;
$optionalColumns = \App\Constants\Fodig\Anonymizer\SiebelMetadata::OPTIONAL_HEADER_COLUMNS;
@endphp

<x-filament::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">How It Works</x-slot>
            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-3 text-sm text-gray-600">
                    <p>Export the latest Siebel metadata as CSV and upload it via the <strong>Import Metadata</strong> action.</p>
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>Queue the import to stream the rows into staging and reconcile the catalog. Change tracking is automatic.</li>
                    </ol>
                </div>
                <div class="grid gap-4 text-sm">

                </div>
            </div>
        </x-filament::section>

        <x-filament::section wire:poll.10s="refreshUploads">
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
                            <th class="px-3 py-2 font-semibold">Progress</th>
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
                            <td class="px-3 py-2 align-top text-xs text-gray-500">
                                <a href="{{ \App\Filament\Fodig\Resources\AnonymousUploadResource::getUrl('view', ['record' => $upload['id']]) }}" class="text-primary-600 hover:underline">
                                    {{ $upload['id'] }}
                                </a>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium text-gray-900">{{ $upload['original_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $upload['created_at_human'] ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-gray-500">{{ $upload['created_at'] }}</td>
                            <td class="px-3 py-2 align-top">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $badgeClasses }}">
                                    {{ \Illuminate\Support\Str::title($status) }}
                                </span>
                                @if(! empty($upload['status_detail']))
                                <div class="mt-1 text-xs text-gray-500">{{ $upload['status_detail'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-gray-700">
                                @php
                                $progressPercent = max(0, min(100, (int) ($upload['progress_percent'] ?? 0)));
                                $progressClass = $status === 'failed' ? 'bg-rose-500' : 'bg-blue-500';
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-2 {{ $progressClass }}" @style(['width: ' . $progressPercent . ' %'])></div>
                                    </div>
                                    <span class="font-mono text-[11px] text-gray-600">{{ $upload['progress_percent_label'] }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2 text-[11px] text-gray-500">
                                    <span>Rows: {{ $upload['processed_rows_label'] }}</span>
                                    @if($upload['total_bytes_label'] !== '—')
                                    <span>Read: {{ $upload['processed_bytes_label'] }} / {{ $upload['total_bytes_label'] }}</span>
                                    @elseif($upload['processed_bytes_label'] !== '—')
                                    <span>Read: {{ $upload['processed_bytes_label'] }}</span>
                                    @endif
                                </div>
                                @if(! empty($upload['progress_updated_at_human']))
                                <div class="mt-1 text-[10px] text-gray-400">Updated {{ $upload['progress_updated_at_human'] }}</div>
                                @endif
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
