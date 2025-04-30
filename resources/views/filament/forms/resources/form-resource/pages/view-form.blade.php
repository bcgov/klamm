<x-filament-panels::page>
    <div class="p-6 bg-white rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-6">
                <div>
                    <span class="block text-lg font-bold mb-2">Form ID</span>
                    <span class="text-gray-800 block">
                        {{ $record->form_id }}
                    </span>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Form Title</span>
                    <span class="text-gray-800 block">
                        {{ $record->form_title }}
                    </span>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Ministry</span>
                    <span class="text-gray-800 block">
                        {{ $record->ministry->name ?? 'N/A' }}
                    </span>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Business Areas</span>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse($record->businessAreas as $businessArea)
                        <x-filament::badge class="!inline-flex">
                            {{ $businessArea->name }}
                        </x-filament::badge>
                        @empty
                        <span class="text-gray-500">N/A</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Program</span>
                    <span class="text-gray-800 block">
                        {{ $record->program ?? 'N/A' }}
                    </span>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <span class="block text-lg font-bold mb-2">Status</span>
                    <span class="text-gray-800 mt-1 inline-block">
                        <x-filament::badge
                            :color="$record->decommissioned ? 'danger' : 'success'"
                            class="!inline-flex">
                            {{ $record->decommissioned ? 'Decommissioned' : 'Active' }}
                        </x-filament::badge>
                    </span>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">ICM Status</span>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-800">Generated:</span>
                            <x-filament::badge
                                :color="$record->icm_generated ? 'success' : 'gray'"
                                class="!inline-flex">
                                {{ $record->icm_generated ? 'Yes' : 'No' }}
                            </x-filament::badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-800">Non-Interactive:</span>
                            <x-filament::badge
                                :color="$record->icm_non_interactive ? 'warning' : 'gray'"
                                class="!inline-flex">
                                {{ $record->icm_non_interactive ? 'Yes' : 'No' }}
                            </x-filament::badge>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Form Type</span>
                    <div class="space-y-2">
                        <div>
                            <span class="text-gray-800">Fill Type:</span>
                            <span class="text-gray-800 ml-2">
                                {{ $record->fillType->name ?? 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-800">Frequency:</span>
                            <span class="text-gray-800 ml-2">
                                {{ $record->formFrequency->name ?? 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-800">Reach:</span>
                            <span class="text-gray-800 ml-2">
                                {{ $record->formReach->name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-6 border-gray-200 mt-3">

        <div>
            <span class="block text-lg font-bold mb-2 pt-3">Form Purpose</span>
            <div class="text-gray-800 mt-2 prose max-w-none">
                {{ $record->form_purpose ?? 'No purpose provided.' }}
            </div>
        </div>

        <hr class="my-6 border-gray-200 mt-3">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-6">
                <div>
                    <span class="block text-lg font-bold mb-2 pt-3">Software Sources</span>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse($record->formSoftwareSources as $source)
                        <x-filament::badge class="!inline-flex">
                            {{ $source->name }}
                        </x-filament::badge>
                        @empty
                        <span class="text-gray-500">N/A</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Locations</span>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse($record->formLocations as $location)
                        <x-filament::badge class="!inline-flex">
                            {{ $location->name }}
                        </x-filament::badge>
                        @empty
                        <span class="text-gray-500">N/A</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Repositories</span>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse($record->formRepositories as $repo)
                        <x-filament::badge class="!inline-flex">
                            {{ $repo->name }}
                        </x-filament::badge>
                        @empty
                        <span class="text-gray-500">N/A</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <span class="block text-lg font-bold mb-2">Tags</span>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse($record->formTags as $tag)
                        <x-filament::badge class="!inline-flex">
                            {{ $tag->name }}
                        </x-filament::badge>
                        @empty
                        <span class="text-gray-500">N/A</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">User Types</span>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse($record->userTypes as $type)
                        <x-filament::badge class="!inline-flex">
                            {{ $type->name }}
                        </x-filament::badge>
                        @empty
                        <span class="text-gray-500">N/A</span>
                        @endforelse
                    </div>
                </div>

                <div>
                    <span class="block text-lg font-bold mb-2">Retention Needs</span>
                    <span class="text-gray-800 block">
                        {{ $record->retention_needs ? $record->retention_needs.' years' : 'N/A' }}
                    </span>
                </div>
            </div>
        </div>

        <hr class="my-6 border-gray-200 mt-3">

        <div>
            <span class="block text-lg font-bold mb-2 pt-3">Links</span>
            @if($record->links->count() > 0)
            <div class="space-y-2 mt-2">
                @foreach($record->links as $link)
                <div class="flex items-center">
                    <a href="{{ $link->link }}" target="_blank" class="text-primary-600 hover:text-primary-800">
                        {{ $link->link }}
                    </a>
                </div>
                @endforeach
            </div>
            @else
            <span class="text-gray-500">No links provided.</span>
            @endif
        </div>

        <hr class="my-6 border-gray-200 mt-3">

        <div>
            <span class="block text-lg font-bold mb-2 pt-3">Notes</span>
            <div class="text-gray-800 mt-2 prose max-w-none">
                {{ $record->notes ?? 'No notes provided.' }}
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