<x-filament-panels::page>
    <div class="p-6 bg-white rounded-lg shadow">
        <div class="space-y-4">
            <div>
                <span class="block text-lg font-bold" style="font-size: 20px;">Business Area:</span>
                <span class="text-gray-800 mt-1 inline-block" style="font-size: 16px;">
                    <x-filament::badge class="!inline-flex">
                        {{ $record->reportBusinessArea->name }}
                    </x-filament::badge>
                </span>
            </div>
            <div>
                <span class="block text-lg font-bold" style="font-size: 20px;">Report Name:</span>
                <span class="text-gray-800 mt-1 block" style="font-size: 16px;">
                    {{ $record->report->name }}
                </span>
            </div>
            <div>
                <span class="block text-lg font-bold" style="font-size: 20px;">Existing Label:</span>
                <span class="text-gray-800 mt-1 block" style="font-size: 16px;">
                    {{ $record->existing_label }}
                </span>
            </div>
        </div>
        <hr class="my-4 border-gray-200">
        <div class="space-y-4">
            <div>
                <span class="block text-lg font-bold" style="font-size: 20px;">Label Source:</span>
                <span class="text-gray-800 mt-1 block" style="font-size: 16px;">
                    {{ $record->labelSource->name ?? 'N/A' }}
                </span>
            </div>
            <div>
                <span class="block text-lg font-bold" style="font-size: 20px;">Data Field:</span>
                <span class="text-gray-800 mt-1 block" style="font-size: 16px;">
                    {{ $record->data_field ?? 'N/A' }}
                </span>
            </div>
            <div>
                <span class="block text-lg font-bold" style="font-size: 20px;">ICM Data Label Path:</span>
                <span class="text-gray-800 mt-1 block" style="font-size: 16px;">
                    {{ $record->icm_data_field_path ?? 'N/A' }}
                </span>
            </div>
            <div>
                <span class="block text-lg font-bold" style="font-size: 20px;">Data Matching Rate:</span>
                <span class="text-gray-800 mt-1 block inline-block" style="font-size: 16px;">
                    @if($record->data_matching_rate === 'n/a')
                    N/A
                    @else
                    <x-filament::badge
                        :color="match($record->data_matching_rate) {
                                'low' => 'success',
                                'medium' => 'warning',
                                'high' => 'danger',
                                default => 'gray',
                            }"
                        class="!inline-flex">
                        {{ ucfirst($record->data_matching_rate) }}
                    </x-filament::badge>
                    @endif
                </span>
            </div>
        </div>
        <hr class="my-4 border-gray-200">
        <div>
            <span class="block text-lg font-bold" style="font-size: 20px;">Description:</span>
            <div class="text-gray-800 mt-2 prose max-w-none" style="font-size: 16px;">
                {{ $record->note ?? 'No description provided.' }}
            </div>
        </div>
    </div>
    @if (count($relationManagers = $this->getRelationManagers()))
    <x-filament-panels::resources.relation-managers
        :active-manager="$this->activeRelationManager"
        :managers="$relationManagers"
        :owner-record="$record"
        :page-class="static::class" />
    @endif
</x-filament-panels::page>