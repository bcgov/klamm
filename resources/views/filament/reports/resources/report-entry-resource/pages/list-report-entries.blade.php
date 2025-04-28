<x-filament-panels::page>
    <div x-data="{ expanded: false }" class="mb-6 bg-white rounded-lg shadow">
        <button
            @click="expanded = !expanded"
            class="w-full px-4 py-3 text-left flex items-center justify-between hover:bg-gray-50 transition">
            <div class="font-medium text-gray-900">
                Column Heading Definitions
            </div>
            <div class="text-gray-500">
                <x-heroicon-o-chevron-up
                    x-show="expanded"
                    x-cloak
                    class="w-5 h-5 transform transition" />
                <x-heroicon-o-chevron-down
                    x-show="!expanded"
                    x-cloak
                    class="w-5 h-5 transform transition" />
            </div>
        </button>

        <div
            x-show="expanded"
            x-collapse
            x-cloak
            class="px-4 pb-4 pt-2 border-t border-gray-200 text-gray-700 space-y-4">

            <div class="space-y-4">
                <div>
                    <p class="font-bold text-gray-900">Business Area</p>
                    <p class="text-gray-600">The business area most invested in the use of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Report Name</p>
                    <p class="text-gray-600">The name of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Report Description</p>
                    <p class="text-gray-600">The description of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Existing Label</p>
                    <p class="text-gray-600">The MIS label that exists on the current version of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Dictionary Name</p>
                    <p class="text-gray-600">After analysis, the dictionary name that the Finanical Components team wants to use.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">ICM Data Field Path</p>
                    <p class="text-gray-600">The field's path within ICM.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Label Source</p>
                    <p class="text-gray-600">Where the label comes from.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Data Match Effort</p>
                    <p class="text-gray-600">How complex the data matching effort is. The definitions are outlined below:</p>
                    <p class="text-gray-600">
                        <span class="font-bold">
                            Low:
                        </span>
                        The data field exists in ICM with no manipulation required from the source system.
                    </p>

                    <p class="text-gray-600">
                        <span class="font-bold">
                            Medium:
                        </span>
                        Some transformation is required from the source system to create the data field, but the logic is already known.
                    </p>

                    <p class="text-gray-600">
                        <span class="font-bold">
                            High:
                        </span>
                        Transformation is required from multiple sources, and the logic may be known.
                    </p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Follow Up Required</p>
                    <p class="text-gray-600">Is there any follow up required with other teams to help us define the report requirement/label.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900">Source Data Field</p>
                    <p class="text-gray-600">Who will build out this requirement in the future state ? i.e. Financial Component or another team?</p>
                </div>
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>