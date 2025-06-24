<x-filament-panels::page>
    <x-filament::section>
        <h2 class="text-lg font-bold tracking-tight">Import Form Schema</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Import a form schema from a JSON file or pasted JSON content.</p>

        @if($this->data['schema_import_job_id'] ?? false)
        <div data-job-status="{{ $this->jobStatus['status'] ?? 'unknown' }}" class="mb-4 p-4 rounded-md {{ $this->jobStatus['status'] === 'success' ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-800' }}">
            <div class="flex items-center">
                @if($this->jobStatus['status'] === 'pending')
                <svg class="animate-spin h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Schema import job is running...</span>
                @elseif($this->jobStatus['status'] === 'processing')
                <div class="w-full">
                    <div class="flex items-center mb-1">
                        <svg class="animate-spin h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ $this->jobStatus['message'] ?? 'Processing schema...' }}</span>
                    </div>
                    @if(isset($this->jobStatus['progress']))
                    <div class="w-full bg-blue-200 rounded-full h-2.5">
                        <div class="bg-blue-600 h-2.5 rounded-full" @if($this->jobStatus['progress']) style="width: {{$this->jobStatus['progress']}}%;" @endif></div>
                    </div>
                    <div class="text-xs text-right mt-1">{{ $this->jobStatus['progress'] }}% complete</div>
                    @endif
                </div>
                @elseif($this->jobStatus['status'] === 'success')
                <svg class="h-5 w-5 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Schema import completed successfully!</span>
                @elseif($this->jobStatus['status'] === 'error')
                <svg class="h-5 w-5 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span>Error: {{ $this->jobStatus['message'] ?? 'Unknown error' }}</span>
                @else
                <svg class="h-5 w-5 mr-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Job status: {{ $this->jobStatus['status'] }}</span>
                @endif
            </div>
        </div>
        @endif <div wire:key="schema-importer-form">
            {{ $this->form }}
        </div>
    </x-filament::section>

    @if($this->data['schema_import_job_id'] ?? false)
    <div
        x-data="{
                init() {
                    $wire.pollJobStatus()

                    // Start polling every 3 seconds
                    let interval = setInterval(() => $wire.pollJobStatus(), 3000)

                    // Listen for stop-polling event
                    $wire.$on('stop-polling', () => {
                        clearInterval(interval)
                    })
                }
            }"></div>
    @endif
</x-filament-panels::page>
