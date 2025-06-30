<x-filament-panels::page class="fi-resource-page-build">
    <div class="fi-section-content-ctn form-element-tree">
        {{ $this->form }}
    </div>

    @push('scripts')
    <script>
        // Real-time form version update handling
        document.addEventListener('DOMContentLoaded', function() {
            let updateTimeout;
            const DEBOUNCE_DELAY = 1000;

            // Function to trigger draft update
            function triggerDraftUpdate() {
                clearTimeout(updateTimeout);
                updateTimeout = setTimeout(() => {
                    window.Livewire.find('{{ $this->getId() }}').call('onFormDataUpdated');
                }, DEBOUNCE_DELAY);
            }

            // Monitor form input changes
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('input', triggerDraftUpdate);
                form.addEventListener('change', triggerDraftUpdate);
            }

            // Monitor Monaco editor changes if they exist
            let monacoCheckInterval = setInterval(() => {
                if (window.monaco && window.monaco.editor) {
                    const editors = window.monaco.editor.getModels();

                    editors.forEach(model => {
                        model.onDidChangeContent(() => {
                            clearTimeout(updateTimeout);
                            updateTimeout = setTimeout(() => {
                                window.Livewire.find('{{ $this->getId() }}').call('onFormDataUpdated');
                            }, 2000); // Longer debounce for code editors
                        });
                    });

                    if (editors.length > 0) {
                        clearInterval(monacoCheckInterval);
                    }
                }
            }, 500);

            // Clear interval after 10 seconds if Monaco isn't found
            setTimeout(() => {
                clearInterval(monacoCheckInterval);
            }, 10000);
        });
    </script>
    @endpush
</x-filament-panels::page>
