<x-filament-panels::page>
    <div x-data="{ expanded: false }" class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow">
        <button
            @click="expanded = !expanded"
            class="w-full px-4 py-3 text-left flex items-center justify-between transition">
            <div class="font-medium text-gray-900 dark:text-white">
                Column Heading Definitions
            </div>
            <div class="text-gray-500 dark:text-gray-300">
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
            class="px-4 pb-4 pt-2 border-t border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 space-y-4">

            <div class="space-y-4">
                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Business Area</p>
                    <p class="text-gray-400 dark:text-gray-100">The business area most invested in the use of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Report Name</p>
                    <p class="text-gray-400 dark:text-gray-300">The name of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Report Description</p>
                    <p class="text-gray-400 dark:text-gray-300">The description of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Existing Label</p>
                    <p class="text-gray-400 dark:text-gray-300">The MIS label that exists on the current version of the report.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Dictionary Name</p>
                    <p class="text-gray-400 dark:text-gray-300">After analysis, the dictionary name that the Finanical Components team wants to use.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">ICM Data Field Path</p>
                    <p class="text-gray-400 dark:text-gray-300">The field's path within ICM.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Label Source</p>
                    <p class="text-gray-400 dark:text-gray-300">Where the label comes from.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Data Match Effort</p>
                    <p class="text-gray-400 dark:text-gray-300">How complex the data matching effort is. The definitions are outlined below:</p>
                    <p class="text-gray-400 dark:text-gray-300">
                        <span class="font-bold">
                            Low:
                        </span>
                        The data field exists in ICM with no manipulation required from the source system.
                    </p>

                    <p class="text-gray-400 dark:text-gray-300">
                        <span class="font-bold">
                            Medium:
                        </span>
                        Some transformation is required from the source system to create the data field, but the logic is already known.
                    </p>

                    <p class="text-gray-400 dark:text-gray-300">
                        <span class="font-bold">
                            High:
                        </span>
                        Transformation is required from multiple sources, and the logic may be known.
                    </p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Follow Up Required</p>
                    <p class="text-gray-400 dark:text-gray-300">Is there any follow up required with other teams to help us define the report requirement/label.</p>
                </div>

                <div>
                    <p class="font-bold text-gray-900 dark:text-white">Source Data Field</p>
                    <p class="text-gray-400 dark:text-gray-300">Who will build out this requirement in the future state ? i.e. Financial Component or another team?</p>
                </div>
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>